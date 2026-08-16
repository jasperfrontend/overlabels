import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { execSync } from 'child_process';
import { defineConfig } from 'vite';

let commitHash = 'dev';
try {
    commitHash = execSync('git rev-parse --short HEAD').toString().trim();
} catch {
    commitHash = (process.env.APP_COMMIT_SHA ?? process.env.RAILWAY_GIT_COMMIT_SHA ?? 'dev').substring(0, 7);
}

export default defineConfig(() => ({
    define: {
        __COMMIT_HASH__: JSON.stringify(commitHash),
    },
    plugins: [
        laravel({
            input: [
                'resources/js/app.ts',
                'resources/js/welcome/app.ts',
                'resources/js/overlay/app.js',
                'resources/js/map/app.ts',
                'resources/js/events-feed/app.ts',
                'resources/js/help/main.ts',
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    build: {
        chunkSizeWarningLimit: 1000,
        rollupOptions: {
            output: {
                // Preserve function and class `name` values through minification.
                //
                // NOT cosmetic, and not for debugging. `@mkody/twitch-emoticons`
                // guards its abstract base class with
                // `if (new.target.name === Emote.name) throw`, and the minifier
                // renamed the base class AND all four subclasses to `e`. That
                // made the guard true for every subclass, so constructing any
                // BTTV/FFZ/7TV emote threw "Base Emote class cannot be used".
                //
                // It only broke in production - dev is unminified - and it was
                // silent, because useEmoteParser wraps the fetches in
                // Promise.allSettled. Symptom was third-party emotes rendering
                // as plain text on prod while Twitch emotes worked, since those
                // come from IRC tag positions and never construct a library
                // object.
                //
                // The library's own build script uses esbuild `--keep-names` for
                // exactly this reason. Bundling its `src/` ourselves opted us out.
                //
                // Verify with a build, not by reading: `class e extends n` in the
                // emote chunk means it has regressed.
                keepNames: true,

                // Rolldown's native chunking API, NOT rollup's `manualChunks`.
                //
                // `manualChunks` is a compatibility shim here and it silently
                // could not do this job: returning a chunk name for a vendor
                // package works (codemirror/websocket/leaflet all landed), but
                // returning one for Vue was ignored outright, with no warning and
                // no build error. Vue stayed welded into the codemirror chunk and
                // the only symptom was a 678 kB download.
                //
                // Which mattered because Vue had no explicit home, so Rolldown
                // parked it inside the first manual chunk it could - codemirror.
                // Every entry needs Vue, so every entry pulled codemirror with it:
                // the overlay (no editor anywhere in it) and the dashboard's first
                // paint (the editor lives on lazily-loaded pages) both paid 237 kB
                // gzipped for a code editor they were not showing.
                //
                // Vue is listed FIRST and that ordering is the fix. Groups are
                // evaluated in order, so Vue claims its modules before the
                // codemirror pattern can absorb them.
                //
                // If you add a group, verify with a build rather than by reading:
                // `manualChunks` looked correct for months while doing nothing.
                advancedChunks: {
                    groups: [
                        { name: 'vue', test: /node_modules[\\/](@vue|vue)[\\/]/ },
                        { name: 'codemirror', test: /node_modules[\\/](vue-codemirror|codemirror|@codemirror)[\\/]/ },
                        { name: 'websocket', test: /node_modules[\\/](pusher-js|laravel-echo)[\\/]/ },
                        { name: 'leaflet', test: /node_modules[\\/]leaflet[\\/]/ },
                    ],
                },
            },
        },
    },
}));
