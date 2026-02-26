import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

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

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    flash: FlashMessage;
    isAdmin: boolean;
    impersonating: { real_admin_id: number; target_user_id: number; target_name: string | null } | null;
};


/* Twitch event types.
 * See https://dev.twitch.tv/docs/eventsub/eventsub-subscription-types/
 * NOTE: any action sent from the Twitch EventSub API will contain a user_id, user_login and user_name.
 * This is the user_id of the user who performs the action, not the broadcaster.
 * To get the broadcaster's id, use broadcaster_user_id instead, which is also sent along in the payload.
 */
export type NormalizedEvent = {
  id: string;               // Twitch message_id for de-dupe
  type: string;             // 'channel.subscribe'
  ts: number;               // Date.now()
  broadcaster_user_id: string;
  broadcaster_user_login: string;
  broadcaster_user_name: string;
  user_login?: string;
  user_name?: string;
  user_id?: string;
  gifter_name: string | undefined;
  tier?: '1000'|'2000'|'3000' | string | undefined;
  is_gift?: boolean;
  gift_count?: number;      // for bombs
  cumulative_total?: number;
  to_broadcaster_user_id?: string;
  to_broadcaster_user_login?: string;
  to_broadcaster_user_name?: string;
  from_broadcaster_user_id?: string;
  from_broadcaster_user_login?: string;
  from_broadcaster_user_name?: string;
  viewers?: number;
  raw: any;                 // keep original for debugging
}

export interface User {
    access_token: any;
    description: any;
    twitch_data: any;
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    role: 'user' | 'admin';
    is_system_user: boolean;
    deleted_at: string | null;
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

export interface OverlayTemplate {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  type: 'static' | 'alert';
  is_public: boolean;
  view_count: number;
  fork_count: number;
  has_controls?: boolean;
  owner?: {
    id: number;
    name: string;
    avatar?: string;
  };
  event_mappings?: { event_type: string }[];
  created_at: string;
  updated_at: string;
}

export interface OverlayControl {
  id: number;
  overlay_template_id: number;
  user_id: number;
  key: string;
  label: string | null;
  type: 'text' | 'number' | 'counter' | 'timer' | 'datetime' | 'boolean';
  value: string | null;
  config: Record<string, any> | null;
  sort_order: number;
  created_at: string;
  updated_at: string;
}
