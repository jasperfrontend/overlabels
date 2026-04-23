/**
 * Browser-side Tailwind-compatible CSS compiler backed by UnoCSS.
 *
 * Scans authored template HTML (+ head + css) for utility class names and emits
 * only the rules that are actually used. The generated CSS is persisted on the
 * overlay template and injected at render time, replacing the full-CDN Tailwind
 * payload pattern that was making alert templates slow to paint.
 *
 * UnoCSS is lazy-imported so the editor pages pay the cost, not the overlay
 * renderer bundle.
 */

type UnoGenerator = {
  generate: (input: string, options?: { minify?: boolean }) => Promise<{ css: string }>;
};

let generatorPromise: Promise<UnoGenerator> | null = null;

async function getGenerator(): Promise<UnoGenerator> {
  if (!generatorPromise) {
    generatorPromise = (async () => {
      const [{ createGenerator }, { presetWind3 }] = await Promise.all([
        import('@unocss/core'),
        import('@unocss/preset-wind3'),
      ]);
      return createGenerator({ presets: [presetWind3()] }) as unknown as UnoGenerator;
    })();
  }
  return generatorPromise;
}

/**
 * Compile the authored template sources into minimal utility CSS.
 *
 * Returns the empty string on any failure so callers can save a template even
 * if the compiler trips on something unusual (the user's hand-written `css`
 * field still works). Errors are logged but not thrown.
 */
export async function compileTailwindCss(sources: {
  html?: string;
  head?: string;
  css?: string;
}): Promise<string> {
  const input = [sources.html ?? '', sources.head ?? '', sources.css ?? '']
    .filter(Boolean)
    .join('\n');

  if (!input.trim()) return '';

  try {
    const uno = await getGenerator();
    const { css } = await uno.generate(input, { minify: true });
    return css ?? '';
  } catch (err) {
    console.error('[compileTailwindCss] UnoCSS generation failed:', err);
    return '';
  }
}
