import { describe, expect, it } from 'vitest';
import { playables, previewData, stageDocument, type KitPreviewData } from './alertPreview';

const alert = {
  id: 7,
  name: 'Stamped - Ko-fi alert',
  version: 'v1',
  sound_url: null,
  head: '<link rel="stylesheet" href="https://fonts.example/a.css">',
  html: '<div class="stp">[[[if:event.type = subscription]]]member[[[else]]]tip[[[endif]]] [[[event.from_name]]] [[[c:accent]]] [[[channel_name]]]</div>',
  css: '.stp{color:[[[c:accent]]]}',
  compiled_css: '.p-4{padding:1rem}',
  controls: [{ key: 'accent', type: 'color', value: '#ff5e5b' }],
};

const preview: KitPreviewData = {
  alerts: [alert],
  backdrop: { name: 'Scene', head: '', html: '<main>[[[channel_name]]] live</main>', css: 'main{opacity:.9}', compiled_css: '', controls: [] },
  triggers: [
    {
      template_id: 7,
      service: 'kofi',
      event_type: 'subscription',
      duration_ms: 8000,
      label: 'Ko-fi Subscription',
      variants: [
        { key: 'first', label: 'Ko-fi Subscription - first month', tags: { 'event.type': 'subscription', 'event.from_name': 'NightOwlNora' } },
        { key: 'renewal', label: 'Ko-fi Subscription - renewal', tags: { 'event.type': 'subscription', 'event.from_name': 'Nora <b>' } },
      ],
    },
    { template_id: 99, service: 'kofi', event_type: 'donation', duration_ms: 5000, label: 'orphan', variants: [{ key: 'x', label: 'x', tags: {} }] },
  ],
  declared: true,
  sample: { channel_name: 'wilko_dj', 'event.from_name': 'should be overridden' },
  locale: 'en-US',
};

describe('stageDocument', () => {
  it('renders the alert over the backdrop through the real two-pass pipeline', () => {
    const doc = stageDocument(preview, { alert, variant: preview.triggers[0].variants[0] });
    expect(doc).toContain('<main>wilko_dj live</main>');
    expect(doc).toContain('<div class="stp">member NightOwlNora #ff5e5b wilko_dj</div>');
    expect(doc).toContain('.stp{color:#ff5e5b}');
  });

  it('lays the frame out the way the overlay does: static root first, alert after it, alert sheets last', () => {
    const doc = stageDocument(preview, { alert, variant: preview.triggers[0].variants[0] });
    expect(doc.indexOf('overlay-static-root')).toBeLessThan(doc.indexOf('alert-overlay'));
    expect(doc.indexOf('main{opacity:.9}')).toBeLessThan(doc.indexOf('fonts.example'));
    expect(doc.indexOf('.p-4{padding:1rem}')).toBeLessThan(doc.indexOf('.stp{color'));
  });

  it('encodes event values into the html', () => {
    const doc = stageDocument(preview, { alert, variant: preview.triggers[0].variants[1] });
    expect(doc).toContain('Nora &lt;b&gt;');
    expect(doc).not.toContain('Nora <b>');
  });

  it('shows just the backdrop between alerts', () => {
    const doc = stageDocument(preview, null);
    expect(doc).toContain('<main>wilko_dj live</main>');
    expect(doc).not.toContain('alert-overlay');
  });

  it('makes a replay a different document so the frame restarts its animations', () => {
    const playing = { alert, variant: preview.triggers[0].variants[0] };
    expect(stageDocument(preview, playing, 1)).not.toBe(stageDocument(preview, playing, 2));
  });
});

describe('previewData', () => {
  it('lets the event win over the static samples', () => {
    expect(previewData(preview, { alert, variant: preview.triggers[0].variants[0] })['event.from_name']).toBe('NightOwlNora');
  });
});

describe('playables', () => {
  it('yields one per variant and skips triggers whose alert is not in the kit', () => {
    expect(playables(preview).map((p) => p.variant.key)).toEqual(['first', 'renewal']);
  });
});
