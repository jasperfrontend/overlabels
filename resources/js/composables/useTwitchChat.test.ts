import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { clampWindow, useTwitchChat } from './useTwitchChat';

/*
 * The suite runs in node by deliberate choice - see the testing note in
 * CLAUDE.md - so the socket lifecycle is tested against a fake WebSocket below
 * rather than a real one.
 */

/**
 * Just enough WebSocket for the lifecycle. The one property that matters is
 * that close() fires `onclose` LATER, as browsers do: the duplicate-message bug
 * lived entirely in that gap.
 */
class FakeSocket {
  static readonly OPEN = 1;
  static all: FakeSocket[] = [];

  readyState = 0;
  closed = false;
  onopen: (() => void) | null = null;
  onmessage: ((event: { data: string }) => void) | null = null;
  onerror: (() => void) | null = null;
  onclose: (() => void) | null = null;

  constructor() {
    FakeSocket.all.push(this);
    queueMicrotask(() => {
      this.readyState = FakeSocket.OPEN;
      this.onopen?.();
    });
  }

  send(): void {}

  close(): void {
    if (this.closed) return;
    this.closed = true;
    this.readyState = 3;
    setTimeout(() => this.onclose?.(), 0);
  }

  /** Twitch delivers a line to every connection that joined the channel. */
  static deliver(line: string): void {
    for (const socket of [...FakeSocket.all]) {
      if (!socket.closed) socket.onmessage?.({ data: line });
    }
  }

  static openCount(): number {
    return FakeSocket.all.filter((s) => !s.closed).length;
  }
}

let nextId = 0;
const privmsg = (text: string) => `@id=msg-${++nextId};user-id=1;display-name=Viewer :viewer!viewer@viewer.tmi.twitch.tv PRIVMSG #channel :${text}`;

describe('useTwitchChat socket lifecycle', () => {
  beforeEach(() => {
    FakeSocket.all = [];
    vi.useFakeTimers();
    vi.stubGlobal('WebSocket', FakeSocket);
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllGlobals();
  });

  it('keeps one connection, and each message once, across a Twitch RECONNECT', async () => {
    // Reported on stream: every message three times, then seven, growing
    // over the days an OBS source stays up. Each RECONNECT orphaned the new
    // socket and opened another, and every orphan kept adding messages.
    const chat = useTwitchChat({ flushMs: 0 });
    chat.connect('channel');
    await vi.advanceTimersByTimeAsync(5_000);

    for (let i = 0; i < 3; i++) {
      FakeSocket.deliver(':tmi.twitch.tv RECONNECT');
      await vi.advanceTimersByTimeAsync(5_000);
    }

    expect(FakeSocket.openCount()).toBe(1);

    FakeSocket.deliver(privmsg('Hi'));
    await vi.advanceTimersByTimeAsync(5_000);

    expect(chat.messages.value.map((m) => m.text)).toEqual(['Hi']);
    chat.disconnect();
  });

  it('still reconnects on its own after an unexpected drop', async () => {
    const chat = useTwitchChat({ flushMs: 0 });
    chat.connect('channel');
    await vi.advanceTimersByTimeAsync(5_000);

    FakeSocket.all[0].close();
    await vi.advanceTimersByTimeAsync(5_000);

    expect(FakeSocket.all).toHaveLength(2);
    expect(FakeSocket.openCount()).toBe(1);
    chat.disconnect();
  });
});

describe('clampWindow', () => {
  it('keeps a sensible size as-is', () => {
    expect(clampWindow(1)).toBe(1);
    expect(clampWindow(4)).toBe(4);
    expect(clampWindow(50)).toBe(50);
  });

  it('falls back to the default rather than to one when the value is not a number', () => {
    // THE case that matters. `Number(json.chat_window)` against a payload from
    // an older server is NaN, and clamping that to the low bound would pin
    // every overlay to a one-message feed instead of leaving it at 50.
    expect(clampWindow(NaN)).toBe(50);
    expect(clampWindow(Number(undefined))).toBe(50);
    expect(clampWindow(Number('nonsense'))).toBe(50);
    expect(clampWindow(Infinity)).toBe(50);
  });

  it('clamps to the ceiling, which matches FOREACH_CAP_MAX server-side', () => {
    expect(clampWindow(51)).toBe(50);
    expect(clampWindow(9999)).toBe(50);
  });

  it('clamps to at least one, since a zero-message feed is never intended', () => {
    expect(clampWindow(0)).toBe(1);
    expect(clampWindow(-10)).toBe(1);
  });

  it('floors a fractional size', () => {
    expect(clampWindow(4.9)).toBe(4);
  });
});
