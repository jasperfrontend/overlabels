/**
 * Overlabels EventSub front desk.
 *
 * Sits on exactly one route, overlabels.com/api/twitch/webhook, and answers
 * Twitch in milliseconds at any rate, so a raid's flood of notifications can
 * never make Twitch revoke a streamer's subscriptions for slow answers. The
 * notifications wait in a Cloudflare Queue and reach Laravel in batches of up
 * to 100, one batch at a time, at /api/twitch/webhook/batch.
 *
 * The edge stays dumb on purpose: no Twitch secrets, no user data, no
 * decisions. Laravel verifies every signature when a batch arrives, exactly as
 * it does for the direct webhook. Anything that is not a notification
 * (Twitch's subscription handshake, revocations) and anything the queue
 * refuses goes straight through to Laravel unchanged.
 *
 * MODE = "passthrough" forwards every request untouched. MODE = "queue" turns
 * the front desk on. See docs/design/eventsub-front-desk.md.
 */

const HEADER_ID = 'Twitch-Eventsub-Message-Id';
const HEADER_TIMESTAMP = 'Twitch-Eventsub-Message-Timestamp';
const HEADER_SIGNATURE = 'Twitch-Eventsub-Message-Signature';
const HEADER_TYPE = 'Twitch-Eventsub-Message-Type';

/** One queue operation covers 64 KB; Twitch notifications are a few KB. */
export const MAX_QUEUED_BODY_BYTES = 64 * 1024;

/** Retry backoff for a batch Laravel could not take, capped at 15 minutes. */
export const MAX_RETRY_DELAY_SECONDS = 15 * 60;

export function retryDelaySeconds(attempts) {
    return Math.min(MAX_RETRY_DELAY_SECONDS, 5 * 2 ** Math.max(0, attempts - 1));
}

/** Twitch timestamps carry nanoseconds; order by them as milliseconds. */
export function sentAtMs(timestamp) {
    const ms = Date.parse(String(timestamp ?? '').replace(/(\.\d{3})\d+/, '$1'));

    return Number.isFinite(ms) ? ms : 0;
}

/** The request as it arrived, for the origin. A consumed body is replayed from text. */
function forward(request, body) {
    if (body === undefined) {
        return fetch(request);
    }

    return fetch(request.url, { method: request.method, headers: request.headers, body });
}

export async function handleFetch(request, env) {
    if (env.MODE !== 'queue' || request.method !== 'POST') {
        return forward(request);
    }

    // The handshake and revocations write to Laravel's database and are rare.
    if (request.headers.get(HEADER_TYPE) !== 'notification') {
        return forward(request);
    }

    const id = request.headers.get(HEADER_ID);
    const timestamp = request.headers.get(HEADER_TIMESTAMP);
    const signature = request.headers.get(HEADER_SIGNATURE);
    if (!id || !timestamp || !signature) {
        return new Response('Missing Twitch EventSub headers', { status: 400 });
    }

    const body = await request.text();

    let data;
    try {
        data = JSON.parse(body);
    } catch {
        return new Response('Invalid JSON', { status: 400 });
    }
    if (!data || typeof data !== 'object' || !data.subscription || !data.subscription.type) {
        return new Response('Not an EventSub notification', { status: 400 });
    }

    // Too big for one queue operation: rare enough to hand to Laravel as is.
    if (new TextEncoder().encode(body).length > MAX_QUEUED_BODY_BYTES) {
        return forward(request, body);
    }

    try {
        await env.EVENTSUB_QUEUE.send({ id, timestamp, signature, body }, { contentType: 'json' });
    } catch (error) {
        // Queue full or rate limited: today's behaviour, never a dropped event.
        console.error('front desk: queue send failed, passing through', String(error));

        return forward(request, body);
    }

    return new Response('OK', { status: 200 });
}

export async function handleQueue(batch, env) {
    const messages = [...batch.messages].sort((a, b) => sentAtMs(a.body?.timestamp) - sentAtMs(b.body?.timestamp));
    const attempts = Math.max(1, ...messages.map((m) => m.attempts ?? 1));

    let response;
    try {
        response = await fetch(env.BATCH_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                // Trimmed on both ends: a secret piped in through a shell can
                // pick up a trailing newline in one store and not the other.
                'X-Front-Desk-Secret': String(env.FRONT_DESK_SECRET ?? '').trim(),
                'User-Agent': 'overlabels-eventsub-front-desk',
            },
            body: JSON.stringify({ messages: messages.map((m) => m.body) }),
        });
    } catch (error) {
        console.error('front desk: Laravel unreachable, retrying batch', String(error));
        batch.retryAll({ delaySeconds: retryDelaySeconds(attempts) });

        return;
    }

    if (!response.ok) {
        console.error(`front desk: Laravel answered ${response.status}, retrying batch`);
        batch.retryAll({ delaySeconds: retryDelaySeconds(attempts) });

        return;
    }

    let results = {};
    try {
        results = (await response.json())?.results ?? {};
    } catch {
        batch.retryAll({ delaySeconds: retryDelaySeconds(attempts) });

        return;
    }

    // stored, duplicate, invalid and ignored are final; error or no answer is retried.
    for (const message of messages) {
        const result = results[message.body?.id];
        if (result === 'stored' || result === 'duplicate' || result === 'invalid' || result === 'ignored') {
            message.ack();
        } else {
            message.retry({ delaySeconds: retryDelaySeconds(message.attempts ?? 1) });
        }
    }
}

export default {
    fetch: handleFetch,
    queue: handleQueue,
};
