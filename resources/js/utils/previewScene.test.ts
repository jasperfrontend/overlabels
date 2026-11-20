import { expandScene } from '@/utils/previewScene';
import { blocksFromData, withTowerSlots } from '@/utils/towerSlots';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const NOW = 1_800_000_000;

describe('expandScene', () => {
  it('stacks a tower the way the server would: cleared first, then one block per entry', () => {
    const steps = expandScene(
      [
        {
          event: 'tower.stack',
          blocks: [
            { name: 'QuietFox', color: '#f97316', offset: 0.3 },
            { name: 'pixelmoth', offset: -0.5 },
            { name: 'noodlebyte', offset: 0.4, record: true },
          ],
        },
      ],
      NOW,
    );

    expect(steps).toHaveLength(4);
    expect(steps[0]).toEqual({ kind: 'tower', payload: { cleared: true, height: 0, block: null, toppled: null } });
    expect(steps[1].payload).toMatchObject({ cleared: false, height: 1, block: { name: 'QuietFox', login: 'quietfox', position: '1', x: '0.3' } });
    // x is the running sum of offsets: the lean of the tower up to that block.
    expect(steps[2].payload).toMatchObject({ height: 2, block: { position: '2', x: '-0.2', color: '' } });
    expect(steps[3].payload).toMatchObject({ height: 3, block: { position: '3', x: '0.2', record: '1', at: String(NOW) } });
  });

  it('builds blocks the tower slots read back unchanged', () => {
    // The blocks go to handleTowerUpdated, which keeps them through toBlock and
    // the tower.N.* slots. A field the slots drop would be a block the preview
    // never draws.
    const steps = expandScene(
      [
        {
          event: 'tower.stack',
          blocks: [
            { name: 'a', offset: 1 },
            { name: 'b', offset: 1 },
          ],
        },
      ],
      NOW,
    );
    const blocks = steps.slice(1).map((step) => (step.payload as Record<string, unknown>).block as Record<string, string>);

    expect(blocksFromData(withTowerSlots({}, blocks as never, 2))).toEqual(blocks);
  });

  it('topples one more block on top of the stack, naming whoever placed it', () => {
    const steps = expandScene(
      [
        {
          event: 'tower.stack',
          blocks: [
            { name: 'a', offset: 1 },
            { name: 'b', offset: 1.5 },
          ],
        },
        { event: 'tower.topple', name: 'Culprit', offset: 1.6 },
      ],
      NOW,
    );

    expect(steps.at(-1)).toEqual({
      kind: 'tower',
      payload: {
        cleared: true,
        height: 0,
        block: { name: 'Culprit', login: 'culprit', color: '', position: '3', offset: '1.6', x: '4.1', record: '', at: String(NOW) },
        toppled: { by: 'Culprit', login: 'culprit', height: 3, record_tower: false },
      },
    });
  });

  it('applies a control the way an account-wide broadcast does', () => {
    expect(expandScene([{ event: 'control.updated', key: 'tower:tower_room', value: 0.4 }], NOW)).toEqual([
      { kind: 'control', payload: { key: 'tower:tower_room', value: '0.4', overlay_slug: '' } },
    ]);
  });

  it('stamps a list removal with the moment the scene plays', () => {
    const [step] = expandScene([{ event: 'list.updated', slug: 'lane', items: ['a', 'b'], last_removed: 'c', last_removed_at: 'now' }], NOW);

    expect(step).toEqual({ kind: 'list', payload: { slug: 'lane', items: ['a', 'b'], last_removed: 'c', last_removed_at: NOW } });
  });

  it('skips what it cannot read instead of throwing inside the overlay', () => {
    expect(expandScene('nope', NOW)).toEqual([]);
    expect(
      expandScene(
        [
          null,
          { event: 'mystery' },
          { event: 'control.updated' },
          { event: 'tower.topple', name: '', offset: 1 },
          { event: 'list.updated', slug: 'x' },
        ],
        NOW,
      ),
    ).toEqual([]);
  });
});

type ManifestScene = { key: string; events: Record<string, unknown>[] };

function manifestScenes(slug: string): ManifestScene[] {
  const manifest = JSON.parse(readFileSync(resolve(__dirname, `../../recipes/${slug}/manifest.json`), 'utf8'));
  return manifest.designer.scenes;
}

describe('the scenes the products ship', () => {
  it.each(['chat-tower', 'follower-bowling'])('%s: every event is one the frame can play', (slug) => {
    for (const scene of manifestScenes(slug)) {
      for (const event of scene.events) {
        expect(expandScene([event], NOW), `${scene.key}: ${String(event.event)}`).not.toEqual([]);
      }
    }
  });

  it('Chat Tower: each moment is in the danger level its name promises', () => {
    // c:danger in tower.md: room under 0.8 is 2, under 1.8 is 1, else 0. Room
    // below 0 is a tower that would already have fallen.
    const danger = (room: number) => (room < 0.8 ? 2 : room < 1.8 ? 1 : 0);
    const rooms = Object.fromEntries(
      manifestScenes('chat-tower').map((scene) => {
        const room = scene.events.filter((e) => e.event === 'control.updated' && e.key === 'tower:tower_room').at(0)?.value;
        return [scene.key, Number(room)];
      }),
    );

    expect(danger(rooms.standing)).toBe(0);
    expect(danger(rooms.close)).toBe(1);
    expect(danger(rooms.about)).toBe(2);
    expect(rooms.about).toBeGreaterThan(0);
    expect(danger(rooms.record)).toBe(0);
  });
});
