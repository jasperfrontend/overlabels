/**
 * The Plinko board: a canvas inside the `[[[plinko_board]]]` placeholder,
 * fed one drop per `!drop` the overlay reads from Twitch chat.
 *
 * Loaded as a lazy chunk - it only ever downloads when a template contains
 * the tag (the emote-library discipline). Everything runs in the overlay:
 * nothing is sent anywhere, so chat volume costs Overlabels nothing.
 *
 * ORDER. Drops are played in the order chat delivered them. One ball per
 * chatter at a time (queued or falling); a repeat `!drop` while yours is
 * still in play is ignored. Balls are released at a steady rate up to a cap
 * on balls in flight, and everyone beyond that waits in line, in order. That
 * is what keeps a 20,000-person chat a game rather than a smear.
 *
 * The command word is the streamer's setting (/settings/integrations/plinko), not styling:
 * it arrives with the render payload and the renderer does the matching, so
 * this module only ever sees drops.
 *
 * Styling is CSS: the module reads custom properties off the placeholder,
 * and the scoreboard is ordinary DOM the template's own CSS styles.
 * Recognised properties, all optional:
 *
 *   --plinko-rows        rows of pegs, 4-24                  (default 14)
 *   --plinko-max-balls   balls in flight at once             (default 150)
 *   --plinko-rate        balls released per second           (default 10)
 *   --plinko-peg-color   resting peg colour                  (default #a78bfa)
 *   --plinko-accent      lit pegs, landing flash             (default #f472b6)
 *   --plinko-ball-color  for chatters with no name colour    (default #f472b6)
 *   --plinko-label-bg    name tag background                 (default #120720)
 *   --plinko-label-color name tag text                       (default #fafafa)
 *   --plinko-slot-color  slot numbers and dividers           (default #a78bfa)
 *   --plinko-font        font family for tags and slots      (default the page's)
 *
 * Scoreboard DOM (class names are the styling contract):
 *   .ol-plinko-hud
 *     .ol-plinko-count   <b>in play</b> and <b>waiting</b>
 *     .ol-plinko-next    ol of the next names in line
 *     .ol-plinko-recent  ol of the latest landings (name + points)
 *     .ol-plinko-top     ol of the best totals this session
 * Every name is set with textContent; nothing from chat is ever parsed as HTML.
 */

import {
  type Ball,
  type BoardGeometry,
  boardGeometry,
  collideBalls,
  pegCount,
  pegPosition,
  slotCenter,
  slotCount,
  slotPoints,
  spawnBall,
  STEP,
  stepBall,
} from './plinkoPhysics';

export interface PlinkoDrop {
  /** Twitch's message id: what a CLEARMSG deletes by. */
  id: string;
  userId: string;
  login: string;
  name: string;
  /** `#rrggbb`, or empty when the chatter never chose one. */
  color: string;
}

export type PlinkoModeration =
  | { type: 'delete_message'; messageId: string }
  | { type: 'purge_user'; userId: string }
  | { type: 'purge_login'; login: string }
  | { type: 'clear_all' };

export interface PlinkoInstance {
  drop(entry: PlinkoDrop): void;
  moderate(action: PlinkoModeration): void;
  destroy(): void;
}

interface Config {
  rows: number;
  maxBalls: number;
  rate: number;
  pegColor: string;
  accent: string;
  ballColor: string;
  labelBg: string;
  labelColor: string;
  slotColor: string;
  font: string;
}

interface Flight {
  drop: PlinkoDrop;
  ball: Ball;
  bornAt: number;
  scored: boolean;
}

interface Score {
  name: string;
  userId: string;
  total: number;
}

interface Landing {
  name: string;
  login: string;
  userId: string;
  points: number;
}

/** Most entries the line will hold; past that, a drop is ignored rather than eating memory. */
const QUEUE_CAP = 100_000;
/** Seconds a landed ball stays in its bin before it fades out. */
const LANDED_HOLD = 1.6;
/** How often the scoreboard DOM is rewritten, at most. */
const HUD_INTERVAL_MS = 250;
/** Ignore a frame gap longer than this (a hidden tab) instead of fast-forwarding through it. */
const MAX_FRAME_GAP = 0.1;
const NEXT_SHOWN = 5;
const RECENT_SHOWN = 6;
const TOP_SHOWN = 5;

function readConfig(el: HTMLElement): Config {
  const style = getComputedStyle(el);
  const prop = (name: string, fallback: string): string => {
    const value = style
      .getPropertyValue(name)
      .trim()
      .replace(/^["']|["']$/g, '');
    return value === '' ? fallback : value;
  };
  const num = (name: string, fallback: number, min: number, max: number): number => {
    const value = Number.parseFloat(prop(name, ''));
    return Number.isFinite(value) ? Math.max(min, Math.min(max, value)) : fallback;
  };

  return {
    rows: Math.round(num('--plinko-rows', 14, 4, 24)),
    maxBalls: Math.round(num('--plinko-max-balls', 150, 1, 1000)),
    rate: num('--plinko-rate', 10, 0.5, 120),
    pegColor: prop('--plinko-peg-color', '#a78bfa'),
    accent: prop('--plinko-accent', '#f472b6'),
    ballColor: prop('--plinko-ball-color', '#f472b6'),
    labelBg: prop('--plinko-label-bg', '#120720'),
    labelColor: prop('--plinko-label-color', '#fafafa'),
    slotColor: prop('--plinko-slot-color', '#a78bfa'),
    font: prop('--plinko-font', style.fontFamily || 'system-ui, sans-serif'),
  };
}

function list(className: string): HTMLOListElement {
  const el = document.createElement('ol');
  el.className = className;
  return el;
}

function row(name: string, value?: string): HTMLLIElement {
  const li = document.createElement('li');
  const nameEl = document.createElement('span');
  nameEl.className = 'ol-plinko-name';
  nameEl.textContent = name;
  li.append(nameEl);
  if (value !== undefined) {
    const valueEl = document.createElement('span');
    valueEl.className = 'ol-plinko-points';
    valueEl.textContent = value;
    li.append(valueEl);
  }
  return li;
}

export function mountPlinkoBoard(el: HTMLElement): PlinkoInstance {
  const config = readConfig(el);

  if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
  const canvas = document.createElement('canvas');
  canvas.className = 'ol-plinko-canvas';
  canvas.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;display:block;';
  const ctx = canvas.getContext('2d');

  const hud = document.createElement('div');
  hud.className = 'ol-plinko-hud';
  const countEl = document.createElement('div');
  countEl.className = 'ol-plinko-count';
  const nextEl = list('ol-plinko-next');
  const recentEl = list('ol-plinko-recent');
  const topEl = list('ol-plinko-top');
  hud.append(countEl, nextEl, recentEl, topEl);
  el.append(canvas, hud);

  let geometry: BoardGeometry = boardGeometry(1, 1, config.rows);
  let dpr = 1;

  function resize(): void {
    const width = el.clientWidth;
    const height = el.clientHeight;
    dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.round(width * dpr));
    canvas.height = Math.max(1, Math.round(height * dpr));
    geometry = boardGeometry(Math.max(1, width), Math.max(1, height), config.rows);
  }
  resize();
  const observer = new ResizeObserver(resize);
  observer.observe(el);

  // ── state ──────────────────────────────────────────────────────────────────
  const queue: PlinkoDrop[] = [];
  let queueHead = 0; // index of the next entry; the array is compacted now and then
  const flights: Flight[] = [];
  /** Logins with a ball queued or in play: one at a time each. */
  const active = new Set<string>();
  const scores = new Map<string, Score>();
  const recent: Landing[] = [];
  /** Peg key "row:index" -> clock time of its last hit, for the glow. */
  const pegHits = new Map<string, number>();
  /** Slot -> clock time of its last landing, for the flash. */
  const slotHits = new Map<number, number>();

  let clock = 0;
  let accumulator = 0;
  let releaseDebt = 0;
  let lastFrame: number | null = null;
  let raf = 0;
  let hudDirty = true;
  let lastHud = 0;
  let destroyed = false;

  function waiting(): number {
    return queue.length - queueHead;
  }

  function drop(entry: PlinkoDrop): void {
    if (destroyed) return;
    const login = entry.login.toLowerCase();
    if (active.has(login) || waiting() >= QUEUE_CAP) return;
    active.add(login);
    queue.push({ ...entry, login });
    hudDirty = true;
  }

  function release(): void {
    const entry = queue[queueHead++];
    if (queueHead > 4096 && queueHead * 2 > queue.length) {
      queue.splice(0, queueHead);
      queueHead = 0;
    }
    flights.push({ drop: entry, ball: spawnBall(geometry, entry.id), bornAt: clock, scored: false });
  }

  function forget(match: (d: PlinkoDrop) => boolean, scoresToo: boolean): void {
    for (let i = flights.length - 1; i >= 0; i--) {
      if (match(flights[i].drop)) {
        active.delete(flights[i].drop.login);
        flights.splice(i, 1);
      }
    }
    const kept: PlinkoDrop[] = [];
    for (let i = queueHead; i < queue.length; i++) {
      if (match(queue[i])) active.delete(queue[i].login);
      else kept.push(queue[i]);
    }
    queue.length = 0;
    queue.push(...kept);
    queueHead = 0;
    if (scoresToo) {
      for (const [login, score] of scores) {
        if (match({ id: '', userId: score.userId, login, name: score.name, color: '' })) scores.delete(login);
      }
      for (let i = recent.length - 1; i >= 0; i--) {
        if (match({ id: '', userId: recent[i].userId, login: recent[i].login, name: recent[i].name, color: '' })) recent.splice(i, 1);
      }
    }
    hudDirty = true;
  }

  // Moderation is honoured whatever the template wants: a chatter who was
  // removed from chat does not keep a ball, or a name, on the stream.
  function moderate(action: PlinkoModeration): void {
    switch (action.type) {
      case 'delete_message':
        // One deleted message takes back that ball, not the chatter's score.
        forget((d) => d.id === action.messageId, false);
        return;
      case 'purge_user':
        forget((d) => d.userId === action.userId, true);
        return;
      case 'purge_login':
        forget((d) => d.login === action.login.toLowerCase(), true);
        return;
      case 'clear_all':
        forget(() => true, true);
        return;
    }
  }

  function land(flight: Flight): void {
    flight.scored = true;
    const slot = flight.ball.slot ?? 0;
    const points = slotPoints(geometry.rows, slot);
    slotHits.set(slot, clock);
    const score = scores.get(flight.drop.login) ?? { name: flight.drop.name, userId: flight.drop.userId, total: 0 };
    score.name = flight.drop.name;
    score.total += points;
    scores.set(flight.drop.login, score);
    recent.unshift({ name: flight.drop.name, login: flight.drop.login, userId: flight.drop.userId, points });
    if (recent.length > RECENT_SHOWN) recent.length = RECENT_SHOWN;
    hudDirty = true;
  }

  // ── simulation ─────────────────────────────────────────────────────────────
  function simulate(dt: number): void {
    releaseDebt = Math.min(releaseDebt + dt * config.rate, config.rate);
    while (releaseDebt >= 1 && waiting() > 0 && flights.length < config.maxBalls) {
      release();
      releaseDebt -= 1;
      hudDirty = true;
    }
    if (waiting() === 0) releaseDebt = Math.min(releaseDebt, 1);

    accumulator += dt;
    while (accumulator >= STEP) {
      accumulator -= STEP;
      clock += STEP;
      for (const flight of flights) {
        for (const hit of stepBall(geometry, flight.ball, clock - flight.bornAt)) {
          pegHits.set(`${hit.row}:${hit.index}`, clock);
        }
        if (flight.ball.landedAt !== null && !flight.scored) land(flight);
      }
      collideBalls(
        geometry,
        flights.map((f) => f.ball),
      );
    }

    for (let i = flights.length - 1; i >= 0; i--) {
      const flight = flights[i];
      if (flight.ball.landedAt !== null && clock - flight.bornAt - flight.ball.landedAt > LANDED_HOLD) {
        active.delete(flight.drop.login);
        flights.splice(i, 1);
        hudDirty = true;
      }
    }
  }

  // ── drawing ────────────────────────────────────────────────────────────────
  function glow(since: number | undefined, fade: number): number {
    if (since === undefined) return 0;
    return Math.max(0, 1 - (clock - since) / fade);
  }

  function draw(): void {
    if (!ctx) return;
    const g = geometry;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, g.width, g.height);

    // Pegs.
    for (let r = 0; r < g.rows; r++) {
      for (let i = 0; i < pegCount(r); i++) {
        const peg = pegPosition(g, r, i);
        const lit = glow(pegHits.get(`${r}:${i}`), 0.35);
        ctx.globalAlpha = 0.6 + 0.4 * lit;
        ctx.fillStyle = lit > 0 ? config.accent : config.pegColor;
        ctx.beginPath();
        ctx.arc(peg.x, peg.y, g.pegRadius * (1 + lit * 0.6), 0, Math.PI * 2);
        ctx.fill();
      }
    }
    ctx.globalAlpha = 1;

    // Bin dividers and slot values.
    ctx.strokeStyle = config.slotColor;
    ctx.globalAlpha = 0.45;
    ctx.lineWidth = 2;
    for (let k = 0; k <= slotCount(g); k++) {
      const x = slotCenter(g, k) - g.spacing / 2;
      ctx.beginPath();
      ctx.moveTo(x, g.binTop);
      ctx.lineTo(x, g.binFloor);
      ctx.stroke();
    }
    ctx.globalAlpha = 1;
    const slotFont = Math.max(9, Math.round(g.spacing * 0.27));
    ctx.font = `600 ${slotFont}px ${config.font}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';
    for (let k = 0; k < slotCount(g); k++) {
      const flash = glow(slotHits.get(k), 1.2);
      const x = slotCenter(g, k);
      if (flash > 0) {
        ctx.globalAlpha = flash * 0.35;
        ctx.fillStyle = config.accent;
        ctx.fillRect(x - g.spacing / 2 + 2, g.binTop, g.spacing - 4, g.binFloor - g.binTop);
        ctx.globalAlpha = 1;
      }
      ctx.fillStyle = flash > 0 ? config.accent : config.slotColor;
      ctx.fillText(String(slotPoints(g.rows, k)), x, g.binFloor + g.spacing * 0.2);
    }

    // Balls, oldest first so the newest name sits on top.
    const r = g.ballRadius;
    for (const flight of flights) {
      const { ball } = flight;
      const fade = ball.landedAt === null ? 1 : Math.max(0, 1 - (clock - flight.bornAt - ball.landedAt) / LANDED_HOLD);
      ctx.globalAlpha = fade;
      ctx.fillStyle = flight.drop.color || config.ballColor;
      ctx.beginPath();
      ctx.arc(ball.x, ball.y, r, 0, Math.PI * 2);
      ctx.fill();
      ctx.lineWidth = Math.max(1.5, r * 0.18);
      ctx.strokeStyle = config.labelColor;
      ctx.stroke();
    }

    // Name tags: one per ball, never wrapped or cut, whatever the name.
    const labelFont = Math.max(11, Math.round(g.spacing * 0.3));
    ctx.font = `700 ${labelFont}px ${config.font}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const padX = labelFont * 0.5;
    const h = labelFont * 1.6;
    for (const flight of flights) {
      const { ball } = flight;
      const fade = ball.landedAt === null ? 1 : Math.max(0, 1 - (clock - flight.bornAt - ball.landedAt) / LANDED_HOLD);
      const w = ctx.measureText(flight.drop.name).width + padX * 2;
      const cy = ball.y - r - h / 2 - labelFont * 0.45;
      ctx.globalAlpha = fade * 0.92;
      ctx.fillStyle = config.labelBg;
      ctx.beginPath();
      ctx.roundRect(ball.x - w / 2, cy - h / 2, w, h, h / 2);
      ctx.fill();
      // The little pointer down to the ball.
      ctx.beginPath();
      ctx.moveTo(ball.x - labelFont * 0.35, cy + h / 2 - 0.5);
      ctx.lineTo(ball.x + labelFont * 0.35, cy + h / 2 - 0.5);
      ctx.lineTo(ball.x, cy + h / 2 + labelFont * 0.4);
      ctx.fill();
      ctx.globalAlpha = fade;
      ctx.fillStyle = config.labelColor;
      ctx.fillText(flight.drop.name, ball.x, cy + 0.5);
    }
    ctx.globalAlpha = 1;
  }

  // ── scoreboard ─────────────────────────────────────────────────────────────
  function renderHud(): void {
    const fmt = (n: number) => n.toLocaleString();
    const inPlay = document.createElement('b');
    inPlay.textContent = fmt(flights.length);
    const inLine = document.createElement('b');
    inLine.textContent = fmt(waiting());
    countEl.replaceChildren(inPlay, ' in play · ', inLine, ' waiting');

    const next: HTMLLIElement[] = [];
    for (let i = queueHead; i < queue.length && next.length < NEXT_SHOWN; i++) next.push(row(queue[i].name));
    nextEl.replaceChildren(...next);

    recentEl.replaceChildren(...recent.map((r) => row(r.name, fmt(r.points))));

    const top = [...scores.values()].sort((a, b) => b.total - a.total).slice(0, TOP_SHOWN);
    topEl.replaceChildren(...top.map((s) => row(s.name, fmt(s.total))));
  }

  function frame(now: number): void {
    if (destroyed) return;
    const dt = lastFrame === null ? 0 : Math.min(MAX_FRAME_GAP, (now - lastFrame) / 1000);
    lastFrame = now;
    simulate(dt);
    draw();
    if (hudDirty && now - lastHud >= HUD_INTERVAL_MS) {
      hudDirty = false;
      lastHud = now;
      renderHud();
    }
    raf = requestAnimationFrame(frame);
  }
  raf = requestAnimationFrame(frame);

  return {
    drop,
    moderate,
    destroy(): void {
      destroyed = true;
      cancelAnimationFrame(raf);
      observer.disconnect();
      canvas.remove();
      hud.remove();
    },
  };
}
