import { describe, expect, it } from 'vitest';
import { applyUiMode, elementKeyFromHash, parseUiMode, resolveUiMode, withLastMileHint } from './useUiMode';

describe('withLastMileHint', () => {
  // Alphabetical, because Laravel echoes Symfony's sorted query string back
  // and Inertia keeps a fragment only when that echo matches the request.
  it('writes the hint with its keys in the order Laravel echoes them back', () => {
    expect(withLastMileHint('/templates/347', 'donation_alert')).toBe('/templates/347?product=donation_alert&state=product');
  });

  it('keeps the fragment and an existing query, all keys sorted', () => {
    expect(withLastMileHint('/templates/347?zoom=2&a=1#tab-obs', 'chat_tower')).toBe(
      '/templates/347?a=1&product=chat_tower&state=product&zoom=2#tab-obs',
    );
  });

  it('replaces a hint the href already carries rather than doubling it', () => {
    expect(withLastMileHint('/x?state=product&product=old', 'new')).toBe('/x?product=new&state=product');
  });

  it('round-trips through parseUiMode', () => {
    expect(parseUiMode(withLastMileHint('/x', 'chat_checkin'))).toEqual({ lastMile: true, product: 'chat_checkin' });
  });
});

describe('resolveUiMode', () => {
  it('is on while a setup flow is active, whatever the URL says', () => {
    expect(resolveUiMode(parseUiMode('/dashboard'), { slug: 'chat_checkin', ready: false, next: { target: 'bot-toggle' } })).toEqual({
      mode: 'product',
      lastMile: false,
      product: 'chat_checkin',
      ready: false,
      target: 'bot-toggle',
    });
    expect(resolveUiMode(parseUiMode('/settings/integrations'), { slug: 'follower_bowling', ready: true, next: null })).toEqual({
      mode: 'product',
      lastMile: false,
      product: 'follower_bowling',
      ready: true,
      target: null,
    });
  });

  it('never turns the mode on from the URL alone: a finished flow stays finished', () => {
    expect(resolveUiMode(parseUiMode('/templates/337?product=follower_bowling&state=product'), null)).toEqual({
      mode: null,
      lastMile: true,
      product: 'follower_bowling',
      ready: false,
      target: null,
    });
    expect(resolveUiMode(parseUiMode('/dashboard'), undefined)).toEqual({ mode: null, lastMile: false, product: null, ready: false, target: null });
  });

  it('lets the URL name the product when both do, and the flow fill in when the URL does not', () => {
    expect(resolveUiMode(parseUiMode('/t?state=product&product=follower_bowling'), { slug: 'chat_checkin', ready: false }).product).toBe(
      'follower_bowling',
    );
    expect(resolveUiMode(parseUiMode('/t?state=product'), { slug: 'chat_checkin', ready: false }).product).toBe('chat_checkin');
    expect(resolveUiMode(parseUiMode('/t?state=product'), { slug: 'chat_checkin', ready: false }).lastMile).toBe(true);
  });
});

describe('parseUiMode', () => {
  it('reads the last-mile hint and its slug from a URL', () => {
    expect(parseUiMode('/templates/328?state=product&product=chat_checkin')).toEqual({ lastMile: true, product: 'chat_checkin' });
    expect(parseUiMode('https://overlabels.com/templates/328?product=follower_bowling&state=product#obs')).toEqual({
      lastMile: true,
      product: 'follower_bowling',
    });
  });

  it('is no hint without the parameter, or with a value it does not know', () => {
    expect(parseUiMode('/templates/328')).toEqual({ lastMile: false, product: null });
    expect(parseUiMode('/templates/328?state=party')).toEqual({ lastMile: false, product: null });
    expect(parseUiMode('/templates/328?product=chat_checkin')).toEqual({ lastMile: false, product: null });
  });

  it('drops a product slug that is not a slug', () => {
    expect(parseUiMode('/x?state=product&product=../etc')).toEqual({ lastMile: true, product: null });
    expect(parseUiMode('/x?state=product&product=')).toEqual({ lastMile: true, product: null });
  });
});

describe('applyUiMode', () => {
  it('sets and removes data-mode on the root it is given', () => {
    const root = { dataset: {} as Record<string, string> } as unknown as HTMLElement;
    applyUiMode('product', root);
    expect(root.dataset.mode).toBe('product');
    applyUiMode(null, root);
    expect(root.dataset.mode).toBeUndefined();
  });

  it('does nothing without a root', () => {
    expect(() => applyUiMode('product', null)).not.toThrow();
  });
});

describe('elementKeyFromHash', () => {
  it('reads the control a step links to', () => {
    expect(elementKeyFromHash('#el-integration-streamlabs')).toBe('integration-streamlabs');
    expect(elementKeyFromHash('el-token-create')).toBe('token-create');
  });

  it('ignores a fragment that names a tab, so the two namespaces never cross', () => {
    expect(elementKeyFromHash('#tab-obs')).toBeNull();
  });

  it('ignores anything that could not be an attribute value', () => {
    // The key reaches a querySelector, so it is checked rather than escaped.
    expect(elementKeyFromHash('#el-"]/*')).toBeNull();
    expect(elementKeyFromHash('#el-')).toBeNull();
    expect(elementKeyFromHash('')).toBeNull();
  });
});
