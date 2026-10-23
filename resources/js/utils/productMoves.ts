/**
 * What is left on a finished product page, as a short list of moves.
 *
 * "Finished" means the setup flow has nothing left: the bot is on, the
 * integration is connected, the overlay exists. What remains happens outside
 * Overlabels - the overlay going into OBS, the service being given our link,
 * a test tip being sent - and the wiring cannot see any of it, which is why
 * these are not wires and never reach the setup flow. They are page state.
 *
 * Exactly one move is current: the first one not done. The page lights only
 * that one, and the arrival from the setup flow scrolls only to it, so the
 * page never has two things asking for attention. A done move is a tick row,
 * not a card, and nothing is numbered: a lone "1" with nothing to count is
 * what this replaced.
 *
 * Pure on purpose. The rules for what the page may claim (an overlay is only
 * "loaded" when the server saw a real link load it; a tip only "landed" when
 * one did) would otherwise live in template conditions nothing can test.
 */

export type MoveKey = 'services' | 'obs' | 'watch' | `test:${string}`;

export interface Move {
  key: MoveKey;
  done: boolean;
}

export interface MoveFacts {
  /** One entry per overlay the product installed for OBS, with whether a real link has ever loaded it. */
  stages: { loaded: boolean }[];
  /** How many alerts the product installed. An alert never goes into OBS itself. */
  alerts: number;
  /** For an alert-only product: an overlay link has served one of the person's overlays lately. */
  hostLoaded: boolean;
  /** Every donation service the alerts fire on, as ServiceConnections builds it. */
  services: { service: string; kind: 'oauth' | 'token' | 'secret' | 'none'; connected: boolean; webhook_url: string | null; received: boolean }[];
  /** The service key of every test-tip guide the page shows, in page order. */
  tests: string[];
  /** A tip the alert fires on has arrived since the install, from the server or the live broadcast. */
  landed: boolean;
}

export interface Moves {
  moves: Move[];
  current: MoveKey | null;
  pending: number;
  /**
   * Connected services whose link may still need pasting on their side. The
   * page shows that paste inside the test-tip move, not as a move of its own.
   */
  paste: string[];
}

export function productMoves(facts: MoveFacts): Moves {
  const moves: Move[] = [];
  const alerting = facts.alerts > 0;

  // Connecting is a move only while a service is not connected; that is the
  // one part of the services the person can finish on its own card.
  //
  // Pasting our link on the service's side is not a move of its own: the
  // only thing that can prove it happened is the service's first event, and
  // that is the test tip. A paste card would stay lit after the paste with
  // nothing able to tick it, and pull the glow back up past the OBS move. So
  // a connected service whose link is unproven rides on the test-tip move.
  // The OAuth pair carries no link, and a secret-kind service (Buy Me a
  // Coffee) can only be connected with the secret the service reveals once
  // our link is saved there, so being connected already proves the paste.
  if (alerting && facts.services.some((service) => !service.connected)) {
    moves.push({ key: 'services', done: false });
  }

  const paste =
    alerting && !facts.landed
      ? facts.services
          .filter((service) => service.connected && service.webhook_url !== null && service.kind !== 'secret' && !service.received)
          .map((service) => service.service)
      : [];

  // A product's own stage goes into OBS. An alert-only product has none and
  // shows inside an overlay the person already has there, so its OBS move is
  // done when a link has served one of those.
  if (facts.stages.length) {
    moves.push({ key: 'obs', done: facts.stages.every((stage) => stage.loaded) });
  } else if (alerting) {
    moves.push({ key: 'obs', done: facts.hostLoaded });
  }

  // The test tip, once per guide. An alert with no guide still gets the
  // waiting line, so the page says it is listening.
  if (alerting) {
    if (facts.tests.length) {
      for (const service of facts.tests) moves.push({ key: `test:${service}`, done: facts.landed });
    } else {
      moves.push({ key: 'watch', done: facts.landed });
    }
  }

  const current = moves.find((move) => !move.done)?.key ?? null;

  return { moves, current, pending: moves.filter((move) => !move.done).length, paste };
}
