import { controlsToPreviewData, type PreviewableControl } from '@/utils/controlPreview';
import { renderTemplateSource } from '@/utils/renderTemplate';

/**
 * Plays a kit's alerts with sample data, without anything being installed.
 *
 * The server (App\Services\Kits\KitPreview) sends each alert's source, an
 * optional static overlay to play it over, and per trigger the sample `event.*`
 * tags a real event produces (App\Services\Alerts\AlertEventSamples). This
 * module turns one of those into a complete HTML document for a sandboxed
 * frame, laid out the way the OBS overlay lays it out: base styles, the static
 * overlay's head and CSS, then the alert's head and CSS, the static root, and
 * the alert container after it.
 *
 * Rendering goes through renderTemplateSource(), the same two-pass pipeline
 * the overlay and the editor preview use, so a kit cannot look one way here
 * and another on stream. What it cannot show: anything an Expression Control
 * animates (no engine runs here), live Twitch values (deliberately never), and
 * a service control's real number (`c:kofi:*` renders as its sample or empty).
 */

export interface PreviewSource {
  head: string;
  html: string;
  css: string;
  compiled_css: string;
  controls: PreviewableControl[];
}

export interface PreviewAlert extends PreviewSource {
  id: number;
  name: string;
  version: string;
  sound_url: string | null;
}

export interface PreviewVariant {
  key: string;
  label: string;
  tags: Record<string, unknown>;
}

export interface PreviewWiring {
  state: 'wired' | 'off' | 'other' | 'free';
  other_alert: string | null;
  other_alert_id: number | null;
  connected: boolean;
  connect_url: string | null;
}

export interface PreviewTrigger {
  template_id: number;
  service: string | null;
  event_type: string | null;
  duration_ms: number;
  label: string;
  variants: PreviewVariant[];
  wiring?: PreviewWiring;
}

export interface KitPreviewData {
  alerts: PreviewAlert[];
  backdrop: (PreviewSource & { name: string }) | null;
  triggers: PreviewTrigger[];
  declared: boolean;
  sample: Record<string, unknown>;
  locale: string;
}

export interface PlayingAlert {
  alert: PreviewAlert;
  variant: PreviewVariant;
}

/** The OBS browser source the preview stands in for. */
export const STAGE_SIZE = { w: 1920, h: 1080 } as const;

/**
 * The overlay page's own base rules (authenticate.blade.php and the renderer's
 * injected base sheet), so a template sits in the same box it does in OBS.
 */
const BASE_CSS =
  'html,body{margin:0;padding:0;width:1920px;height:1080px;overflow:hidden;background:transparent}' +
  '.overlay-static-root{display:contents}' +
  '.overlay-emote{display:inline;vertical-align:middle;height:1.5em}';

/** Sample static tags, then the backdrop's and the alert's own controls, then the event: the same precedence the overlay merges in. */
export function previewData(preview: KitPreviewData, playing: PlayingAlert | null): Record<string, unknown> {
  return {
    ...preview.sample,
    ...(preview.backdrop ? controlsToPreviewData(preview.backdrop.controls) : {}),
    ...(playing ? controlsToPreviewData(playing.alert.controls) : {}),
    ...(playing ? playing.variant.tags : {}),
  };
}

const style = (css: string) => (css ? `<style>${css}</style>` : '');

/**
 * The whole frame document. `nonce` only exists to make two identical plays
 * produce different documents, so the frame reloads and every animation
 * starts again from zero.
 */
export function stageDocument(preview: KitPreviewData, playing: PlayingAlert | null, nonce = 0): string {
  const data = previewData(preview, playing);
  const locale = preview.locale || 'en-US';
  const backdrop = preview.backdrop;

  const head = [
    '<meta charset="utf-8">',
    style(BASE_CSS),
    backdrop?.head ?? '',
    style(backdrop?.compiled_css ?? ''),
    style(backdrop ? renderTemplateSource(backdrop.css, data, locale, false) : ''),
    playing?.alert.head ?? '',
    style(playing?.alert.compiled_css ?? ''),
    style(playing ? renderTemplateSource(playing.alert.css, data, locale, false) : ''),
  ].join('');

  const body = [
    `<div class="overlay-static-root">${backdrop ? renderTemplateSource(backdrop.html, data, locale, true) : ''}</div>`,
    playing
      ? `<div class="alert-overlay"><div class="alert-content">${renderTemplateSource(playing.alert.html, data, locale, true)}</div></div>`
      : '',
  ].join('');

  return `<!doctype html><html><head>${head}</head><body><!-- ${nonce} -->${body}</body></html>`;
}

/** Every playable moment in a preview, in button order: one per trigger variant. */
export function playables(preview: KitPreviewData): Array<{ trigger: PreviewTrigger; variant: PreviewVariant; alert: PreviewAlert }> {
  const out: Array<{ trigger: PreviewTrigger; variant: PreviewVariant; alert: PreviewAlert }> = [];
  for (const trigger of preview.triggers) {
    const alert = preview.alerts.find((a) => a.id === trigger.template_id);
    if (!alert) continue;
    for (const variant of trigger.variants) out.push({ trigger, variant, alert });
  }
  return out;
}
