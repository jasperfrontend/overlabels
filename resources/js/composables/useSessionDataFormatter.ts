import { computed, type Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

type Options = {
  /** Optional reactive speed unit ('kmh' | 'mph'). Required if you call formatSpeed/formatDistance. */
  speedUnit?: Ref<string>;
  /** Optional locale override - takes precedence over the authed user's locale. Used on
   *  unauthenticated pages (e.g. the public map view) where we want the streamer's locale
   *  rather than 'en-US'. */
  localeOverride?: Ref<string | null | undefined>;
};

export function useSessionDataFormatter(opts: Options = {}) {
  const page = usePage();

  const userLocale = computed<string>(() => {
    const override = opts.localeOverride?.value;
    if (override) return override;
    const user = (page.props as { auth?: { user?: { locale?: string } } })?.auth?.user;
    return user?.locale || 'en-US';
  });

  function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(userLocale.value, {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
    });
  }

  function formatTime(iso: string): string {
    return new Date(iso).toLocaleTimeString(userLocale.value, {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  }

  function formatDuration(startIso: string, endIso: string): string {
    const ms = new Date(endIso).getTime() - new Date(startIso).getTime();
    const totalSeconds = Math.floor(ms / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    if (hours > 0) {
      return `${hours}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
    }
    return `${minutes}m ${String(seconds).padStart(2, '0')}s`;
  }

  function formatDistance(km: number): string {
    const unit = opts.speedUnit?.value ?? 'kmh';
    if (unit === 'mph') {
      const miles = km / 1.609344;
      return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 2 }).format(miles) + ' mi';
    }
    return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 2 }).format(km) + ' km';
  }

  function formatAltitude(m: number | null): string | null {
    if (m === null) return null;
    return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 1 }).format(m) + ' m';
  }

  function formatSpeed(ms: number | null): string | null {
    if (ms === null) return null;
    const unit = opts.speedUnit?.value ?? 'kmh';
    const converted = unit === 'mph' ? (ms * 3.6) / 1.609344 : ms * 3.6;
    return new Intl.NumberFormat(userLocale.value, { maximumFractionDigits: 1 }).format(converted);
  }

  function batteryDelta(start: number | null, end: number | null): string {
    if (start === null || end === null) return '';
    const diff = end - start;
    if (diff > 0) return `+${diff}%`;
    if (diff < 0) return `${diff}%`;
    return '0%';
  }

  function batteryColor(pct: number | null): string {
    if (pct === null) return 'text-muted-foreground';
    if (pct <= 15) return 'text-red-500';
    if (pct <= 30) return 'text-amber-500';
    return 'text-green-500';
  }

  return {
    userLocale,
    formatDate,
    formatTime,
    formatDuration,
    formatDistance,
    formatAltitude,
    formatSpeed,
    batteryDelta,
    batteryColor,
  };
}
