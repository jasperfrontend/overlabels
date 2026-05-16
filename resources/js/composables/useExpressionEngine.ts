/**
 * Frontend Vue wrapper around the shared expression engine. The pure
 * evaluator + function library lives in `resources/js/lib/expression-engine/`
 * and is also imported by the Node sidecar at `services/expression-engine/`
 * - one source of truth, parity by construction.
 *
 * This file is responsible for the Vue-shaped concerns only:
 *  - reactive re-evaluation on data changes (watchEffect)
 *  - shared RAF ticker for time-dependent expressions (`now()`, `now_ms()`)
 *  - per-key registration / unregistration
 *  - writing the result back into `data.value` under `c:<key>`
 *
 * Everything that isn't Vue-flavoured (parsing, evaluation, function impls,
 * context building, AST inspection) is re-exported from the shared lib so
 * existing imports like `import { evaluate } from '@/composables/useExpressionEngine'`
 * continue to work.
 */

import jsep from 'jsep';
import { type Ref, watchEffect, type WatchStopHandle } from 'vue';

// Pure engine surface from the shared lib. Re-exported so consumers don't
// need to know whether they're talking to the wrapper or the lib.
import {
  buildContext,
  evaluate,
  resultToString,
  containsNowCall,
  ARG_FUNCTIONS,
  SUPPORTED_FUNCTIONS,
} from '@/lib/expression-engine/engine.mjs';

export {
  buildContext,
  evaluate,
  resultToString,
  containsNowCall,
  ARG_FUNCTIONS,
  SUPPORTED_FUNCTIONS,
};

// ---------------------------------------------------------------------------
// Composable
// ---------------------------------------------------------------------------

interface ExpressionEntry {
  key: string;
  dataKey: string;
  expression: string;
  ast: jsep.Expression | null;
  stop: WatchStopHandle | null;
  timeDependent: boolean;
}

export function useExpressionEngine(data: Ref<Record<string, any> | null | undefined>) {
  const registry = new Map<string, ExpressionEntry>();

  let rafHandle: number | null = null;
  let timeDependentCount = 0;

  // Time-dependent expressions are evaluated in a single RAF-driven batch
  // rather than via per-expression watchEffects. The old design read and
  // wrote data.value inside each watchEffect, so each write triggered every
  // other time-dependent watchEffect to re-run; `now_ms()` had advanced by a
  // few microseconds in between, so resultToString produced a different
  // string, which wrote data.value again, which... Vue trips its recursive-
  // update guard and the overlay locks up at ~5fps. Batching collapses N
  // writes into one per frame and breaks the cycle entirely.
  function tickFrame(): void {
    if (data.value) {
      const ctx = buildContext(data.value);
      let patch: Record<string, string> | null = null;

      for (const entry of registry.values()) {
        if (!entry.timeDependent || !entry.ast) continue;
        try {
          const result = evaluate(entry.ast, ctx);
          const strResult = resultToString(result);
          if (data.value[entry.dataKey] !== strResult) {
            if (patch === null) patch = {};
            patch[entry.dataKey] = strResult;
          }
        } catch (e) {
          console.warn(`[ExpressionEngine] Evaluation error for "${entry.key}":`, e);
        }
      }

      if (patch !== null) {
        data.value = { ...data.value, ...patch };
      }
    }

    rafHandle = requestAnimationFrame(tickFrame);
  }

  function ensureTickerRunning(): void {
    if (rafHandle !== null) return;
    rafHandle = requestAnimationFrame(tickFrame);
  }

  function stopTickerIfIdle(): void {
    if (timeDependentCount > 0 || rafHandle === null) return;
    cancelAnimationFrame(rafHandle);
    rafHandle = null;
  }

  function registerExpression(key: string, expression: string): void {
    unregisterExpression(key);

    const dataKey = `c:${key}`;

    let ast: jsep.Expression | null = null;
    try {
      ast = jsep(expression);
    } catch (e) {
      console.warn(`[ExpressionEngine] Parse error for "${key}":`, e);
    }

    const timeDependent = containsNowCall(ast);

    let stop: WatchStopHandle | null = null;

    if (timeDependent) {
      // Driven by tickFrame; no watchEffect. Data-dep refreshes pick up on
      // the next animation frame (<= 16ms), which is the same latency the
      // user would see from a CSS transition anyway.
      timeDependentCount++;
      ensureTickerRunning();

      // Seed the initial value so the first frame doesn't render with an
      // undefined slot. Result is written directly without reactivity.
      if (ast && data.value) {
        try {
          const result = evaluate(ast, buildContext(data.value));
          const strResult = resultToString(result);
          if (data.value[dataKey] !== strResult) {
            data.value = { ...data.value, [dataKey]: strResult };
          }
        } catch (e) {
          console.warn(`[ExpressionEngine] Initial eval error for "${key}":`, e);
        }
      }
    } else {
      // Data-dependent only: react to changes in the keys this expression
      // reads. Writes are bounded by the `!==` check so identical results
      // do not propagate.
      stop = watchEffect(() => {
        if (!data.value || !ast) return;

        const ctx = buildContext(data.value);

        try {
          const result = evaluate(ast, ctx);
          const strResult = resultToString(result);

          if (data.value[dataKey] !== strResult) {
            data.value = { ...data.value, [dataKey]: strResult };
          }
        } catch (e) {
          console.warn(`[ExpressionEngine] Evaluation error for "${key}":`, e);
        }
      });
    }

    registry.set(key, { key, dataKey, expression, ast, stop, timeDependent });
  }

  function unregisterExpression(key: string): void {
    const entry = registry.get(key);
    if (!entry) return;
    if (entry.stop) entry.stop();
    if (entry.timeDependent) {
      timeDependentCount--;
      stopTickerIfIdle();
    }
    registry.delete(key);
  }

  function destroy(): void {
    for (const entry of registry.values()) {
      if (entry.stop) entry.stop();
    }
    registry.clear();
    timeDependentCount = 0;
    if (rafHandle !== null) {
      cancelAnimationFrame(rafHandle);
      rafHandle = null;
    }
  }

  return {
    registerExpression,
    unregisterExpression,
    destroy,
  };
}
