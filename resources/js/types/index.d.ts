import type { LucideIcon } from '@lucide/vue';

export interface Auth {
  csrf: string | null | undefined;
  user: User;
}

export interface BreadcrumbItem {
  title: string;
  href: string;
}

export interface NavItem {
  title: string;
  href: string;
  icon?: LucideIcon;
  isActive?: boolean;
  target?: string;
}

export interface FlashMessage {
  message?: string;
  type?: 'info' | 'success' | 'warning' | 'error';
}

export interface StreamState {
  state: 'offline' | 'starting' | 'live' | 'ending';
  confidence: number;
  startedAt: string | null;
}

export interface UsageSummary {
  /** Broadcasts (overlay updates) counted this month. */
  broadcasts: number;
  /** Free-tier monthly ceiling, or null when running observe-only. */
  limit: number | null;
  /** The month the count covers, as YYYY-MM. */
  period: string;
}

/** A help page that declared the current route in its `context:` frontmatter. */
export interface HelpLink {
  slug: string;
  /** The page's short name, from its `heading` frontmatter. */
  title: string;
  /** The page's own opening paragraph, used as the excerpt. */
  lead: string;
  url: string;
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
  name: string;
  quote: { message: string; author: string };
  auth: Auth;
  sidebarOpen: boolean;
  /** Contextual help for the current route, best match first. Often empty. */
  help: HelpLink[];
  flash: FlashMessage;
  isAdmin: boolean;
  /** Keys of one-off NudgeBars this user has already clicked away. */
  dismissedNudges: string[];
  impersonating: { real_admin_id: number; target_user_id: number; target_name: string | null } | null;
  lockdown: { active: boolean; activated_at?: string; activated_by?: number; activated_by_name?: string; reason?: string } | null;
  streamState: StreamState | null;
  twitchScope: { missing: string[] } | null;
};

/* Twitch event types.
 * See https://dev.twitch.tv/docs/eventsub/eventsub-subscription-types/
 * NOTE: any action sent from the Twitch EventSub API will contain a user_id, user_login and user_name.
 * This is the user_id of the user who performs the action, not the broadcaster.
 * To get the broadcaster's id, use broadcaster_user_id instead, which is also sent along in the payload.
 */
export type NormalizedEvent = {
  id: string; // Twitch message_id for de-dupe
  type: string; // 'channel.subscribe'
  ts: number; // Date.now()
  broadcaster_user_id: string;
  broadcaster_user_login: string;
  broadcaster_user_name: string;
  broadcaster_user_avatar?: string;
  user_login?: string;
  user_name?: string;
  user_id?: string;
  user_avatar?: string;
  gifter_name: string | undefined;
  tier?: '1000' | '2000' | '3000' | string | undefined;
  is_gift?: boolean;
  gift_count?: number; // for bombs
  cumulative_total?: number;
  to_broadcaster_user_id?: string;
  to_broadcaster_user_login?: string;
  to_broadcaster_user_name?: string;
  to_broadcaster_user_avatar?: string;
  from_broadcaster_user_id?: string;
  from_broadcaster_user_login?: string;
  from_broadcaster_user_name?: string;
  from_broadcaster_user_avatar?: string;
  viewers?: number;
  raw: any; // keep original for debugging
};

export interface User {
  access_token: any;
  description: any;
  twitch_data: any;
  id: number;
  name: string;
  avatar?: string;
  created_at: string;
  updated_at: string;
  role: 'user' | 'admin';
  locale?: string;
  foreach_caps?: ForeachCaps;
  is_system_user: boolean;
  deleted_at: string | null;
}

export interface ForeachCaps {
  subscribers: number;
  goals: number;
  followers: number;
  followed: number;
  /** Chat is the one cap enforced client-side, as the socket's window size. */
  chat: number;
  /** Checkins: server slices the initial window, the client trims after each delta. */
  checkins: number;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface AdminTemplate {
  id: number;
  name: string;
  slug: string;
  type: string;
  is_public: boolean;
  fork_count?: number;
  view_count?: number;
  created_at: string;
  updated_at?: string;
  owner?: { id: number; name: string; twitch_id: string | null } | null;
}

export interface BuilderPlacement {
  instance_id: string;
  block_template_id: number;
  block_slug: string;
  block_name: string;
  x: number;
  y: number;
  w: number;
  h: number;
  snapshot: { head: string; html: string; css: string };
}

export interface BuilderMetadata {
  version: number;
  grid: { cols: number; rows: number; gap: number };
  canvas: { width: number; height: number };
  placements: BuilderPlacement[];
  // Overlay-level overrides the user typed in the Builder's Style panel. Input
  // to the compile, never output of it - the composed head/css are rebuilt from
  // scratch on every save, so these have to survive here.
  custom_css?: string;
  custom_head?: string;
}

export interface OverlayTemplate {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert' | 'block';
  is_public: boolean;
  view_count: number;
  fork_count: number;
  kits_exists?: boolean;
  screenshot_url?: string | null;
  has_controls?: boolean;
  metadata?: {
    block?: { default_span?: { w: number; h: number }; category?: string };
    builder?: BuilderMetadata;
  } | null;
  template_tags?: string[] | null;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  event_mappings?: { event_type: string }[];
  external_event_mappings?: { service: string; event_type: string }[];
  created_at: string;
  updated_at: string;
}

export interface Update {
  id: number;
  title: string;
  slug: string;
  tags: string[] | null;
  excerpt: string | null;
  body: string;
  compiled_css: string | null;
  published_at: string;
  created_at: string;
  updated_at: string;
}

/** One row on the What's New card. Built by WhatsNewController::props(). */
export interface WhatsNewItem {
  id: number;
  title: string;
  excerpt: string | null;
  published_at: string;
  href: string;
  /** `external` means the link leaves the app, so the visit can only be recorded on click. */
  cta: { label: string; href: string; external: boolean } | null;
  /** The reader has already been where this points. Stays on the card, in greys. */
  stale: boolean;
}

export interface WhatsNew {
  items: WhatsNewItem[];
  /** Every unseen post, including the ones past the card's row cap. */
  total: number;
  /** Whether this account has anything a press of Undo could bring back. */
  canUndo: boolean;
}

export interface OverlayControl {
  id: number;
  overlay_template_id: number | null;
  user_id: number;
  key: string;
  label: string | null;
  description: string | null;
  type: 'text' | 'number' | 'counter' | 'timer' | 'datetime' | 'boolean' | 'expression' | 'list_writer';
  value: string | null;
  config: Record<string, any> | null;
  sort_order: number;
  source: string | null;
  source_managed: boolean;
  created_at: string;
  updated_at: string;
}
