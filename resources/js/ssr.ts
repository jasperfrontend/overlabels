import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { route as ziggyRoute, ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Overlabels';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => title ? `${title} - ${appName}` : appName,
        resolve: resolvePage,
        setup: ({ App, props, plugin }) => {
            const ziggyConfig = {
                ...page.props.ziggy,
                location: new URL(page.props.ziggy.location),
            };
            // Client-side, ZiggyVue assigns `route` to window so bare `route()` calls in
            // <script setup> resolve. Mirror that here for SSR.
            (globalThis as unknown as { route: typeof ziggyRoute }).route = (
                name?: string,
                params?: Parameters<typeof ziggyRoute>[1],
                absolute?: boolean,
            ) => ziggyRoute(name, params, absolute, ziggyConfig);
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, ziggyConfig);
        },
    }),
    { cluster: true },
);

function resolvePage(name: string) {
    const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

    return resolvePageComponent<DefineComponent>(`./pages/${name}.vue`, pages);
}
