import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * One date + time format, in the signed-in user's locale (the picker on the Appearance settings page).
 *
 * Accepts an ISO string (how Eloquent serialises a date) or Unix seconds. Null or empty renders as
 * `fallback`; a string the browser cannot parse renders as itself rather than "Invalid Date".
 */
export function useDateTimeFormat() {
  const page = usePage();

  const formatter = computed(() => {
    const locale = (page.props as { auth?: { user?: { locale?: string } } })?.auth?.user?.locale || 'en-US';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'medium' });
  });

  function formatDateTime(value: string | number | null | undefined, fallback = '-'): string {
    if (value === null || value === undefined || value === '') return fallback;
    const date = new Date(typeof value === 'number' ? value * 1000 : value);
    if (Number.isNaN(date.getTime())) return String(value);
    return formatter.value.format(date);
  }

  return { formatDateTime };
}
