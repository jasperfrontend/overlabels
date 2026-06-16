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
                'resources/js/overlay/app.js',
                'resources/js/map/app.ts',
                'resources/js/help-reference/main.ts',
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
                // Rolldown (Vite 8) only accepts the function form of manualChunks.
                // Match by node_modules path to preserve the previous object grouping.
                manualChunks(id) {
                    if (!id.includes('node_modules')) return;
                    if (id.includes('/vue-codemirror/') || id.includes('/codemirror/') || id.includes('/@codemirror/')) {
                        return 'codemirror';
                    }
                    if (id.includes('/pusher-js/') || id.includes('/laravel-echo/')) {
                        return 'websocket';
                    }
                    if (id.includes('/leaflet/')) {
                        return 'leaflet';
                    }
                },
            },
        },
    },
}));
