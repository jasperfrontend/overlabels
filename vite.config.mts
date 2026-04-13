import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { execSync } from 'child_process';
import { defineConfig } from 'vite';

let commitHash = 'dev';
try {
    commitHash = execSync('git rev-parse --short HEAD').toString().trim();
} catch {
    commitHash = (process.env.RAILWAY_GIT_COMMIT_SHA ?? 'dev').substring(0, 7);
}

export default defineConfig(({ isSsrBuild }) => ({
    define: {
        __COMMIT_HASH__: JSON.stringify(commitHash),
    },
    plugins: [
        laravel({
            input: ['resources/js/app.ts', 'resources/js/overlay/app.js'],
            ssr: 'resources/js/ssr.ts',
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
        rollupOptions: isSsrBuild
            ? {}
            : {
                output: {
                    manualChunks: {
                        codemirror: [
                            'vue-codemirror',
                            'codemirror',
                            '@codemirror/lang-html',
                            '@codemirror/lang-css',
                            '@codemirror/lang-javascript',
                            '@codemirror/theme-one-dark',
                            '@codemirror/view',
                            '@codemirror/state'
                        ],
                        websocket: ['pusher-js', 'laravel-echo'],
                    }
                }
            }
    }
}));
