import { describe, expect, it } from 'vitest';
import { productMoves, type MoveFacts } from './productMoves';

function facts(over: Partial<MoveFacts> = {}): MoveFacts {
  return { stages: [], alerts: 0, hostLoaded: false, services: [], tests: [], landed: false, ...over };
}

type Service = MoveFacts['services'][number];

const kofi = (over: Partial<Service> = {}): Service => ({
  service: 'kofi',
  kind: 'token',
  connected: true,
  webhook_url: 'https://example.test/api/webhooks/kofi/abc',
  received: false,
  ...over,
});

const bmac = (over: Partial<Service> = {}): Service => ({
  service: 'bmac',
  kind: 'secret',
  connected: true,
  webhook_url: 'https://example.test/api/webhooks/bmac/abc',
  received: false,
  ...over,
});

const streamlabs = (over: Partial<Service> = {}): Service => ({
  service: 'streamlabs',
  kind: 'oauth',
  connected: true,
  webhook_url: null,
  received: false,
  ...over,
});

const throne = (over: Partial<Service> = {}): Service => ({
  service: 'throne',
  kind: 'none',
  connected: true,
  webhook_url: 'https://example.test/api/webhooks/throne/abc',
  received: false,
  ...over,
});

describe('productMoves', () => {
  it('gives a chat product one move, OBS, until a real link has loaded it', () => {
    const open = productMoves(facts({ stages: [{ loaded: false }] }));
    expect(open.moves).toEqual([{ key: 'obs', done: false }]);
    expect(open.current).toBe('obs');
    expect(open.pending).toBe(1);
    expect(open.paste).toEqual([]);

    const loaded = productMoves(facts({ stages: [{ loaded: true }] }));
    expect(loaded.current).toBeNull();
    expect(loaded.pending).toBe(0);
  });

  it('needs every stage loaded before the OBS move is done', () => {
    expect(productMoves(facts({ stages: [{ loaded: true }, { loaded: false }] })).current).toBe('obs');
  });

  it('orders an alert product with a service still to connect: services, then OBS, then the test tip', () => {
    const result = productMoves(facts({ alerts: 1, services: [kofi({ connected: false })], tests: ['kofi'] }));
    expect(result.moves.map((move) => move.key)).toEqual(['services', 'obs', 'test:kofi']);
    expect(result.current).toBe('services');
    expect(result.pending).toBe(3);
  });

  it('has no services move once every service is connected, so the glow goes to OBS', () => {
    const result = productMoves(facts({ alerts: 1, services: [kofi()], tests: ['kofi'] }));
    expect(result.moves.map((move) => move.key)).toEqual(['obs', 'test:kofi']);
    expect(result.current).toBe('obs');
  });

  it('folds an unproven Ko-fi or Throne link into the test tip, which is the proof of the paste', () => {
    expect(productMoves(facts({ alerts: 1, services: [kofi()], tests: ['kofi'] })).paste).toEqual(['kofi']);
    expect(productMoves(facts({ alerts: 1, services: [throne()], tests: ['throne'] })).paste).toEqual(['throne']);
  });

  it('makes the test tip current once OBS is done, even while the link is unproven', () => {
    const result = productMoves(facts({ alerts: 1, services: [kofi()], tests: ['kofi'], hostLoaded: true }));
    expect(result.current).toBe('test:kofi');
    expect(result.paste).toEqual(['kofi']);
  });

  it('counts a connected Buy Me a Coffee as pasted: its secret only exists once our link is saved there', () => {
    const result = productMoves(facts({ alerts: 1, services: [bmac()], tests: ['bmac'] }));
    expect(result.moves.map((move) => move.key)).toEqual(['obs', 'test:bmac']);
    expect(result.paste).toEqual([]);
  });

  it('never asks for a paste from an OAuth service, which carries no link', () => {
    expect(productMoves(facts({ alerts: 1, services: [streamlabs()], tests: ['streamlabs'] })).paste).toEqual([]);
  });

  it('stops asking for the paste once the link has been used, or a tip has landed', () => {
    expect(productMoves(facts({ alerts: 1, services: [kofi({ received: true })], tests: ['kofi'] })).paste).toEqual([]);
    expect(productMoves(facts({ alerts: 1, services: [kofi()], tests: ['kofi'], landed: true })).paste).toEqual([]);
  });

  it('never folds a service that is not connected into the test tip: it has its own move', () => {
    const result = productMoves(facts({ alerts: 1, services: [kofi({ connected: false, webhook_url: null })] }));
    expect(result.moves[0]).toEqual({ key: 'services', done: false });
    expect(result.paste).toEqual([]);
  });

  it('lets a landed tip finish the test, leaving only an unloaded overlay', () => {
    const result = productMoves(facts({ alerts: 1, services: [kofi()], tests: ['kofi'], landed: true }));
    expect(result.moves).toEqual([
      { key: 'obs', done: false },
      { key: 'test:kofi', done: true },
    ]);
    expect(result.current).toBe('obs');
    expect(result.pending).toBe(1);
  });

  it("is done for an alert product once a link serves one of the person's overlays and a tip lands", () => {
    const result = productMoves(facts({ alerts: 1, services: [kofi()], tests: ['kofi'], hostLoaded: true, landed: true }));
    expect(result.current).toBeNull();
  });

  it('keeps the OBS move open for an alert product with no overlay of its own and none served', () => {
    expect(productMoves(facts({ alerts: 1, tests: ['kofi'] })).moves).toContainEqual({ key: 'obs', done: false });
  });

  it('still listens for a tip when the alert has no test guide', () => {
    expect(productMoves(facts({ alerts: 1, hostLoaded: true })).moves).toEqual([
      { key: 'obs', done: true },
      { key: 'watch', done: false },
    ]);
  });

  it('has no moves for a product with neither a stage nor an alert', () => {
    expect(productMoves(facts())).toEqual({ moves: [], current: null, pending: 0, paste: [] });
  });
});
