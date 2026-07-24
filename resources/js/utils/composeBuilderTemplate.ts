import type { BuilderMetadata, BuilderPlacement } from '@/types';
import { prefixCss } from '@/utils/prefixCss';

/**
 * Compile a Builder state (grid + block placements) into the plain
 * head/html/css of a regular static overlay. Pure and deterministic: the same
 * state always emits the same strings, so re-saving an untouched composition
 * is a no-op diff.
 *
 * The output is what the render pipeline consumes - the Builder metadata is
 * provenance for re-editing, never dereferenced at render time.
 */
export function composeBuilderTemplate(state: BuilderMetadata): { head: string; html: string; css: string } {
    const { grid, canvas, placements } = state;

    const html = [
        '<div id="builder-root">',
        ...placements.map(
            (p) => `  <div id="blk-${p.instance_id}" class="builder-cell">\n${p.snapshot.html}\n  </div>`,
        ),
        '</div>',
    ].join('\n');

    const cssParts: string[] = [
        // OBS browser sources want a transparent full-canvas page.
        'html, body { margin: 0; padding: 0; background: transparent; }',
        [
            '#builder-root {',
            '  position: fixed;',
            '  inset: 0;',
            `  width: ${canvas.width}px;`,
            `  height: ${canvas.height}px;`,
            '  display: grid;',
            `  grid-template-columns: repeat(${grid.cols}, 1fr);`,
            `  grid-template-rows: repeat(${grid.rows}, 1fr);`,
            `  gap: ${grid.gap}px;`,
            '}',
        ].join('\n'),
    ];

    for (const p of placements) {
        cssParts.push(
            `#blk-${p.instance_id} { grid-area: ${p.y} / ${p.x} / span ${p.h} / span ${p.w}; position: relative; overflow: hidden; }`,
        );
        const scoped = prefixCss(p.snapshot.css ?? '', `#blk-${p.instance_id}`);
        if (scoped) cssParts.push(scoped);
    }

    return {
        head: dedupeHead(placements),
        html,
        css: cssParts.join('\n\n'),
    };
}

/**
 * Concatenate placement heads, dropping exact duplicates (normalized by
 * whitespace) so ten instances of one block emit its font link once. Two
 * semantically equal but differently formatted tags both survive - harmless.
 */
function dedupeHead(placements: BuilderPlacement[]): string {
    const seen = new Set<string>();
    const parts: string[] = [];

    for (const p of placements) {
        const head = (p.snapshot.head ?? '').trim();
        if (!head) continue;
        const normalized = head.replace(/\s+/g, ' ');
        if (seen.has(normalized)) continue;
        seen.add(normalized);
        parts.push(head);
    }

    return parts.join('\n');
}
