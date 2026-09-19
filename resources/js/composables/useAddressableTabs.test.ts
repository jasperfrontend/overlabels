import { describe, expect, it } from 'vitest';
import { tabKeyFromHash, urlWithTab } from './useAddressableTabs';

/**
 * The fragment is the whole contract: a link someone pastes has to open the
 * tab they were looking at, and a link naming a tab this page does not have
 * has to fall through to the default rather than render nothing.
 */
describe('tabKeyFromHash', () => {
  const keys = ['overview', 'controls', 'triggers', 'obs'];

  it('reads a tab the page has', () => {
    expect(tabKeyFromHash('#tab-obs', keys)).toBe('obs');
    expect(tabKeyFromHash('tab-triggers', keys)).toBe('triggers');
  });

  it('ignores a tab the page does not have', () => {
    // /templates/show builds its strip conditionally: #tab-triggers is honest
    // on an alert and meaningless on a static overlay.
    expect(tabKeyFromHash('#tab-triggers', ['overview', 'obs'])).toBeNull();
  });

  it('ignores a fragment that is not a tab', () => {
    expect(tabKeyFromHash('#el-integration-streamlabs', keys)).toBeNull();
    expect(tabKeyFromHash('#obs', keys)).toBeNull();
    expect(tabKeyFromHash('', keys)).toBeNull();
    expect(tabKeyFromHash('#', keys)).toBeNull();
  });

  it('does not treat the prefix alone as a tab', () => {
    expect(tabKeyFromHash('#tab-', keys)).toBeNull();
  });
});

describe('urlWithTab', () => {
  it('keeps the query string the flow needs', () => {
    expect(urlWithTab('/templates/42?state=product&product=chat-checkin', 'obs')).toBe('/templates/42?state=product&product=chat-checkin#tab-obs');
  });

  it('replaces a fragment rather than stacking one', () => {
    expect(urlWithTab('/templates/42#tab-overview', 'controls')).toBe('/templates/42#tab-controls');
  });

  it('works on an absolute URL', () => {
    expect(urlWithTab('https://overlabels.com/dashboard', 'lists')).toBe('https://overlabels.com/dashboard#tab-lists');
  });
});
