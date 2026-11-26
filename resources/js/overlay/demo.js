import { createPinia } from 'pinia';
import { createApp } from 'vue';
import OverlayRenderer from '../components/OverlayRenderer.vue';

/**
 * A product's public demo: the product's real overlay renderer, fed a sample
 * payload built from its recipe (/api/overlay/demo/{product}) instead of an
 * account's, with the framing page playing the designer's scenes into it.
 *
 * No Echo, no token, no health checks: nothing here connects to anything, and
 * nothing a visitor sees is anyone's real data.
 */
document.addEventListener('DOMContentLoaded', () => {
  const mount = document.getElementById('overlay-content');
  const product = window.__OVERLAY_DEMO__?.product;
  if (!mount || !product) return;

  createApp(OverlayRenderer, { slug: product, token: '', sample: true, demo: product }).use(createPinia()).mount(mount);
});
