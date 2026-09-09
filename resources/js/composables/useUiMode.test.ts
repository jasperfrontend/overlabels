import { describe, expect, it } from 'vitest';
import { applyUiMode, parseUiMode, resolveUiMode } from './useUiMode';

describe('resolveUiMode', () => {
  it('is on while a setup flow is active, whatever the URL says', () => {
    expect(resolveUiMode(parseUiMode('/dashboard'), { slug: 'chat_checkin', ready: false })).toEqual({
      mode: 'product',
      product: 'chat_checkin',
      ready: false,
    });
    expect(resolveUiMode(parseUiMode('/settings/integrations'), { slug: 'follower_bowling', ready: true })).toEqual({
      mode: 'product',
      product: 'follower_bowling',
      ready: true,
    });
  });

  it('lets the URL name the product when both do, and the flow fill in when the URL does not', () => {
    expect(resolveUiMode(parseUiMode('/t?state=product&product=follower_bowling'), { slug: 'chat_checkin', ready: false }).product).toBe(
      'follower_bowling',
    );
    expect(resolveUiMode(parseUiMode('/t?state=product'), { slug: 'chat_checkin', ready: false }).product).toBe('chat_checkin');
  });

  it('falls back to the URL alone, never ready, when no flow is active', () => {
    expect(resolveUiMode(parseUiMode('/t?state=product&product=chat_checkin'), null)).toEqual({
      mode: 'product',
      product: 'chat_checkin',
      ready: false,
    });
    expect(resolveUiMode(parseUiMode('/dashboard'), undefined)).toEqual({ mode: null, product: null, ready: false });
  });
});

describe('parseUiMode', () => {
  it('reads the product mode and its slug from a URL', () => {
    expect(parseUiMode('/templates/328?state=product&product=chat_checkin')).toEqual({ mode: 'product', product: 'chat_checkin' });
    expect(parseUiMode('https://overlabels.com/templates/328?product=follower_bowling&state=product#obs')).toEqual({
      mode: 'product',
      product: 'follower_bowling',
    });
  });

  it('is no mode without the parameter, or with a value it does not know', () => {
    expect(parseUiMode('/templates/328')).toEqual({ mode: null, product: null });
    expect(parseUiMode('/templates/328?state=party')).toEqual({ mode: null, product: null });
    expect(parseUiMode('/templates/328?product=chat_checkin')).toEqual({ mode: null, product: null });
  });

  it('drops a product slug that is not a slug', () => {
    expect(parseUiMode('/x?state=product&product=../etc')).toEqual({ mode: 'product', product: null });
    expect(parseUiMode('/x?state=product&product=')).toEqual({ mode: 'product', product: null });
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
