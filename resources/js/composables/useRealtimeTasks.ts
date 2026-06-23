/**
 * Subscribes to the church channel for live task updates.
 *
 * Mount in any page that shows task lists (Tasks/Index, Dashboard/Home).
 * The callback receives the broadcast payload so the component decides
 * what to do (e.g. emit a toast, mark a row stale, refresh via router).
 *
 * Only events relevant to the current user are surfaced:
 *   - assigned_to === current user → highlight
 *   - others → silently update counters
 */

import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { getEcho } from '@/Services/realtime/echo'
import { Channels, BroadcastEvents } from '@/Services/realtime/channels'
import type { TaskActivityPayload } from '@/Services/realtime/channels'

export function useRealtimeTasks(
    onActivity: (payload: TaskActivityPayload, isForMe: boolean) => void
) {
    const page = usePage()
    let channelName: string | null = null

    onMounted(() => {
        const churchId = page.props.auth?.user?.church_id
        const userId   = page.props.auth?.user?.id
        const key      = import.meta.env.VITE_REVERB_APP_KEY as string

        if (!churchId || !key) return

        channelName = Channels.church(churchId)

        try {
            getEcho()
                .private(channelName)
                .listen(`.${BroadcastEvents.TASK_ACTIVITY}`, (payload: TaskActivityPayload) => {
                    const isForMe = payload.assigned_to === userId
                    onActivity(payload, isForMe)
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
