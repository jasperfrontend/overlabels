/**
 * A chat game's live demo (resources/views/welcome/demos/_overlay-demo.blade.php):
 * the product's real overlay in a frame (/products/{slug}/demo-frame, the
 * renderer's `demo` mode), with the product designer's scenes played into it
 * one after another, on a loop, through the same `preview-scene` message the
 * designer sends. The overlay runs its own handlers on them, so the demo is
 * the product.
 *
 * Started by a click on the homepage, so a page with several demos loads none
 * of them up front; on a product page as soon as it is in view. The loop
 * pauses while the demo is off screen.
 */

interface DemoScene {
  key: string;
  events: unknown[];
}

interface DemoFrame {
  product: string;
  scenes: DemoScene[];
  stage: { w: number; h: number };
}

/** How long a scene plays before the next. A topple needs its fall and banner. */
const SCENE_MS: Record<string, number> = {
  toppled: 7000,
  record: 6000,
  bowl: 9000,
};
const DEFAULT_SCENE_MS = 4500;

export function initOverlayDemos(): void {
  document.querySelectorAll<HTMLElement>('[data-overlay-demo]').forEach((stage) => {
    let frame: DemoFrame;
    try {
      frame = JSON.parse(stage.dataset.overlayDemo ?? '');
    } catch {
      return;
    }
    if (!frame.scenes?.length || !stage.dataset.overlayDemoSrc) return;

    const button = stage.querySelector<HTMLButtonElement>('[data-overlay-demo-start]');
    let started = false;

    const start = () => {
      if (started) return;
      started = true;
      button?.remove();
      run(stage, frame, stage.dataset.overlayDemoSrc as string);
    };

    button?.addEventListener('click', start);

    if (stage.dataset.overlayDemoAutostart === '1') {
      const observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
          observer.disconnect();
          start();
        }
      });
      observer.observe(stage);
    }
  });
}

function run(stage: HTMLElement, frame: DemoFrame, src: string): void {
  const iframe = document.createElement('iframe');
  iframe.src = src;
  iframe.title = `${frame.product} demo`;
  iframe.setAttribute('aria-hidden', 'true');
  iframe.tabIndex = -1;
  Object.assign(iframe.style, {
    position: 'absolute',
    top: '0',
    left: '0',
    border: '0',
    width: `${frame.stage.w}px`,
    height: `${frame.stage.h}px`,
    transformOrigin: 'top left',
    pointerEvents: 'none',
    opacity: '0',
    transition: 'opacity 300ms ease',
  });

  // The overlay is drawn for its stage size (1920x1080) and scaled to fit.
  const fit = () => {
    iframe.style.transform = `scale(${stage.clientWidth / frame.stage.w})`;
  };
  fit();
  new ResizeObserver(fit).observe(stage);
  stage.appendChild(iframe);

  let index = 0;
  let timer: ReturnType<typeof setTimeout> | null = null;
  let visible = true;
  let ready = false;

  const play = () => {
    if (!ready || !visible || !iframe.contentWindow) return;
    const scene = frame.scenes[index % frame.scenes.length];
    index++;
    // A plain copy: the events are posted to another window.
    iframe.contentWindow.postMessage({ ol: 'preview-scene', events: JSON.parse(JSON.stringify(scene.events)) }, window.location.origin);
    timer = setTimeout(play, SCENE_MS[scene.key] ?? DEFAULT_SCENE_MS);
  };

  window.addEventListener('message', (event) => {
    if (event.source !== iframe.contentWindow || event.data?.ol !== 'demo-ready' || ready) return;
    ready = true;
    iframe.style.opacity = '1';
    stage.querySelector<HTMLElement>('[data-overlay-demo-poster]')?.remove();
    play();
  });

  new IntersectionObserver((entries) => {
    visible = entries.some((entry) => entry.isIntersecting);
    if (!visible && timer) {
      clearTimeout(timer);
      timer = null;
    } else if (visible && !timer) {
      play();
    }
  }).observe(stage);
}
