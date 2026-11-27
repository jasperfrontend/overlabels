import type { CheckinPin } from '@/utils/checkinSlots';
import { createPinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createApp, type App } from 'vue';

/**
 * OverlayRenderer mounted for real in happy-dom, with only its edges faked:
 * the render endpoint (fetch), Reverb (window.Echo) and the three.js globe,
 * which happy-dom cannot draw. What is tested is the renderer's own wiring.
 */

// Every update() the globe receives, newest last.
const globeUpdates: CheckinPin[][] = [];

vi.mock('@/globe/checkinGlobe', () => ({
  mountCheckinGlobe: () => ({
    update: (pins: CheckinPin[]) => globeUpdates.push(pins),
    setPaused: () => {},
    destroy: () => {},
  }),
}));

// Handlers the renderer registers on its private channel, by event name.
let handlers: Record<string, (event: unknown) => void> = {};

/** A stand-in for whatever Echo is asked for that this test does not care about. */
function inert(): unknown {
  const fn = () => proxy;
  const proxy: unknown = new Proxy(fn, { get: (_target, key) => (key === 'then' ? undefined : proxy), apply: () => proxy });
  return proxy;
}

function fakeEcho() {
  const channel = {
    listen(name: string, handler: (event: unknown) => void) {
      handlers[name] = handler;
      return channel;
    },
    subscribed: () => channel,
    error: () => channel,
    stopListening: () => channel,
    listenForWhisper: () => channel,
    notification: () => channel,
  };
  return { private: () => channel, channel: () => channel, leave: () => {}, leaveChannel: () => {}, connector: inert(), socketId: () => 'test' };
}

/** A render payload for a globe overlay with no HUD and no pin list: nothing else in the HTML reads a checkin. */
function renderPayload() {
  return {
    template: { html: '<div class="stage">[[[checkin_globe]]]</div>', css: '', head: null, tags: ['checkin_globe'], compiled_css: '' },
    data: { user_twitch_id: '1234' },
    checkins_window: 50,
  };
}

function pin(login: string): CheckinPin {
  return { name: login, login, place: 'Reykjavik, IS', country: 'Iceland', country_code: 'IS', lat: '64.1', lng: '-21.9', at: '1', distance: '' };
}

const loginsIn = (pins: CheckinPin[] | undefined) => (pins ?? []).map((p) => p.login);

let app: App | null = null;

beforeEach(() => {
  globeUpdates.length = 0;
  handlers = {};
  (window as unknown as { Echo: unknown }).Echo = fakeEcho();
  vi.stubGlobal(
    'fetch',
    vi.fn(async () => new Response(JSON.stringify(renderPayload()), { status: 200, headers: { 'Content-Type': 'application/json' } })),
  );
});

afterEach(() => {
  app?.unmount();
  app = null;
  vi.unstubAllGlobals();
});

async function mountRenderer(): Promise<void> {
  const { default: OverlayRenderer } = await import('@/components/OverlayRenderer.vue');
  const host = document.createElement('div');
  document.body.appendChild(host);
  app = createApp(OverlayRenderer, { slug: 'globe-test', token: 'a'.repeat(64) });
  app.use(createPinia());
  app.mount(host);

  // The globe chunk loads after the payload; it is up once it has had its first pins.
  await vi.waitFor(() => expect(globeUpdates.length).toBeGreaterThan(0));
  await vi.waitFor(() => expect(handlers['.checkins.updated']).toBeTypeOf('function'));
}

describe('OverlayRenderer and the checkin globe', () => {
  it('hands a real checkin to the globe when nothing else in the HTML changes', async () => {
    await mountRenderer();
    const before = globeUpdates.length;

    handlers['.checkins.updated']({ pin: pin('viewer_one'), count: 1 });

    await vi.waitFor(() => expect(globeUpdates.length).toBeGreaterThan(before));
    expect(loginsIn(globeUpdates.at(-1))).toContain('viewer_one');
  });
});
