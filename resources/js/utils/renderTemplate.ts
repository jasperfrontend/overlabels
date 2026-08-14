import { useConditionalTemplates } from '@/composables/useConditionalTemplates';
import { replaceTagsWithFormatting } from '@/utils/tagParser';

// useConditionalTemplates is a plain factory - no refs, no lifecycle hooks - so
// a single module-level instance is safe and avoids rebuilding its closures on
// every keystroke-driven preview re-render.
const { processTemplate } = useConditionalTemplates();

/**
 * The canonical two-pass template render.
 *
 * Mirrors OverlayRenderer's `parseSource()` so a preview cannot drift from what
 * OBS actually shows. Anything that renders template source outside the live
 * overlay should call this rather than substituting tags by hand.
 *
 * Pass 1 resolves conditional and foreach blocks, including the loop-scoped
 * `alias.*` / `loop.*` tokens whose lifetime is confined to a loop body.
 * Pass 2 is the single tag-substitution pass; substituted values are never
 * re-scanned for tags (see the SINGLE-PASS note in tagParser.ts).
 *
 * `encode` is true for HTML sinks and false for CSS, where entity-encoding
 * would corrupt selectors like `.a > .b`.
 */
export function renderTemplateSource(
  source: string | null | undefined,
  data: Record<string, unknown>,
  locale: string,
  encode: boolean = true,
): string {
  if (!source) return '';

  const withBlocks = processTemplate(source, data, { locale, encode });

  return replaceTagsWithFormatting(withBlocks, data, locale, encode);
}
