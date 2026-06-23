/**
 * Base composable for subscribing to a single private channel.
 *
 * Handles:
 *   - Lazy Echo connection
 *   - Automatic channel leave on component unmount
 *   - Guard: no-op if user is not authenticated or Echo key is missing
 *
 * Usage:
 *   const channel = useRealtimeChannel('church.1')
 *   channel?.listen('.task.activity', (payload) => { ... })
 *
 * Note: the leading '.' in the event name is required when using broadcastAs()
 * with a custom name (it disables Laravel's automatic FQCN prefixing).
 */

import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { getEcho } from '@/Services/realtime/echo'

export function useRealtimeChannel(channelName: string) {
    const page = usePage()

    // Only connect when we have a valid user session
    const isReady = (): boolean => {
        const key = import.meta.env.VITE_REVERB_APP_KEY as string
        return !!(key && page.props.auth?.user)
    }

    let channel: ReturnType<ReturnType<typeof getEcho>['private']> | null = null

    onMounted(() => {
        if (!isReady()) return
        try {
            channel = getEcho().private(channelName)
        } catch {
            // Fail silently — WebSocket errors should never crash the page
        }
    })

    onUnmounted(() => {
        if (channel) {
            try {
                getEcho().leave(channelName)
            } catch { /* ignore */ }
            channel = null
        }
    })

    return {
        /** Listen to a custom-named broadcast event (prefix with '.') */
        on<T = unknown>(eventName: string, handler: (payload: T) => void): void {
            channel?.listen(eventName, handler)
        },
        /** Stop listening to a specific event */
        off(eventName: string): void {
            channel?.stopListening(eventName)
        },
    }
}
