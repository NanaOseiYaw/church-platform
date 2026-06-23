<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { RefreshCw, Unlink, ExternalLink, Youtube, CheckCircle, Clock } from 'lucide-vue-next'
import { useNotificationStore } from '@/stores/useNotificationStore'
import type { ChannelConnection } from '@/types'

const props = defineProps<{
    connection: ChannelConnection
    canManage?: boolean
}>()

const notify = useNotificationStore()

function sync() {
    router.post(`/dashboard/sermons/channel/${props.connection.id}/sync`, {}, {
        preserveScroll: true,
        onSuccess: () => notify.success('Sync queued — refresh in a moment.'),
        onError:   () => notify.error('Could not trigger sync.'),
    })
}

function disconnect() {
    if (!confirm(`Disconnect "${props.connection.channel_title}"? Synced sermons will be preserved.`)) return
    router.delete(`/dashboard/sermons/channel/${props.connection.id}`, {
        preserveScroll: true,
        onSuccess: () => notify.success('Channel disconnected.'),
    })
}

const providerLabel: Record<string, string> = {
    youtube: 'YouTube',
    vimeo:   'Vimeo',
}
</script>

<template>
    <div class="bg-white border border-neutral-100 rounded-2xl p-5 flex items-start gap-4">

        <!-- Channel thumbnail -->
        <div class="w-12 h-12 rounded-xl overflow-hidden bg-neutral-100 shrink-0 flex items-center justify-center">
            <img
                v-if="connection.channel_thumbnail"
                :src="connection.channel_thumbnail"
                :alt="connection.channel_title ?? ''"
                class="w-full h-full object-cover"
            />
            <Youtube v-else class="w-6 h-6 text-red-500" />
        </div>

        <!-- Info -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                <h3 class="text-sm font-semibold text-neutral-900 truncate">
                    {{ connection.channel_title ?? connection.channel_id }}
                </h3>
                <span class="text-[10px] font-semibold uppercase tracking-wide bg-red-50 text-red-600 px-2 py-0.5 rounded-full">
                    {{ providerLabel[connection.provider] ?? connection.provider }}
                </span>
                <span
                    :class="[
                        'text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full',
                        connection.is_active
                            ? 'bg-green-50 text-green-600'
                            : 'bg-neutral-100 text-neutral-500',
                    ]"
                >
                    {{ connection.is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            <!-- Stats row -->
            <div class="flex items-center gap-3 text-xs text-neutral-400 flex-wrap">
                <span v-if="connection.video_count !== null" class="flex items-center gap-1">
                    <CheckCircle class="w-3 h-3 text-green-400" />
                    {{ connection.video_count }} videos
                </span>
                <span v-if="connection.last_synced_at_formatted" class="flex items-center gap-1">
                    <RefreshCw class="w-3 h-3" />
                    Synced {{ connection.last_synced_at_formatted }}
                </span>
                <span v-else class="flex items-center gap-1">
                    <Clock class="w-3 h-3" />
                    Never synced
                </span>
                <span v-if="connection.next_sync_at_formatted" class="text-neutral-300">
                    · next {{ connection.next_sync_at_formatted }}
                </span>
            </div>
        </div>

        <!-- Actions -->
        <div v-if="canManage" class="flex items-center gap-1 shrink-0">
            <a
                v-if="connection.channel_url"
                :href="connection.channel_url"
                target="_blank"
                rel="noopener noreferrer"
                class="p-2 rounded-lg text-neutral-400 hover:text-neutral-700 hover:bg-neutral-50 transition-colors"
                title="Open channel"
            >
                <ExternalLink class="w-4 h-4" />
            </a>
            <button
                type="button"
                class="p-2 rounded-lg text-neutral-400 hover:text-brand-600 hover:bg-brand-50 transition-colors"
                title="Sync now"
                @click="sync"
            >
                <RefreshCw class="w-4 h-4" />
            </button>
            <button
                type="button"
                class="p-2 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                title="Disconnect channel"
                @click="disconnect"
            >
                <Unlink class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
