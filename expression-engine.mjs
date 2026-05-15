/**
 * Expression Engine Sidecar
 *
 * Localhost HTTP service that evaluates Overlabels Expression Control
 * formulas server-side. Imports the same engine code the frontend overlay
 * uses (`resources/js/lib/expression-engine/engine.mjs`), so parity with
 * the overlay's jsep eval is by construction - not by parallel
 * implementations and parity tests.
 *
 * Why a sidecar instead of in-process PHP: the frontend evaluator is the
 * single source of truth for expression semantics. Reimplementing it in
 * PHP would create a drift surface. Running the same JS twice (once in
 * the overlay, once here) keeps it one codebase.
 *
 * Usage:
 *   node expression-engine.mjs
 *
 * Environment (.env or process env):
 *   EXPRESSION_ENGINE_HOST    - Bind host (default: 127.0.0.1)
 *   EXPRESSION_ENGINE_PORT    - Bind port (default: 3010)
 *   EXPRESSION_ENGINE_SECRET  - Shared secret expected in X-Internal-Secret
 *
 * Endpoints:
 *   POST /evaluate  { expression, data } -> { ok, value } | { ok: false, error }
 *   GET  /health    -> { ok: true }
 */

import { createServer } from 'http';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { evaluateExpression } from './resources/js/lib/expression-engine/engine.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));

// ---------------------------------------------------------------------------
// .env loader (minimal, matches the listener-sidecar pattern)
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
    // No .env in production containers; falls back to process.env.
  }
  return env;
}

const dotenv = loadEnv();
function env(key, fallback = '') {
  return process.env[key] || dotenv[key] || fallback;
}

const HOST = env('EXPRESSION_ENGINE_HOST', '127.0.0.1');
const PORT = parseInt(env('EXPRESSION_ENGINE_PORT', '3010'), 10);
const SECRET = env('EXPRESSION_ENGINE_SECRET', '');

if (!SECRET) {
  console.error('[expression-engine] EXPRESSION_ENGINE_SECRET is required.');
  process.exit(1);
}

// ---------------------------------------------------------------------------
// HTTP helpers
// ---------------------------------------------------------------------------
function sendJson(res, status, body) {
  const payload = JSON.stringify(body);
  res.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Content-Length': Buffer.byteLength(payload),
  });
  res.end(payload);
}

async function readBody(req, maxBytes = 65536) {
  return new Promise((resolveBody, reject) => {
    const chunks = [];
    let total = 0;
    req.on('data', (chunk) => {
      total += chunk.length;
      if (total > maxBytes) {
        reject(new Error('payload_too_large'));
        req.destroy();
        return;
      }
      chunks.push(chunk);
    });
    req.on('end', () => resolveBody(Buffer.concat(chunks).toString('utf-8')));
    req.on('error', reject);
  });
}

// ---------------------------------------------------------------------------
// Request handlers
// ---------------------------------------------------------------------------
function handleHealth(req, res) {
  sendJson(res, 200, { ok: true });
}

async function handleEvaluate(req, res) {
  if (req.headers['x-internal-secret'] !== SECRET) {
    sendJson(res, 403, { ok: false, error: { code: 'forbidden', message: 'invalid or missing secret' } });
    return;
  }

  let raw;
  try {
    raw = await readBody(req);
  } catch (e) {
    sendJson(res, 413, { ok: false, error: { code: 'payload_too_large', message: String(e?.message ?? e) } });
    return;
  }

  let body;
  try {
    body = JSON.parse(raw);
  } catch (e) {
    sendJson(res, 400, { ok: false, error: { code: 'bad_json', message: String(e?.message ?? e) } });
    return;
  }

  if (typeof body?.expression !== 'string' || body.expression.length === 0) {
    sendJson(res, 400, { ok: false, error: { code: 'bad_request', message: 'expression must be a non-empty string' } });
    return;
  }
  if (body.expression.length > 2000) {
    sendJson(res, 400, { ok: false, error: { code: 'expression_too_long', message: 'max 2000 chars' } });
    return;
  }

  const data = body.data && typeof body.data === 'object' && !Array.isArray(body.data) ? body.data : {};

  const result = evaluateExpression(body.expression, data);
  sendJson(res, 200, result);
}

// ---------------------------------------------------------------------------
// Server
// ---------------------------------------------------------------------------
const server = createServer(async (req, res) => {
  try {
    if (req.method === 'GET' && req.url === '/health') {
      handleHealth(req, res);
      return;
    }
    if (req.method === 'POST' && req.url === '/evaluate') {
      await handleEvaluate(req, res);
      return;
    }
    sendJson(res, 404, { ok: false, error: { code: 'not_found' } });
  } catch (e) {
    console.error('[expression-engine] handler crashed', e);
    sendJson(res, 500, { ok: false, error: { code: 'internal', message: 'handler crashed' } });
  }
});

server.listen(PORT, HOST, () => {
  console.log(`[expression-engine] listening on http://${HOST}:${PORT}`);
});

// Graceful shutdown so Kamal's SIGTERM doesn't leave half-served requests.
function shutdown() {
  console.log('[expression-engine] shutting down...');
  server.close(() => process.exit(0));
  setTimeout(() => process.exit(1), 5000).unref();
}
process.on('SIGTERM', shutdown);
process.on('SIGINT', shutdown);
