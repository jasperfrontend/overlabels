import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { handleFetch, handleQueue, MAX_QUEUED_BODY_BYTES, MAX_SEND_WAIT_MS, retryDelaySeconds, sentAtMs } from './index.js';

const URL_ = 'https://overlabels.com/api/twitch/webhook';

function twitchRequest(type: string, body: string, headers: Record<string, string> = {}) {
  return new Request(URL_, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Twitch-Eventsub-Message-Id': 'msg-1',
      'Twitch-Eventsub-Message-Timestamp': '2026-11-22T21:21:43.3305328Z',
      'Twitch-Eventsub-Message-Signature': 'sha256=abc',
      'Twitch-Eventsub-Message-Type': type,
      ...headers,
    },
    body,
  });
}

const FOLLOW = JSON.stringify({ subscription: { type: 'channel.follow' }, event: { broadcaster_user_id: '1', user_id: '42' } });

let origin: ReturnType<typeof vi.fn>;
let send: ReturnType<typeof vi.fn>;

beforeEach(() => {
  origin = vi.fn(async () => new Response('from origin', { status: 200 }));
  vi.stubGlobal('fetch', origin);
  send = vi.fn(async () => undefined);
});

afterEach(() => vi.unstubAllGlobals());

const env = (mode: string) => ({
  MODE: mode,
  EVENTSUB_QUEUE: { send },
  BATCH_URL: 'https://overlabels.com/api/twitch/webhook/batch',
  FRONT_DESK_SECRET: 's3cret',
});

describe('fetch: passthrough mode', () => {
  it('forwards a notification to Laravel untouched and queues nothing', async () => {
    const response = await handleFetch(twitchRequest('notification', FOLLOW), env('passthrough'));

    expect(await response.text()).toBe('from origin');
    expect(origin).toHaveBeenCalledTimes(1);
    expect(send).not.toHaveBeenCalled();
  });
});

describe('fetch: queue mode', () => {
  it('queues a notification with its headers and exact body, and answers 200 itself', async () => {
    const response = await handleFetch(twitchRequest('notification', FOLLOW), env('queue'));

    expect(response.status).toBe(200);
    expect(origin).not.toHaveBeenCalled();
    expect(send).toHaveBeenCalledWith(
      { id: 'msg-1', timestamp: '2026-11-22T21:21:43.3305328Z', signature: 'sha256=abc', body: FOLLOW },
      { contentType: 'json' },
    );
  });

  it('says in headers how long the queue write and the handler took', async () => {
    send.mockImplementationOnce(() => new Promise((resolve) => setTimeout(resolve, 30)));

    const response = await handleFetch(twitchRequest('notification', FOLLOW), env('queue'));

    expect(Number(response.headers.get('X-Front-Desk-Send-Ms'))).toBeGreaterThanOrEqual(25);
    expect(Number(response.headers.get('X-Front-Desk-Handler-Ms'))).toBeGreaterThanOrEqual(Number(response.headers.get('X-Front-Desk-Send-Ms')));
    expect(response.headers.get('X-Front-Desk-Send')).toBe('sent');
  });

  it('answers Twitch after MAX_SEND_WAIT_MS when the queue write is slow, and lets the write finish', async () => {
    vi.useFakeTimers();
    let finish: () => void = () => {};
    send.mockImplementationOnce(() => new Promise<void>((resolve) => (finish = resolve)));
    const background: Promise<unknown>[] = [];

    const pending = handleFetch(twitchRequest('notification', FOLLOW), env('queue'), { waitUntil: (p: Promise<unknown>) => background.push(p) });
    await vi.advanceTimersByTimeAsync(MAX_SEND_WAIT_MS);
    const response = await pending;

    expect(response.status).toBe(200);
    expect(response.headers.get('X-Front-Desk-Send')).toBe('slow');
    expect(background).toHaveLength(1);

    finish();
    await Promise.all(background);
    expect(origin).not.toHaveBeenCalled();
    vi.useRealTimers();
  });

  it('hands a slow write that fails after the answer to Laravel in the background', async () => {
    vi.useFakeTimers();
    let fail: (e: Error) => void = () => {};
    send.mockImplementationOnce(() => new Promise<void>((_, reject) => (fail = reject)));
    const background: Promise<unknown>[] = [];

    const pending = handleFetch(twitchRequest('notification', FOLLOW), env('queue'), { waitUntil: (p: Promise<unknown>) => background.push(p) });
    await vi.advanceTimersByTimeAsync(MAX_SEND_WAIT_MS);
    expect((await pending).status).toBe(200);

    fail(new Error('queue gave up'));
    await Promise.all(background);
    expect(origin).toHaveBeenCalledTimes(1);
    vi.useRealTimers();
  });

  it('passes the subscription handshake and revocations through to Laravel', async () => {
    for (const type of ['webhook_callback_verification', 'revocation']) {
      await handleFetch(twitchRequest(type, '{"challenge":"x","subscription":{"type":"channel.follow"}}'), env('queue'));
    }

    expect(origin).toHaveBeenCalledTimes(2);
    expect(send).not.toHaveBeenCalled();
  });

  it('refuses something that is not shaped like Twitch, without bothering Laravel', async () => {
    const noHeaders = await handleFetch(twitchRequest('notification', FOLLOW, { 'Twitch-Eventsub-Message-Signature': '' }), env('queue'));
    const notJson = await handleFetch(twitchRequest('notification', 'nope'), env('queue'));
    const noType = await handleFetch(twitchRequest('notification', '{"event":{}}'), env('queue'));

    expect([noHeaders.status, notJson.status, noType.status]).toEqual([400, 400, 400]);
    expect(origin).not.toHaveBeenCalled();
    expect(send).not.toHaveBeenCalled();
  });

  it('falls back to Laravel, body intact, when the queue refuses the message', async () => {
    send.mockRejectedValueOnce(new Error('Too Many Requests'));

    await handleFetch(twitchRequest('notification', FOLLOW), env('queue'));

    expect(origin).toHaveBeenCalledTimes(1);
    const [url, init] = origin.mock.calls[0];
    expect(url).toBe(URL_);
    expect(init.body).toBe(FOLLOW);
    expect(new Headers(init.headers).get('Twitch-Eventsub-Message-Id')).toBe('msg-1');
  });

  it('hands a body too big for one queue operation straight to Laravel', async () => {
    const big = JSON.stringify({ subscription: { type: 'channel.follow' }, event: { pad: 'x'.repeat(MAX_QUEUED_BODY_BYTES) } });

    await handleFetch(twitchRequest('notification', big), env('queue'));

    expect(send).not.toHaveBeenCalled();
    expect(origin.mock.calls[0][1].body).toBe(big);
  });
});

function queueMessage(id: string, timestamp: string, attempts = 1) {
  return { body: { id, timestamp, signature: 's', body: '{}' }, attempts, ack: vi.fn(), retry: vi.fn() };
}

function fakeBatch(messages: ReturnType<typeof queueMessage>[]) {
  return { messages, retryAll: vi.fn(), ackAll: vi.fn() };
}

describe('queue consumer', () => {
  it('posts the batch to Laravel in Twitch timestamp order, with the shared secret', async () => {
    origin.mockResolvedValueOnce(Response.json({ results: { late: 'stored', early: 'stored' } }));
    const batch = fakeBatch([queueMessage('late', '2026-11-22T21:21:44.1000000Z'), queueMessage('early', '2026-11-22T21:21:43.9000000Z')]);

    await handleQueue(batch, env('queue'));

    const [url, init] = origin.mock.calls[0];
    expect(url).toBe('https://overlabels.com/api/twitch/webhook/batch');
    expect(new Headers(init.headers).get('X-Front-Desk-Secret')).toBe('s3cret');
    expect(JSON.parse(init.body).messages.map((m: { id: string }) => m.id)).toEqual(['early', 'late']);
  });

  it('sends the shared secret without a trailing newline', async () => {
    origin.mockResolvedValueOnce(Response.json({ results: {} }));

    await handleQueue(fakeBatch([queueMessage('a', '2026-11-22T21:21:43Z')]), { ...env('queue'), FRONT_DESK_SECRET: 's3cret\n' });

    expect(new Headers(origin.mock.calls[0][1].headers).get('X-Front-Desk-Secret')).toBe('s3cret');
  });

  it('acks what Laravel settled and retries what it could not', async () => {
    origin.mockResolvedValueOnce(Response.json({ results: { a: 'stored', b: 'duplicate', c: 'invalid', d: 'error' } }));
    const [a, b, c, d, e] = ['a', 'b', 'c', 'd', 'e'].map((id) => queueMessage(id, '2026-11-22T21:21:43Z'));

    await handleQueue(fakeBatch([a, b, c, d, e]), env('queue'));

    for (const settled of [a, b, c]) expect(settled.ack).toHaveBeenCalled();
    for (const unsettled of [d, e]) expect(unsettled.retry).toHaveBeenCalled();
  });

  it('retries the whole batch, with backoff, when Laravel is down or refuses it', async () => {
    origin.mockRejectedValueOnce(new Error('connect ECONNREFUSED'));
    const down = fakeBatch([queueMessage('a', '2026-11-22T21:21:43Z', 3)]);
    await handleQueue(down, env('queue'));
    expect(down.retryAll).toHaveBeenCalledWith({ delaySeconds: retryDelaySeconds(3) });

    origin.mockResolvedValueOnce(new Response('Unauthorized', { status: 401 }));
    const refused = fakeBatch([queueMessage('a', '2026-11-22T21:21:43Z')]);
    await handleQueue(refused, env('queue'));
    expect(refused.retryAll).toHaveBeenCalled();
  });
});

describe('helpers', () => {
  it('backs off exponentially and never past 15 minutes', () => {
    expect([1, 2, 3, 4].map(retryDelaySeconds)).toEqual([5, 10, 20, 40]);
    expect(retryDelaySeconds(100)).toBe(900);
  });

  it('reads Twitch nanosecond timestamps', () => {
    expect(sentAtMs('2026-11-22T21:21:43.3305328Z')).toBe(Date.parse('2026-11-22T21:21:43.330Z'));
    expect(sentAtMs('garbage')).toBe(0);
  });
});
