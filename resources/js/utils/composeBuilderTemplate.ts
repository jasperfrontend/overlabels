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
/** Wrapper the grid lives on, and the scope every overlay-level override gets. */
export const BUILDER_ROOT = '#builder-root';

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

    // The user's own CSS goes last so it wins ties on source order, and is
    // scoped to the grid root so it can actually TIE: a block's `.value` rule
    // compiles to `#blk-a3f2 .value` (1,1,0), which a bare `.value` (0,1,0)
    // could never beat no matter how far down the file it sat. Scoped to
    // `#builder-root .value` it matches that specificity and wins on order.
    const custom = prefixCss(state.custom_css ?? '', BUILDER_ROOT);
    if (custom) cssParts.push(`/* Your CSS */\n${custom}`);

    return {
        head: composeHead(placements, state.custom_head ?? ''),
        html,
        css: cssParts.join('\n\n'),
    };
}

/**
 * Block heads first (deduped), the user's own head last. The user's is never
 * deduped against the blocks': if they want to re-declare a font the blocks
 * already pull in, that is their call and later wins anyway.
 */
function composeHead(placements: BuilderPlacement[], customHead: string): string {
    const parts = [dedupeHead(placements), customHead.trim()].filter(Boolean);

    return parts.join('\n');
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
