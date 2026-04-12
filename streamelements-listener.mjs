/**
 * StreamElements Socket.IO Listener
 *
 * Bridges StreamElements Socket.IO tip events to the Overlabels webhook pipeline.
 * Fetches active StreamElements integrations from the Laravel internal API,
 * maintains Socket.IO connections per user, and POSTs tip events to the webhook
 * endpoint.
 *
 * Usage:
 *   node streamelements-listener.mjs
 *
 * Environment (reads from .env):
 *   APP_URL                       - Laravel app base URL (default: http://localhost:8000)
 *   STREAMELEMENTS_LISTENER_SECRET - Shared secret for the internal API endpoint
 */

import { io } from 'socket.io-client';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

// ---------------------------------------------------------------------------
// .env parser (minimal, no dependencies)
// ---------------------------------------------------------------------------
function loadEnv() {
  const env = {};
  try {
    const content = readFileSync(resolve(__dirname, '.env'), 'utf-8');
    for (const line of content.split('\n')) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith('#')) continue;
      const eqIdx = trimmed.indexOf('=');
      if (eqIdx === -1) continue;
      const key = trimmed.slice(0, eqIdx).trim();
      let value = trimmed.slice(eqIdx + 1).trim();
      if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
        value = value.slice(1, -1);
      }
      env[key] = value;
    }
  } catch {
    // .env file not found - fall back to process.env
  }
  return env;
}

const dotenv = loadEnv();
function env(key, fallback = '') {
  return process.env[key] || dotenv[key] || fallback;
}

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------
const APP_URL = env('APP_URL', 'http://localhost:8000');
const LISTENER_SECRET = env('STREAMELEMENTS_LISTENER_SECRET');
const REFRESH_INTERVAL_MS = 60_000;

if (!LISTENER_SECRET) {
  console.error('[StreamElements Listener] STREAMELEMENTS_LISTENER_SECRET is not set. Exiting.');
  process.exit(1);
}

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
/** @type {Map<number, { socket: any, webhookToken: string, listenerSecret: string, jwtToken: string }>} */
const connections = new Map();

// ---------------------------------------------------------------------------
// Fetch active integrations from the Laravel internal API
// ---------------------------------------------------------------------------
async function fetchIntegrations() {
  const url = `${APP_URL}/api/internal/streamelements/integrations`;

  try {
    const res = await fetch(url, {
      headers: {
        'X-Internal-Secret': LISTENER_SECRET,
        Accept: 'application/json',
      },
    });

    if (!res.ok) {
      console.error(`[StreamElements Listener] API returned ${res.status}: ${await res.text()}`);
      return null;
    }

    const data = await res.json();
    return data.integrations ?? [];
  } catch (err) {
    console.error('[StreamElements Listener] Failed to fetch integrations:', err.message);
    return null;
  }
}

// ---------------------------------------------------------------------------
// Relay a tip event to the Laravel webhook endpoint
// ---------------------------------------------------------------------------
async function relayEvent(webhookToken, listenerSecret, eventData) {
  const url = `${APP_URL}/api/webhooks/streamelements/${webhookToken}`;

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Listener-Secret': listenerSecret,
      },
      body: JSON.stringify(eventData),
    });

    const body = await res.json().catch(() => ({}));
    console.log(`[StreamElements Listener] Relayed tip -> ${res.status} ${body.status ?? ''}`);
  } catch (err) {
    console.error('[StreamElements Listener] Relay failed:', err.message);
  }
}

// ---------------------------------------------------------------------------
// Manage Socket.IO connections
// ---------------------------------------------------------------------------
function connectSocket(integration) {
  const { id, webhook_token: webhookToken, jwt_token: jwtToken, listener_secret: listenerSecret } = integration;

  const existing = connections.get(id);
  if (existing) {
    if (existing.jwtToken === jwtToken) return;
    console.log(`[StreamElements Listener] JWT for integration #${id} changed; reconnecting...`);
    disconnectSocket(id);
  }

  console.log(`[StreamElements Listener] Connecting integration #${id}...`);

  const socket = io('https://realtime.streamelements.com', {
    transports: ['websocket'],
    reconnection: true,
    reconnectionDelay: 5000,
    reconnectionDelayMax: 30000,
  });

  socket.on('connect', () => {
    console.log(`[StreamElements Listener] Integration #${id} socket connected, authenticating...`);
    socket.emit('authenticate', { method: 'jwt', token: jwtToken });
  });

  socket.on('authenticated', (data) => {
    console.log(`[StreamElements Listener] Integration #${id} authenticated for channel ${data?.channelId ?? 'unknown'}.`);
  });

  socket.on('unauthorized', (err) => {
    console.error(`[StreamElements Listener] Integration #${id} unauthorized:`, err);
    // JWT likely revoked. Drop the socket; user must regenerate and save a new JWT.
    disconnectSocket(id);
  });

  socket.on('disconnect', (reason) => {
    console.log(`[StreamElements Listener] Integration #${id} disconnected: ${reason}`);
  });

  socket.on('connect_error', (err) => {
    console.error(`[StreamElements Listener] Integration #${id} connection error: ${err.message}`);
  });

  socket.on('event', (eventData) => {
    if (eventData?.type !== 'tip') return;

    console.log(`[StreamElements Listener] Tip received for integration #${id}:`, {
      from: eventData.data?.displayName ?? eventData.data?.username ?? 'unknown',
      amount: eventData.data?.amount ?? 'unknown',
      currency: eventData.data?.currency ?? '',
    });

    relayEvent(webhookToken, listenerSecret, eventData);
  });

  connections.set(id, { socket, webhookToken, listenerSecret, jwtToken });
}

function disconnectSocket(integrationId) {
  const conn = connections.get(integrationId);
  if (!conn) return;

  console.log(`[StreamElements Listener] Disconnecting integration #${integrationId}...`);
  conn.socket.disconnect();
  connections.delete(integrationId);
}

// ---------------------------------------------------------------------------
// Refresh loop - sync connections with active integrations
// ---------------------------------------------------------------------------
async function refresh() {
  const integrations = await fetchIntegrations();
  if (integrations === null) return;

  const activeIds = new Set(integrations.map((i) => i.id));

  for (const id of connections.keys()) {
    if (!activeIds.has(id)) {
      disconnectSocket(id);
    }
  }

  for (const integration of integrations) {
    connectSocket(integration);
  }

  const count = connections.size;
  console.log(`[StreamElements Listener] Active connections: ${count}`);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
async function main() {
  console.log('[StreamElements Listener] Starting...');
  console.log(`[StreamElements Listener] App URL: ${APP_URL}`);

  await refresh();

  setInterval(refresh, REFRESH_INTERVAL_MS);

  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
      console.log(`\n[StreamElements Listener] Received ${signal}, shutting down...`);
      for (const id of connections.keys()) {
        disconnectSocket(id);
      }
      process.exit(0);
    });
  }
}

main();
