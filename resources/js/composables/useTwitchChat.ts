import { type ChatFilters, EMPTY_CHAT_FILTERS, hasActiveFilters, shouldHideMessage } from '@/utils/chatFilters';
import { type ChatMessage, type ModerationAction, parseIrcLine, toChatMessage, toModerationAction } from '@/utils/ircParser';
import { ref } from 'vue';

/**
 * Read a channel's Twitch chat directly from Twitch, anonymously.
 *
 * The overlay connects to Twitch itself rather than having chat relayed through
 * Overlabels. Chat is public, an anonymous `justinfan` login needs no
 * credentials, and this keeps a firehose off the server entirely: no ingestion
 * cost, no metering, no added latency, and chat that keeps working even while
 * Overlabels is down.
 *
 * This does not violate "overlays never phone home" - that rule is about not
 * sending overlay data back out. This is read-only, one way, to a third party
 * the overlay is already about.
 *
 * The window is small and user-configurable up to a ceiling. Twitch's own chat
 * panel shows roughly 20 lines at 1080p and 30 at 1440p, so the default of 50 is
 * comfortably more than anyone can see, and beyond it we would be paying to
 * render messages that are scrolled out of existence. A streamer who wants a
 * three-line feed sets it on /settings/account with the other foreach caps.
 */

const TWITCH_IRC_URL = 'wss://irc-ws.chat.twitch.tv:443';

/**
 * Newest-N kept, when the user has expressed no preference.
 *
 * The user-facing setting lives with the other foreach caps on
 * /settings/account, because `[[[foreach:chat as msg]]]` is a foreach loop like
 * any other and "how many items does it expand to" should be the same question
 * everywhere. It arrives as `chat_window` in the render payload.
 */
const DEFAULT_WINDOW = 50;

/**
 * Hard ceiling, matching User::FOREACH_CAP_MAX server-side.
 *
 * Deliberately not raisable without measuring: 50 is already well past what
 * fits on screen (Twitch's own chat shows ~20 lines at 1080p, ~30 at 1440p),
 * and beyond it the overlay pays to render messages nobody can see.
 */
const MAX_WINDOW = 50;

/**
 * Coerce a requested window size into something usable.
 *
 * Exported for tests. The NaN branch is the one that matters in practice:
 * `Number(json.chat_window)` on a payload from an older server is NaN, and
 * falling through to `Math.max(1, ...)` would silently pin every overlay to a
 * one-message feed. An absent setting means "the default", not "one".
 */
export function clampWindow(size: number): number {
  if (!Number.isFinite(size)) return DEFAULT_WINDOW;

  return Math.max(1, Math.min(MAX_WINDOW, Math.floor(size)));
}

/**
 * How long changes are allowed to queue before being applied.
 *
 * Not a throughput fix - the renderer handles a 50-message feed in well under a
 * millisecond. It is an ANIMATION fix: the overlay re-renders by replacing its
 * innerHTML, which destroys any CSS transition mid-flight. Applying at most five
 * times a second keeps message-enter animations able to finish. 200 ms of
 * latency on a chat overlay is invisible to a viewer.
 */
const DEFAULT_FLUSH_MS = 200;

/** Reconnect backoff. OBS sources run for hours; the socket will drop. */
const RECONNECT_BASE_MS = 1_000;
const RECONNECT_MAX_MS = 30_000;

type QueuedChange = { kind: 'add'; message: ChatMessage } | { kind: 'moderate'; action: ModerationAction };

export interface UseTwitchChatOptions {
  windowSize?: number;
  flushMs?: number;
}

export function useTwitchChat(options: UseTwitchChatOptions = {}) {
  let windowSize = clampWindow(options.windowSize ?? DEFAULT_WINDOW);
  const flushMs = Math.max(0, options.flushMs ?? DEFAULT_FLUSH_MS);

  // Set once the render payload arrives. Until then nothing is filtered, which
  // is the right way round: showing a message the streamer wanted hidden for
  // the first instant is recoverable, and defaulting to hiding everything
  // would render an empty overlay on any payload problem.
  let filters: ChatFilters = EMPTY_CHAT_FILTERS;

  const messages = ref<ChatMessage[]>([]);
  const isConnected = ref(false);

  let socket: WebSocket | null = null;
  let channel = '';
  let closedByUs = false;
  let reconnectAttempt = 0;
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null;
  let flushTimer: ReturnType<typeof setTimeout> | null = null;

  // One ordered queue for additions AND deletions, rather than applying
  // moderation immediately. If a message and the CLEARMSG deleting it land in
  // the same window, order is the only thing that gets the result right.
  let queue: QueuedChange[] = [];

  function applyModeration(list: ChatMessage[], action: ModerationAction): ChatMessage[] {
    switch (action.type) {
      case 'delete_message':
        return list.filter((m) => m.id !== action.messageId);
      case 'purge_user':
        return list.filter((m) => m.userId !== action.userId);
      case 'purge_login':
        return list.filter((m) => m.login !== action.login);
      case 'clear_all':
        return [];
    }
  }

  function flush(): void {
    flushTimer = null;
    if (queue.length === 0) return;

    const pending = queue;
    queue = [];

    let next = messages.value.slice();
    for (const change of pending) {
      if (change.kind === 'add') {
        next.push(change.message);
      } else {
        next = applyModeration(next, change.action);
      }
    }

    // Trim from the front: oldest messages leave first, and index 0 stays
    // the oldest visible one so a template renders top-to-bottom.
    if (next.length > windowSize) {
      next = next.slice(next.length - windowSize);
    }

    messages.value = next;
  }

  function scheduleFlush(): void {
    if (flushTimer !== null) return;
    flushTimer = setTimeout(flush, flushMs);
  }

  function send(raw: string): void {
    if (socket && socket.readyState === WebSocket.OPEN) socket.send(raw);
  }

  function handleLine(raw: string): void {
    const line = parseIrcLine(raw);
    if (!line) return;

    // Twitch pings on a timer and drops connections that do not answer.
    if (line.command === 'PING') {
      send(`PONG :${line.trailing ?? 'tmi.twitch.tv'}`);
      return;
    }

    // Twitch asks clients to reconnect before it cycles a server. Honouring
    // it is the difference between a seamless handover and a dead overlay.
    if (line.command === 'RECONNECT') {
      reconnect();
      return;
    }

    const message = toChatMessage(line);
    if (message) {
      // Filtered at INGEST, not at render. A hidden message must not occupy
      // one of the 50 window slots, or a chatter spamming commands would push
      // real messages off the overlay while showing nothing themselves.
      if (hasActiveFilters(filters) && shouldHideMessage(message, filters)) return;

      queue.push({ kind: 'add', message });
      scheduleFlush();
      return;
    }

    const action = toModerationAction(line);
    if (action) {
      queue.push({ kind: 'moderate', action });
      scheduleFlush();
    }
  }

  function openSocket(): void {
    closedByUs = false;
    socket = new WebSocket(TWITCH_IRC_URL);

    socket.onopen = () => {
      reconnectAttempt = 0;
      isConnected.value = true;
      // Anonymous read-only login. Any `justinfan` nick is accepted with
      // no password, which is exactly why no credential ever has to reach
      // an OBS browser source.
      send('CAP REQ :twitch.tv/tags twitch.tv/commands');
      send(`NICK justinfan${Math.floor(Math.random() * 80_000) + 1_000}`);
      send(`JOIN #${channel}`);
    };

    socket.onmessage = (event: MessageEvent) => {
      // A frame can carry several lines.
      for (const raw of String(event.data).split('\r\n')) {
        if (raw) handleLine(raw);
      }
    };

    socket.onerror = () => {
      // onclose always follows, which is where reconnection is handled.
      isConnected.value = false;
    };

    socket.onclose = () => {
      isConnected.value = false;
      socket = null;
      if (closedByUs) return;

      const delay = Math.min(RECONNECT_BASE_MS * 2 ** reconnectAttempt, RECONNECT_MAX_MS);
      reconnectAttempt++;
      reconnectTimer = setTimeout(openSocket, delay);
    };
  }

  function reconnect(): void {
    if (socket) {
      closedByUs = true;
      socket.close();
      socket = null;
    }
    closedByUs = false;
    openSocket();
  }

  /** Join a channel by its lowercase login. Safe to call once per overlay. */
  function connect(channelLogin: string): void {
    const login = (channelLogin ?? '').trim().toLowerCase();
    if (!login || socket) return;

    channel = login;
    openSocket();
  }

  function disconnect(): void {
    closedByUs = true;
    if (reconnectTimer !== null) {
      clearTimeout(reconnectTimer);
      reconnectTimer = null;
    }
    if (flushTimer !== null) {
      clearTimeout(flushTimer);
      flushTimer = null;
    }
    queue = [];
    if (socket) {
      socket.close();
      socket = null;
    }
    isConnected.value = false;
  }

  /**
   * Apply display filters. Safe to call before or after connect().
   *
   * Only affects messages arriving from now on - anything already in the
   * window stays. Re-filtering the existing window would make a settings
   * change retroactively blank out messages already on screen, which reads as
   * a glitch rather than as the setting taking effect.
   */
  function setFilters(next: ChatFilters): void {
    filters = next;
  }

  /**
   * Set how many messages the window holds. Safe before or after connect().
   *
   * Trims immediately rather than waiting for the next message, so lowering the
   * setting takes visible effect at once instead of leaving a too-long feed
   * until chat happens to move.
   */
  function setWindowSize(size: number): void {
    const next = clampWindow(size);
    if (next === windowSize) return;

    windowSize = next;

    if (messages.value.length > windowSize) {
      messages.value = messages.value.slice(messages.value.length - windowSize);
    }
  }

  /**
   * Feed a raw IRC line in as though it had arrived on the socket.
   *
   * The seam the dev chat hose uses to load-test the renderer. Going in HERE
   * rather than pushing straight into `messages` is the whole point: it
   * exercises parsing, filtering, the ordered queue, flush batching, window
   * trimming and moderation - everything a real message touches except the
   * socket, which is the one part that is never the bottleneck.
   *
   * Harmless in production. It only affects what the caller's own browser
   * draws; nothing is sent anywhere and no other viewer is involved.
   */
  function injectRawLine(raw: string): void {
    handleLine(raw);
  }

  return { messages, isConnected, connect, disconnect, setFilters, setWindowSize, injectRawLine };
}
