// Shared types for the dashboard Lists pages and their components.

export interface AppenderRow {
  id: number;
  target_list_id: number;
  command: string;
  permission_level: string;
  cooldown_seconds: number;
  value_template: string;
  args_empty_reply: string | null;
  success_reply: string | null;
  dedup_policy: 'none' | 'per_chatter' | 'per_chatter_per_stream';
  max_size: number | null;
  enabled: boolean;
  last_fired_at: number | null;
}

export type ToastType = 'info' | 'success' | 'warning' | 'error';
