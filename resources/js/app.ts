import './bootstrap'
import '../css/app.css'

import { createApp, h, DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createPinia } from 'pinia'
import { ZiggyVue } from 'ziggy-js'

const pinia = createPinia()

createInertiaApp({
    // Title is assembled by each layout using reactive church.name from useChurch().
    // Returning the raw title avoids hardcoding any church name at boot time.
    title: (title) => title ?? '',
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .mount(el)
    },
    progress: {
        // Neutral default — the brand CSS vars override on page load once the app hydrates.
        color: '#6366f1',
        showSpinner: false,
    },
})
