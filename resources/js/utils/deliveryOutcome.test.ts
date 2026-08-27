import { describe, expect, it } from 'vitest';
import { OUTCOME_LABELS, SCORED_OUTCOMES, isScoredOutcome, outcomeLabel } from './deliveryOutcome';

describe('deliveryOutcome', () => {
  it('mirrors the nine outcomes of App\\Enums\\DeliveryOutcome', () => {
    expect(Object.keys(OUTCOME_LABELS).sort()).toEqual(
      ['delivered', 'no_listener', 'failed', 'token_invalid', 'render_failed', 'no_mapping', 'muted', 'chat_only', 'unknown_user'].sort(),
    );
  });

  it('scores exactly the delivery family', () => {
    expect([...SCORED_OUTCOMES]).toEqual(['delivered', 'no_listener', 'failed', 'token_invalid', 'render_failed']);
    expect(isScoredOutcome('no_listener')).toBe(true);
    expect(isScoredOutcome('no_mapping')).toBe(false);
    expect(isScoredOutcome(null)).toBe(false);
  });

  it('renders nothing for a row from before the ledger', () => {
    expect(outcomeLabel(null)).toBeNull();
    expect(outcomeLabel(undefined)).toBeNull();
    expect(outcomeLabel('token_invalid')).toBe('login expired');
    expect(outcomeLabel('something_new')).toBe('something_new');
  });
});
