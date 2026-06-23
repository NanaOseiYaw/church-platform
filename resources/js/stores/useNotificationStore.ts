import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface AppNotification {
    id: string
    type: 'success' | 'error' | 'warning' | 'info'
    title: string
    message?: string
    duration?: number  // ms, 0 = persistent
}

export const useNotificationStore = defineStore('notifications', () => {
    const toasts = ref<AppNotification[]>([])

    function push(n: Omit<AppNotification, 'id'>) {
        const id = crypto.randomUUID()
        toasts.value.push({ id, duration: 4000, ...n })

        if (n.duration !== 0) {
            setTimeout(() => dismiss(id), n.duration ?? 4000)
        }
    }

    function dismiss(id: string) {
        toasts.value = toasts.value.filter(t => t.id !== id)
    }

    function clear() { toasts.value = [] }

    // Convenience shorthands
    const success = (title: string, message?: string) => push({ type: 'success', title, message })
    const error   = (title: string, message?: string) => push({ type: 'error',   title, message })
    const warning = (title: string, message?: string) => push({ type: 'warning', title, message })
    const info    = (title: string, message?: string) => push({ type: 'info',    title, message })

    const hasToasts = computed(() => toasts.value.length > 0)

    return { toasts, hasToasts, push, dismiss, clear, success, error, warning, info }
})
