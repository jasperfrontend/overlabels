/**
 * CSS custom-property applier for the static overlay (Phase 1 of surgical
 * patching). Pairs with `compileCssBindings()` in `@/utils/tagParser`.
 *
 * Compile step happens once at mount: the user's CSS source is rewritten so
 * every [[[tag|pipe]]] becomes `var(--ol-...)`, and the stylesheet is injected
 * a single time. Runtime updates flow through `applyAll(data)` which iterates
 * the binding table, formats each value, and writes via
 * `document.documentElement.style.setProperty`. No <style> element swap, no
 * Vue computed re-evaluation, no morphdom pass.
 *
 * The `lastWritten` cache short-circuits setProperty calls when the value
 * didn't actually change for that (key, pipe) pair, so a tag update that
 * doesn't affect a particular CSS-referenced formatter costs nothing.
 */

import { applyFormatter } from '@/utils/formatters';
import type { CssBindingTable } from '@/utils/tagParser';

export function useCssCustomProperties() {
  let bindings: CssBindingTable | null = null;
  let locale = 'en-US';
  const lastWritten = new Map<string, string>();

  function install(table: CssBindingTable, initialLocale: string): void {
    bindings = table;
    locale = initialLocale;
    lastWritten.clear();
  }

  function setLocale(newLocale: string): void {
    if (newLocale === locale) return;
    locale = newLocale;
    // Locale change can re-format every bound value; invalidate the cache so
    // the next applyAll() rewrites everything.
    lastWritten.clear();
  }

  function resolveValue(raw: unknown, pipe: string | undefined): string {
    if (raw === undefined || raw === null || typeof raw === 'object') return '';
    const str = String(raw);
    return pipe ? applyFormatter(str, pipe, locale) : str;
  }

  function applyAll(data: Record<string, unknown> | null | undefined): void {
    if (!bindings || !data) return;
    const root = document.documentElement.style;
    for (const [key, binds] of bindings) {
      const raw = data[key];
      for (const b of binds) {
        const formatted = resolveValue(raw, b.pipe);
        // Bake the unit suffix into the written value so `var(--x)px` becomes
        // `var(--x)` resolving to `50px`. If the formatted value is empty
        // (data not yet present, control unset), drop the suffix too so the
        // property goes empty and the consuming `var()` falls back cleanly.
        const value = formatted === '' ? '' : `${formatted}${b.suffix ?? ''}`;
        if (lastWritten.get(b.property) === value) continue;
        lastWritten.set(b.property, value);
        root.setProperty(b.property, value);
      }
    }
  }

  function teardown(): void {
    if (!bindings) return;
    const root = document.documentElement.style;
    for (const property of lastWritten.keys()) {
      root.removeProperty(property);
    }
    lastWritten.clear();
    bindings = null;
  }

  return { install, setLocale, applyAll, teardown };
}
