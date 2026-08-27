/**
 * The delivery ledger's outcome vocabulary, as words a streamer reads.
 * Mirrors App\Enums\DeliveryOutcome. Scored outcomes are the ones that count
 * toward "of the alerts that should have reached your overlay, how many did";
 * the rest are context and never a mark against delivery.
 */
export const SCORED_OUTCOMES = ['delivered', 'no_listener', 'failed', 'token_invalid', 'render_failed'] as const;

export const OUTCOME_LABELS: Record<string, string> = {
  delivered: 'delivered',
  no_listener: 'no overlay open',
  failed: 'failed',
  token_invalid: 'login expired',
  render_failed: 'could not build',
  no_mapping: 'no alert',
  muted: 'muted',
  chat_only: 'chat only',
  unknown_user: 'unknown user',
};

export function outcomeLabel(outcome: string | null | undefined): string | null {
  if (!outcome) return null;
  return OUTCOME_LABELS[outcome] ?? outcome;
}

export function isScoredOutcome(outcome: string | null | undefined): boolean {
  return (SCORED_OUTCOMES as readonly string[]).includes(outcome ?? '');
}
