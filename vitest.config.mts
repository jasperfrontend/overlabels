import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Deliberately separate from vite.config.mts rather than merged into it.
//
// The build config exists to produce browser bundles: it loads
// laravel-vite-plugin, the Vue SFC compiler and Tailwind, and it shells out to
// `git rev-parse` at module scope to stamp the commit hash. None of that is
// wanted by a unit test run, and the git call in particular would make the
// suite fail outside a checkout.
//
// Two projects:
//
// - `unit` is the pure TypeScript under resources/js: the DSL, the tag parser,
//   the two-pass template renderer, the formatters. Plain functions with no Vue
//   or DOM dependency, so `environment: 'node'`.
// - `dom` is `*.dom.test.ts` only: a component mounted with Vue's own
//   createApp() in happy-dom, for bugs that live in a component's reactivity
//   rather than in a pure function. happy-dom is a simulated DOM, not a browser:
//   no WebGL, no layout, so anything that draws (three.js, canvas) is replaced
//   with a fake in the test. Added Nov 2026 for the Chat Checkin globe missing
//   real checkins (OverlayRenderer.dom.test.ts). A real browser (Vitest Browser
//   Mode) is a separate, still open decision.
const alias = { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) };

export default defineConfig({
    resolve: { alias },
    test: {
        projects: [
            {
                resolve: { alias },
                test: {
                    name: 'unit',
                    environment: 'node',
                    // The Cloudflare front desk Worker is plain functions over
                    // Request and Response, which Node provides, so it is tested
                    // here too.
                    include: ['resources/js/**/*.test.ts', 'cloudflare/**/*.test.ts'],
                    exclude: ['resources/js/**/*.dom.test.ts'],
                },
            },
            {
                plugins: [vue()],
                resolve: { alias },
                test: {
                    name: 'dom',
                    environment: 'happy-dom',
                    include: ['resources/js/**/*.dom.test.ts'],
                },
            },
        ],
    },
});
