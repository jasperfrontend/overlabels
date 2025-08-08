import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

export default defineConfig({
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
        rollupOptions: {
            output: {
                manualChunks: {
                    // Separate CodeMirror into its own chunk for better loading
                    codemirror: [
                        'vue-codemirror',
                        '@codemirror/lang-html',
                        '@codemirror/lang-css',
                        '@codemirror/lang-javascript',
                        '@codemirror/theme-one-dark',
                        '@codemirror/basic-setup',
                        '@codemirror/view',
                        '@codemirror/state'
                    ]
                }
            }
        }
    }
});
