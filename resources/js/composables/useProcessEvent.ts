import { EVENT_RULES } from '@/composables/useTwitchEventRules';
import type { NormalizedEvent } from '@/types';

/**
 * Minimal object path getter — no deps.
 */
function get(obj: any, path: string, defaultValue?: any) {
  if (!obj) return defaultValue;
  return path.split('.').reduce((acc, key) => {
    if (acc && Object.prototype.hasOwnProperty.call(acc, key)) {
      return acc[key];
    }
    return undefined;
  }, obj) ?? defaultValue;
}

/**
 * Process a normalized Twitch event against EVENT_RULES,
 * mutating the `state` object in place.
 */
export function processEvent(event: NormalizedEvent, state: Record<string, any>) {
  const rules = EVENT_RULES[event.type];
  if (!rules) return;

  for (const rule of rules) {
    switch (rule.op) {
      case 'set': {
        state[rule.tag] = rule.hasOwnProperty('value') ? rule.value : get(event, rule.from, undefined);
        break;
      }

      case 'inc': {
        const incValue =
          rule.hasOwnProperty('by')
            ? rule.by
            : get(event, rule.byPath, 1);
        state[rule.tag] = (state[rule.tag] ?? 0) + (incValue ?? 0);
        break;
      }

      case 'max': {
        const maxValue =
          rule.hasOwnProperty('value')
            ? rule.value
            : get(event, rule.from, undefined);
        if (typeof maxValue === 'number') {
          state[rule.tag] = Math.max(state[rule.tag] ?? 0, maxValue);
        }
        break;
      }

      case 'do': {
        state[rule.tag] = rule.value ?? true;
        break;
      }

      default:
        console.warn(`Unknown op "${rule.op}" for event ${event.type}`);
    }
  }
}
