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
// Scope is the pure TypeScript under resources/js: the DSL, the tag parser, the
// two-pass template renderer, the formatters. These are plain functions with no
// Vue or DOM dependency, which is why `environment: 'node'` is enough and no
// jsdom is installed. Testing a component would mean adding both, so keep the
// line here: this suite guards template/tag semantics, not rendering.
export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
