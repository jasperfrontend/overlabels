/**
 * StreamLabs Socket.IO Listener
 *
 * Bridges StreamLabs Socket.IO donation events to the Overlabels webhook pipeline.
 * Fetches active StreamLabs integrations from the Laravel internal API, maintains
 * Socket.IO connections per user, and POSTs donation events to the webhook endpoint.
 *
 * Usage:
 *   node streamlabs-listener.mjs
 *
 * Environment (reads from .env):
 *   APP_URL                    - Laravel app base URL (default: http://localhost:8000)
 *   STREAMLABS_LISTENER_SECRET - Shared secret for the internal API endpoint
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
      // Strip surrounding quotes
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
const LISTENER_SECRET = env('STREAMLABS_LISTENER_SECRET');
const REFRESH_INTERVAL_MS = 60_000; // Check for integration changes every 60s

if (!LISTENER_SECRET) {
  console.error('[StreamLabs Listener] STREAMLABS_LISTENER_SECRET is not set. Exiting.');
  process.exit(1);
}

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------
/** @type {Map<number, { socket: any, webhookToken: string, listenerSecret: string }>} */
const connections = new Map();

// ---------------------------------------------------------------------------
// Fetch active integrations from the Laravel internal API
// ---------------------------------------------------------------------------
async function fetchIntegrations() {
  const url = `${APP_URL}/api/internal/streamlabs/integrations`;

  try {
    const res = await fetch(url, {
      headers: {
        'X-Internal-Secret': LISTENER_SECRET,
        Accept: 'application/json',
      },
    });

    if (!res.ok) {
      console.error(`[StreamLabs Listener] API returned ${res.status}: ${await res.text()}`);
      return null;
    }

    const data = await res.json();
    return data.integrations ?? [];
  } catch (err) {
    console.error('[StreamLabs Listener] Failed to fetch integrations:', err.message);
    return null;
  }
}

// ---------------------------------------------------------------------------
// Relay a donation event to the Laravel webhook endpoint
// ---------------------------------------------------------------------------
async function relayEvent(webhookToken, listenerSecret, eventData) {
  const url = `${APP_URL}/api/webhooks/streamlabs/${webhookToken}`;

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
    console.log(`[StreamLabs Listener] Relayed donation -> ${res.status} ${body.status ?? ''}`);
  } catch (err) {
    console.error('[StreamLabs Listener] Relay failed:', err.message);
  }
}

// ---------------------------------------------------------------------------
// Manage Socket.IO connections
// ---------------------------------------------------------------------------
function connectSocket(integration) {
  const { id, webhook_token: webhookToken, socket_token: socketToken, listener_secret: listenerSecret } = integration;

  if (connections.has(id)) return; // Already connected

  console.log(`[StreamLabs Listener] Connecting integration #${id}...`);

  const socket = io(`https://sockets.streamlabs.com?token=${socketToken}`, {
    transports: ['websocket'],
    reconnection: true,
    reconnectionDelay: 5000,
    reconnectionDelayMax: 30000,
  });

  socket.on('connect', () => {
    console.log(`[StreamLabs Listener] Integration #${id} connected.`);
  });

  socket.on('disconnect', (reason) => {
    console.log(`[StreamLabs Listener] Integration #${id} disconnected: ${reason}`);
  });

  socket.on('connect_error', (err) => {
    console.error(`[StreamLabs Listener] Integration #${id} connection error: ${err.message}`);
  });

  socket.on('event', (eventData) => {
    if (eventData.type !== 'donation') return;

    console.log(`[StreamLabs Listener] Donation received for integration #${id}:`, {
      from: eventData.message?.[0]?.from ?? 'unknown',
      amount: eventData.message?.[0]?.formatted_amount ?? 'unknown',
    });

    relayEvent(webhookToken, listenerSecret, eventData);
  });

  connections.set(id, { socket, webhookToken, listenerSecret });
}

function disconnectSocket(integrationId) {
  const conn = connections.get(integrationId);
  if (!conn) return;

  console.log(`[StreamLabs Listener] Disconnecting integration #${integrationId}...`);
  conn.socket.disconnect();
  connections.delete(integrationId);
}

// ---------------------------------------------------------------------------
// Refresh loop - sync connections with active integrations
// ---------------------------------------------------------------------------
async function refresh() {
  const integrations = await fetchIntegrations();
  if (integrations === null) return; // API error, keep existing connections

  const activeIds = new Set(integrations.map((i) => i.id));

  // Disconnect removed/disabled integrations
  for (const id of connections.keys()) {
    if (!activeIds.has(id)) {
      disconnectSocket(id);
    }
  }

  // Connect new integrations
  for (const integration of integrations) {
    connectSocket(integration);
  }

  const count = connections.size;
  console.log(`[StreamLabs Listener] Active connections: ${count}`);
}

// ---------------------------------------------------------------------------
// Main
// ---------------------------------------------------------------------------
async function main() {
  console.log('[StreamLabs Listener] Starting...');
  console.log(`[StreamLabs Listener] App URL: ${APP_URL}`);

  await refresh();

  setInterval(refresh, REFRESH_INTERVAL_MS);

  // Graceful shutdown
  for (const signal of ['SIGINT', 'SIGTERM']) {
    process.on(signal, () => {
      console.log(`\n[StreamLabs Listener] Received ${signal}, shutting down...`);
      for (const id of connections.keys()) {
        disconnectSocket(id);
      }
      process.exit(0);
    });
  }
}

main();
