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
}

export type AppPageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    flash: FlashMessage;
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
}

export type BreadcrumbItemType = BreadcrumbItem;
