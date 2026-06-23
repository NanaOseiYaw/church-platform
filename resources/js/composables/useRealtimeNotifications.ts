/**
 * Subscribes to the current user's private channel and updates the
 * live notification count whenever a new notification arrives.
 *
 * Mount this once in DashboardLayout — it survives across page navigations.
 *
 * Effects:
 *   - Increments `auth.liveNotificationsCount` live (no page reload needed)
 *   - Emits a `notification-received` custom event so NotificationDropdown
 *     can prepend the new notification if it's currently open
 */

import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { getEcho, disconnectEcho } from '@/Services/realtime/echo'
import { Channels, BroadcastEvents } from '@/Services/realtime/channels'
import type { NotificationPayload } from '@/Services/realtime/channels'
import { useAuthStore } from '@/stores/useAuthStore'
import { useNotificationStore } from '@/stores/useNotificationStore'

export function useRealtimeNotifications() {
    const page  = usePage()
    const auth  = useAuthStore()
    const toast = useNotificationStore()

    let channelName: string | null = null

    onMounted(() => {
        const userId = page.props.auth?.user?.id
        const key    = import.meta.env.VITE_REVERB_APP_KEY as string

        if (!userId || !key) return

        channelName = Channels.user(userId)

        try {
            getEcho()
                .private(channelName)
                .listen(`.${BroadcastEvents.NOTIFICATION_NEW}`, (payload: NotificationPayload) => {
                    // 1. Update the live badge count instantly
                    auth.setLiveNotificationsCount(payload.unread_count)

                    // 2. Emit to any open NotificationDropdown so it can prepend
                    window.dispatchEvent(
                        new CustomEvent('realtime:notification', { detail: payload })
                    )
                })
        } catch {
            // WebSocket failures are non-fatal — the app still works via HTTP
        }
    })

    onUnmounted(() => {
        if (channelName) {
            try { getEcho().leave(channelName) } catch { /* ignore */ }
            channelName = null
        }
    })
}
