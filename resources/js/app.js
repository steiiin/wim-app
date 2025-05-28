import '../css/app.css';
import './bootstrap';

// Imports
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Vuetify
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
const vuetify = createVuetify({
  components,
  directives
})

// Error logging
async function reportError(payload) {
    await axios.post('/api/client-error', {
        ...payload,
        url:       window.location.href,
        userAgent: navigator.userAgent,
        timestamp: new Date().toISOString(),
    })
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vuetify)

        // errors: Vue render
        app.config.errorHandler = (err, instance, info) => {
            reportError({
                message:   err.message,
                stack:     err.stack,
                info,
                component: instance?.type?.name,
            })
        }

        // errors: general
        window.addEventListener("error", event => {
            reportError({
                message:   event.error?.message,
                stack:     event.error?.stack,
                info: 'general-error',
                component: event.target?.location?.href ,
            })
        });

        // errors: axios errors
        axios.interceptors.response.use(
            response => response,
            error => {
                reportError({
                    message:   error.message,
                    stack:     error.stack,
                    info:      'axios-response',
                    component: error.config?.url,
                })
                return Promise.reject(error)
            }
        )

        return app.mount(el);

    },
    progress: {
        color: '#000000',
    },
});
