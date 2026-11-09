import { describe, expect, it } from 'vitest';
import { headStylesheetHrefs, planAlertSource, toAlertSourceMap, type AlertSourceMap } from './alertSources';

const preloaded: AlertSourceMap = {
  'kofi-alert': { head: '<link rel="stylesheet" href="https://fonts.example/a.css">', html: '<b>old</b>', css: '.a{}', version: 'v1' },
};

describe('planAlertSource', () => {
  it('uses the source that rode the broadcast and remembers it', () => {
    const plan = planAlertSource(preloaded, {
      alert_template_slug: 'kofi-alert',
      alert_template_version: 'v2',
      html: '<b>new</b>',
      css: '.b{}',
      head: '',
    });
    expect(plan).toEqual({ kind: 'ready', source: { head: '', html: '<b>new</b>', css: '.b{}', version: 'v2' }, remember: true });
  });

  it('uses the mount-time copy when the broadcast left the source out and the version matches', () => {
    const plan = planAlertSource(preloaded, {
      alert_template_slug: 'kofi-alert',
      alert_template_version: 'v1',
      html: '',
      css: '',
      source_omitted: true,
    });
    expect(plan).toEqual({ kind: 'ready', source: preloaded['kofi-alert'], remember: false });
  });

  it('fetches when the mount-time copy is stale', () => {
    expect(planAlertSource(preloaded, { alert_template_slug: 'kofi-alert', alert_template_version: 'v2', html: '', source_omitted: true })).toEqual({
      kind: 'fetch',
      slug: 'kofi-alert',
    });
  });

  it('fetches an alert created after the overlay loaded', () => {
    expect(planAlertSource(preloaded, { alert_template_slug: 'brand-new', alert_template_version: 'v1', html: '', source_omitted: true })).toEqual({
      kind: 'fetch',
      slug: 'brand-new',
    });
  });

  it('renders a broadcast from a server that predates versions exactly as before', () => {
    const plan = planAlertSource({}, { alert_template_slug: 'kofi-alert', html: '<b>x</b>', css: '.x{}' });
    expect(plan).toEqual({ kind: 'ready', source: { head: '', html: '<b>x</b>', css: '.x{}', version: '' }, remember: false });
  });

  it('gives up only when there is neither a source nor a slug to look one up by', () => {
    expect(planAlertSource(preloaded, { html: '', source_omitted: true })).toEqual({ kind: 'none' });
  });
});

describe('toAlertSourceMap', () => {
  it('keeps well-formed entries and drops the rest without throwing', () => {
    const map = toAlertSourceMap({
      good: { head: null, html: '<i></i>', css: '', version: 'abc' },
      noHtml: { css: '.x{}' },
      junk: 'nope',
    });
    expect(map).toEqual({ good: { head: '', html: '<i></i>', css: '', version: 'abc' } });
  });

  it('treats an absent or array payload as no sources', () => {
    expect(toAlertSourceMap(undefined)).toEqual({});
    expect(toAlertSourceMap([])).toEqual({});
  });
});

describe('headStylesheetHrefs', () => {
  it('finds https stylesheet links in any attribute order and decodes ampersands', () => {
    const head =
      '<link href="https://fonts.googleapis.com/css2?family=Anton&amp;display=swap" rel="stylesheet">' +
      "<link rel='preconnect' href='https://fonts.gstatic.com'>" +
      '<link rel=stylesheet href=https://cdn.example/x.css>' +
      '<link rel="stylesheet" href="javascript:void(0)">';
    expect(headStylesheetHrefs(head)).toEqual(['https://fonts.googleapis.com/css2?family=Anton&display=swap', 'https://cdn.example/x.css']);
  });
});
