import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { vFocusRestore } from '@/directives/focusRestore';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

createInertiaApp({
    title: (title) => `${title} | ${appName}`,
    resolve: (name) => {
        const page = resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        );
        page.then((module) => {
            // 認証ページ（Auth/で始まるページ）にはデフォルトレイアウトを適用しない
            if (module.default.layout === undefined && !name.startsWith('Auth/')) {
                module.default.layout = AuthenticatedLayout;
            }
        });
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .directive('focus-restore', vFocusRestore)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
