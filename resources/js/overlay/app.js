import { createApp } from 'vue'
import OverlayRenderer from '../components/OverlayRenderer.vue'

// Mount the Vue app once the DOM is ready and window.__OVERLAY__ is available
document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('overlay-content')
    if (!mount || !window.__OVERLAY__) return

    const { slug, token } = window.__OVERLAY__

    createApp(OverlayRenderer, { slug, token }).mount(mount)
})
