/**
 * The homepage Chat Checkin demo, for real: the product's own three.js globe
 * (resources/js/globe/checkinGlobe.ts) mounted into the demo scene, four
 * seeded viewers checking in one after another, and a chat box under the
 * scene where the visitor types !checkin and a city and watches their own
 * pin land. Places resolve through GET /api/checkin/resolve, which runs the
 * same PlaceResolverService the bot uses, so what the demo says is what the
 * product would say.
 *
 * three.js is a lazy chunk here exactly as in OverlayRenderer.vue: the
 * import only fires once the demo scrolls near the viewport, so the homepage
 * bundle never carries it. If that import or the WebGL mount fails, the
 * Blade-rendered fallback (a CSS disc, the seeded lines as plain text) stays
 * put and the chat box never appears.
 *
 * Everything the visitor can influence is written with textContent - no
 * innerHTML anywhere in this file.
 */

import type { GlobeInstance } from '@/globe/checkinGlobe';
import {
  EMPTY_STATE,
  REPLY_EMPTY,
  REPLY_MISS,
  distanceFromHomeKm,
  hudLines,
  nextState,
  parseCommand,
  pins,
  placeFromResponse,
  replyFromResponse,
  successReply,
  type DemoCheckin,
  type DemoPlace,
  type DemoState,
} from './checkinDemoState';

const RESOLVE_URL = '/api/checkin/resolve';
const VISITOR = { name: 'you', login: 'you', color: '#1daaff' } as const;
/**
 * Successful lookups per page load. The visitor has ONE pin (a re-checkin
 * moves it), so this is a request budget, not a pin count: 25 plus the four
 * seeds sits under the endpoint's throttle:30,1.
 */
const VISITOR_LOOKUP_CAP = 25;
const CHAT_LINES_KEPT = 8;
const SEED_INTERVAL_MS = 3000;
const SEED_REPLY_DELAY_MS = 900;
const SEED_RESOLVE_TIMEOUT_MS = 4000;

const REPLY_QUIET = 'Chat is quiet right now, try again.';
const REPLY_CAPPED = 'That is enough travelling for one demo. Install the product and let your chat do this.';
const REPLY_THROTTLED = 'Easy, chat. The bot answers again in a minute.';

interface Seed {
  name: string;
  color: string;
  query: string;
  /** Used only when the endpoint cannot be reached, so the demo still tells the truth about where these viewers are. */
  fallback: DemoPlace;
}

const SEEDS: Seed[] = [
  {
    name: 'pixelmoth',
    color: '#1E90FF',
    query: 'Lisbon',
    fallback: { name: 'Lisbon', country_code: 'PT', country: 'Portugal', label: 'Lisbon, PT', lat: 38.7167, lng: -9.1333 },
  },
  {
    name: 'quietfox',
    color: '#8A2BE2',
    query: 'Austin',
    fallback: { name: 'Austin', country_code: 'US', country: 'United States', label: 'Austin, US', lat: 30.2672, lng: -97.7431 },
  },
  {
    name: 'kettle_',
    color: '#FF69B4',
    query: 'Cape Town',
    fallback: { name: 'Cape Town', country_code: 'ZA', country: 'South Africa', label: 'Cape Town, ZA', lat: -33.9258, lng: 18.4232 },
  },
  {
    name: 'sunroof',
    color: '#2E8B57',
    query: 'Osaka',
    fallback: { name: 'Osaka', country_code: 'JP', country: 'Japan', label: 'Osaka, JP', lat: 34.6937, lng: 135.5023 },
  },
];

type Resolution = { kind: 'place'; place: DemoPlace } | { kind: 'reply'; reply: string } | { kind: 'offline' };

async function resolvePlace(query: string): Promise<Resolution> {
  const response = await fetch(`${RESOLVE_URL}?q=${encodeURIComponent(query)}`, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  });

  let body: unknown = null;
  try {
    body = await response.json();
  } catch {
    body = null;
  }

  if (response.ok) {
    const place = placeFromResponse(body);
    return place ? { kind: 'place', place } : { kind: 'reply', reply: REPLY_MISS };
  }

  if (response.status === 429) return { kind: 'reply', reply: REPLY_THROTTLED };
  if (response.status === 404 || response.status === 422) return { kind: 'reply', reply: replyFromResponse(body) };

  return { kind: 'offline' };
}

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function withTimeout<T>(promise: Promise<T>, ms: number, fallback: T): Promise<T> {
  return Promise.race([promise, sleep(ms).then(() => fallback)]);
}

function reducedMotion(): boolean {
  return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function webglAvailable(): boolean {
  if (typeof window.WebGLRenderingContext === 'undefined') return false;
  try {
    const canvas = document.createElement('canvas');
    const gl = canvas.getContext('webgl2') ?? canvas.getContext('webgl');
    if (!gl) return false;
    // Hand the probe context back so it does not hold one of the browser's
    // context slots while the real renderer asks for its own.
    gl.getExtension('WEBGL_lose_context')?.loseContext();
    return true;
  } catch {
    return false;
  }
}

/* ---- the scene ---- */

class CheckinDemo {
  private state: DemoState = EMPTY_STATE;
  private globe: GlobeInstance | null = null;
  private busy = false;
  private visitorLookups = 0;
  private visible = true;

  private readonly chat: HTMLElement;
  private readonly globeEl: HTMLElement;
  private readonly hud: Record<'count' | 'latest' | 'farthest', HTMLElement | null>;
  private readonly form: HTMLFormElement | null;
  private readonly input: HTMLInputElement | null;
  private readonly send: HTMLButtonElement | null;

  constructor(
    private readonly root: HTMLElement,
    chat: HTMLElement,
    globeEl: HTMLElement,
  ) {
    this.chat = chat;
    this.globeEl = globeEl;
    this.hud = {
      count: root.querySelector<HTMLElement>('[data-hud="count"]'),
      latest: root.querySelector<HTMLElement>('[data-hud="latest"]'),
      farthest: root.querySelector<HTMLElement>('[data-hud="farthest"]'),
    };
    this.form = root.querySelector<HTMLFormElement>('[data-checkin-form]');
    this.input = root.querySelector<HTMLInputElement>('[data-checkin-input]');
    this.send = root.querySelector<HTMLButtonElement>('[data-checkin-send]');
  }

  /** Swap the CSS stand-in for the real globe. Throws when three.js cannot mount; the caller keeps the fallback then. */
  async mount(): Promise<void> {
    const mod = await import('@/globe/checkinGlobe');

    // The renderer's constructor throws when it cannot get a WebGL context
    // (the probe in webglAvailable() proves nothing about this one), so the
    // fallback disc only goes once the canvas is actually there.
    this.globe = mod.mountCheckinGlobe(this.globeEl);
    this.globeEl.querySelector('[data-checkin-fallback]')?.remove();
    this.globe.update([]);
    this.globe.setPaused(!this.visible);

    // The Blade fallback rendered the finished scene as plain text; the live
    // one starts empty and earns its lines.
    this.chat.replaceChildren();
    this.renderHud();
    this.root.classList.add('is-live');

    this.wireForm();
    void this.runSeeds();
  }

  /** The render loop only runs while the demo is on screen; the seeds and the chat keep going regardless. */
  setVisible(visible: boolean): void {
    this.visible = visible;
    this.globe?.setPaused(!visible);
  }

  private wireForm(): void {
    if (!this.form || !this.input || !this.send) return;

    this.form.hidden = false;
    this.form.addEventListener('submit', (event) => {
      event.preventDefault();
      void this.submit();
    });
  }

  /* ---- seeded viewers ---- */

  private async runSeeds(): Promise<void> {
    const instant = reducedMotion();

    // Ask for all four up front so the answer is usually already here by the
    // time each line is due; the stagger is the show, not the network.
    const lookups = SEEDS.map((seed) => resolvePlace(seed.query).catch((): Resolution => ({ kind: 'offline' })));

    for (const [i, seed] of SEEDS.entries()) {
      if (!instant) await sleep(i === 0 ? 700 : SEED_INTERVAL_MS);
      if (!this.globe) return;

      this.addLine(seed.name, seed.color, `!checkin ${seed.query}`);
      if (!instant) await sleep(SEED_REPLY_DELAY_MS);
      if (!this.globe) return;

      const resolved = await withTimeout(lookups[i], instant ? 0 : SEED_RESOLVE_TIMEOUT_MS, { kind: 'offline' } as Resolution);
      const place = resolved.kind === 'place' ? resolved.place : seed.fallback;

      this.checkin({ name: seed.name, login: seed.name.toLowerCase(), place, at: Math.floor(Date.now() / 1000) });
    }
  }

  /* ---- the visitor ---- */

  private async submit(): Promise<void> {
    if (this.busy || !this.input) return;

    const raw = this.input.value;
    const parsed = parseCommand(raw);
    this.input.value = '';

    if ('empty' in parsed) {
      if (raw.trim() === '') return;
      this.addLine(VISITOR.name, VISITOR.color, '!checkin', true);
      this.addBotLine(REPLY_EMPTY);
      return;
    }

    this.addLine(VISITOR.name, VISITOR.color, `!checkin ${parsed.place}`, true);

    if (this.visitorLookups >= VISITOR_LOOKUP_CAP) {
      this.addBotLine(REPLY_CAPPED);
      return;
    }

    this.setBusy(true);
    try {
      const resolved = await resolvePlace(parsed.place);

      if (resolved.kind === 'place') {
        this.visitorLookups += 1;
        this.checkin({ name: VISITOR.name, login: VISITOR.login, place: resolved.place, at: Math.floor(Date.now() / 1000) });
      } else if (resolved.kind === 'reply') {
        this.addBotLine(resolved.reply);
      } else {
        this.addBotLine(REPLY_QUIET);
      }
    } catch {
      this.addBotLine(REPLY_QUIET);
    } finally {
      this.setBusy(false);
      // The reply may land seconds later; a visitor who scrolled on must not
      // be yanked back to the demo by the refocus.
      this.input.focus({ preventScroll: true });
    }
  }

  private setBusy(busy: boolean): void {
    this.busy = busy;
    if (this.input) this.input.disabled = busy;
    if (this.send) this.send.disabled = busy;
  }

  /* ---- one checkin landing: bot reply, pin, HUD ---- */

  private checkin(checkin: DemoCheckin): void {
    this.state = nextState(this.state, checkin);
    this.addBotLine(successReply(checkin.name, checkin.place.label, distanceFromHomeKm(checkin)));
    this.globe?.update(pins(this.state));
    this.renderHud();
    this.flashLabel(checkin.login);
  }

  private renderHud(): void {
    const lines = hudLines(this.state);
    for (const key of ['count', 'latest', 'farthest'] as const) {
      const el = this.hud[key];
      if (!el) continue;
      const text = lines[key];
      el.textContent = text ?? '';
      el.hidden = text === null;
    }
  }

  /** A newly landed label pops once; the renderer rebuilds every label on update, so find it fresh. */
  private flashLabel(login: string): void {
    const label = this.globeEl.querySelector<HTMLElement>(`.ol-globe-label[data-login="${CSS.escape(login)}"]`);
    if (!label) return;
    label.classList.add('is-new');
    label.addEventListener('animationend', () => label.classList.remove('is-new'), { once: true });
  }

  /* ---- chat column ---- */

  private addLine(name: string, color: string, text: string, isVisitor = false): void {
    const line = document.createElement('div');
    line.className = isVisitor ? 'ol-ck__line ol-ck__line--you' : 'ol-ck__line';

    const author = document.createElement('span');
    author.className = 'ol-ck__name';
    author.style.color = color;
    author.textContent = name;

    const cmd = document.createElement('span');
    cmd.className = 'ol-ck__cmd';
    cmd.textContent = text;

    line.append(author, ': ', cmd);
    this.appendLine(line);
  }

  private addBotLine(text: string): void {
    const line = document.createElement('div');
    line.className = 'ol-ck__line ol-ck__bot';

    const author = document.createElement('span');
    author.className = 'ol-ck__name';
    author.textContent = 'overlabels';

    line.append(author, ': ', text);
    this.appendLine(line);
  }

  private appendLine(line: HTMLElement): void {
    this.chat.appendChild(line);
    while (this.chat.children.length > CHAT_LINES_KEPT) {
      this.chat.firstElementChild?.remove();
    }
  }
}

/* ---- entry ---- */

export function initCheckinDemo(): void {
  // The partial is on the page twice - once in the hero showcase, once as
  // the product row - and each instance is its own demo with its own globe.
  document.querySelectorAll<HTMLElement>('[data-checkin-demo]').forEach(wireCheckinDemo);
}

function wireCheckinDemo(root: HTMLElement): void {
  const chat = root.querySelector<HTMLElement>('[data-checkin-chat]');
  const globeEl = root.querySelector<HTMLElement>('[data-checkin-globe]');
  if (!chat || !globeEl) return;

  // Wired once per instance, however many times this is called.
  if (root.dataset.checkinDemo === 'wired') return;
  root.dataset.checkinDemo = 'wired';

  let started = false;
  let demo: CheckinDemo | null = null;
  const start = (): void => {
    if (started) return;
    started = true;

    if (!webglAvailable()) return;

    demo = new CheckinDemo(root, chat, globeEl);
    demo.mount().catch((err: unknown) => {
      console.warn('checkin demo globe failed to load', err);
    });
  };

  if (typeof IntersectionObserver === 'undefined') {
    start();
    return;
  }

  // Stays observing after the mount: the globe's render loop sleeps while the
  // demo is scrolled out of view and wakes when it comes back.
  const observer = new IntersectionObserver(
    (entries) => {
      const visible = entries.some((entry) => entry.isIntersecting);
      if (visible) start();
      demo?.setVisible(visible);
    },
    { rootMargin: '200px 0px' },
  );
  observer.observe(root);
}
