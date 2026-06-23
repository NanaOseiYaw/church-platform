import axios from 'axios'

declare global {
    interface Window {
        axios: typeof axios
    }

    function route(name: string, params?: Record<string, any> | any[] | string | number): string
}

declare module '@inertiajs/vue3' {
    interface PageProps {
        church:        import('./index').ChurchBranding
        flash:         import('./index').FlashMessages
        auth:          import('./index').SharedProps['auth']
        impersonating: string | null
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        route(name: string, params?: Record<string, any> | any[] | string | number): string
    }
}
