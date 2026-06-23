/**
 * Subscribes to an attendance session's private channel and calls a
 * callback with updated stats whenever another user saves attendance.
 *
 * Mount in Attendance/Show.vue so all coordinators watching the same
 * session see each other's check-ins instantly.
 *
 * The sessionId must be a UUID string (attendances use UUID primary keys).
 */

import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { getEcho } from '@/Services/realtime/echo'
import { Channels, BroadcastEvents } from '@/Services/realtime/channels'
import type { AttendanceUpdatedPayload } from '@/Services/realtime/channels'

export function useRealtimeAttendance(
    sessionId: string,
    onUpdate: (payload: AttendanceUpdatedPayload) => void,
) {
    const page = usePage()
    let channelName: string | null = null

    onMounted(() => {
        const key = import.meta.env.VITE_REVERB_APP_KEY as string
        if (!key || !page.props.auth?.user || !sessionId) return

        channelName = Channels.attendance(sessionId)

        try {
            getEcho()
                .private(channelName)
                .listen(`.${BroadcastEvents.ATTENDANCE_UPDATED}`, (payload: AttendanceUpdatedPayload) => {
                    // Guard: only process events for this specific session
                    if (payload.session_id === sessionId) {
                        onUpdate(payload)
                    }
                })
        } catch { /* non-fatal */ }
    })

    onUnmounted(() => {
        if (channelName) {
            try { getEcho().leave(channelName) } catch { /* ignore */ }
            channelName = null
        }
    })
}
