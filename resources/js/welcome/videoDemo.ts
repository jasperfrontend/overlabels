/**
 * A product demo that is a recording rather than the live overlay
 * (welcome/demos/snow.blade.php). The video is `preload="none"`, so nothing
 * downloads until it starts: a click on the homepage, or scrolling into view
 * on a product page (`data-video-demo-autostart="1"`). Once playing it pauses
 * off screen and resumes in view, the way the live demos' loop does.
 */
export function initVideoDemos(): void {
  document.querySelectorAll<HTMLElement>('[data-video-demo]').forEach((stage) => {
    const video = stage.querySelector('video');
    if (!video) return;
    const button = stage.querySelector<HTMLButtonElement>('[data-video-demo-start]');
    let started = false;

    const play = () => void video.play().catch(() => undefined);

    const start = () => {
      if (started) return;
      started = true;
      button?.remove();
      play();
      new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) play();
        else video.pause();
      }).observe(stage);
    };

    button?.addEventListener('click', start);

    if (stage.dataset.videoDemoAutostart === '1') {
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
