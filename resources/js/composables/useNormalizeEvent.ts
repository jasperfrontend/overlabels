import type { NormalizedEvent } from '@/types';

/**
 * Normalize the raw Twitch EventSub payload into a consistent structure.
 * Handles all major EventSub event types with proper field mapping.
 */
export function normalizeEvent(raw: any): NormalizedEvent {
  const e = raw?.event ?? raw?.payload ?? raw?.eventData ?? raw;
  const eventType = raw?.subscription?.type ?? raw?.eventType ?? e?.type ?? 'unknown';

  const pick = <T = any>(...keys: string[]): T | null => {
    for (const key of keys) {
      if (e?.[key] != null) return e[key];
      if (raw?.[key] != null) return raw[key];
    }
    return null;
  };

  let user_id: string | null;
  let user_login: string | null;
  let user_name: string | null;
  let gifter_name: string | null = null;
  let tier: '1000' | '2000' | '3000' | null = null;
  let is_gift: boolean | null = null;
  let gift_count: number | null = null;

  switch (eventType) {
    case 'channel.subscribe':
    case 'channel.subscription.message':
      user_id = e?.user_id;
      user_login = e?.user_login;
      user_name = e?.user_name;
      tier = e?.tier;
      is_gift = e?.is_gift ?? false;
      break;

    case 'channel.subscription.gift':
      user_id = e?.user_id;
      user_login = e?.user_login;
      user_name = e?.user_name;
      gifter_name = e?.user_name;
      tier = e?.tier;
      is_gift = true;
      gift_count = e?.total ?? 1;
      break;

    case 'channel.follow':
      user_id = e?.user_id;
      user_login = e?.user_login;
      user_name = e?.user_name;
      break;

    case 'channel.raid':
      user_id = e?.from_broadcaster_user_id;
      user_login = e?.from_broadcaster_user_login;
      user_name = e?.from_broadcaster_user_name;
      break;

    case 'channel.cheer':
      user_id = e?.user_id ?? (e?.is_anonymous ? null : e?.user_id);
      user_login = e?.user_login ?? (e?.is_anonymous ? null : e?.user_login);
      user_name = e?.user_name ?? (e?.is_anonymous ? 'Anonymous' : e?.user_name);
      break;

    default:
      user_id = pick('user_id');
      user_login = pick('user_login');
      user_name = pick('user_name');
      break;
  }

  const broadcaster_user_id = pick('broadcaster_user_id', 'to_broadcaster_user_id');
  const broadcaster_user_login = pick('broadcaster_user_login', 'to_broadcaster_user_login');
  const broadcaster_user_name = pick('broadcaster_user_name', 'to_broadcaster_user_name');

  const timestamps = [
    e?.followed_at,
    e?.started_at,
    e?.raided_at,
    e?.timestamp,
    e?.redeemed_at,
    e?.created_at,
    raw?.timestamp,
  ].filter(Boolean);

  const ts = timestamps.length > 0 ? Date.parse(timestamps[0]) || Date.now() : Date.now();

  const id = pick('id', 'message_id') ??
    raw?.metadata?.message_id ??
    `${eventType}-${user_id || 'unknown'}-${ts}`;

  return {
    broadcaster_user_id: broadcaster_user_id ?? undefined,
    broadcaster_user_login: broadcaster_user_login ?? undefined,
    broadcaster_user_name: broadcaster_user_name ?? undefined,
    gifter_name: gifter_name ?? undefined,
    id: String(id),
    type: eventType,
    ts,
    user_login: user_login ?? undefined,
    user_name: user_name ?? undefined,
    user_id: user_id ?? undefined,
    tier: tier ?? undefined,
    is_gift: is_gift ?? undefined,
    gift_count: gift_count ?? undefined,
    raw,
  };
}

