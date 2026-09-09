import { describe, expect, it } from 'vitest';
import { applyUiMode, parseUiMode, resolveUiMode } from './useUiMode';

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
