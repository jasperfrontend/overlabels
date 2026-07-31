import type { BuilderPlacement } from '@/types';

/**
 * Harvest the CSS class names actually present in a Builder composition, so the
 * Style panel can offer real targets instead of asking the user to guess.
 * Pure and dependency-free, like prefixCss.
 *
 * Classes come from the placed blocks' HTML (what ends up in the DOM), and are
 * marked `styled` when the same block's CSS has a rule for them. That split is
 * what keeps a Tailwind-authored block from burying the four class names worth
 * overriding under sixty utility classes.
 *
 * Deliberately NOT harvested:
 * - ids. The compiler puts `#blk-{instance}` on every wrapper, an element can
 *   only carry one id, and instance ids are noise to a user picking a target.
 * - classes built from tags (`class="text-[[[c:color]]]"`). Their real value
 *   only exists at render time, so there is nothing stable to target.
 */

export interface HarvestedClass {
    name: string;
    /** The block's own CSS styles this class, so overriding it needs the scope. */
    styled: boolean;
    /** Names of the blocks using it, deduped, in placement order. */
    blocks: string[];
    /** Emitted by the compiler rather than by any block. */
    structural?: boolean;
}

const CLASS_ATTR = /class\s*=\s*(?:"([^"]*)"|'([^']*)')/gi;
const CSS_CLASS_SELECTOR = /\.(-?[_a-zA-Z][\w-]*)/g;
const CSS_COMMENT = /\/\*[\s\S]*?\*\//g;
const CSS_STRING = /"[^"]*"|'[^']*'/g;
const CSS_URL = /url\([^)]*\)/gi;

/** Wrapper class the compiler puts on every placement (see composeBuilderTemplate). */
const STRUCTURAL: HarvestedClass = {
    name: 'builder-cell',
    styled: true,
    blocks: [],
    structural: true,
};

/**
 * Styled classes first (they need the scope to override), each group ordered by
 * how many blocks use it, then alphabetically. The structural wrapper class
 * leads, since "every block" is the most common thing to want to reach.
 */
export function harvestBuilderClasses(placements: BuilderPlacement[]): HarvestedClass[] {
    const found = new Map<string, { styled: boolean; blocks: string[] }>();

    for (const p of placements) {
        const styledHere = classSelectorsIn(p.snapshot.css ?? '');

        for (const name of classAttributesIn(p.snapshot.html ?? '')) {
            const entry = found.get(name) ?? { styled: false, blocks: [] };
            entry.styled = entry.styled || styledHere.has(name);
            if (!entry.blocks.includes(p.block_name)) entry.blocks.push(p.block_name);
            found.set(name, entry);
        }
    }

    const harvested = [...found.entries()]
        .map(([name, entry]) => ({ name, ...entry }))
        .sort(
            (a, b) =>
                Number(b.styled) - Number(a.styled) ||
                b.blocks.length - a.blocks.length ||
                a.name.localeCompare(b.name),
        );

    return placements.length ? [STRUCTURAL, ...harvested] : [];
}

/** Class tokens from every class attribute in a fragment of HTML. */
function classAttributesIn(html: string): Set<string> {
    const names = new Set<string>();

    for (const match of html.matchAll(CLASS_ATTR)) {
        const value = match[1] ?? match[2] ?? '';
        for (const token of value.split(/\s+/)) {
            // `[[[c:color]]]` inside a token means the class name is only known
            // at render time - there is nothing here to target.
            if (token && !token.includes('[[[')) names.add(token);
        }
    }

    return names;
}

/**
 * Class names appearing as selectors in a stylesheet. Strings, url() values and
 * comments are dropped first so `url(logo.png)` doesn't read as a `.png` class.
 * Over-matching is harmless anyway: the result is only intersected with classes
 * that really appear in the HTML.
 */
function classSelectorsIn(css: string): Set<string> {
    const cleaned = css.replace(CSS_COMMENT, ' ').replace(CSS_URL, ' ').replace(CSS_STRING, ' ');

    return new Set([...cleaned.matchAll(CSS_CLASS_SELECTOR)].map((m) => m[1]));
}
