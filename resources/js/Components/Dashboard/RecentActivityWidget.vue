<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ShieldCheck } from 'lucide-vue-next'

interface ActivityEntry {
    id: number
    action: string
    target_name: string | null
    created_at: string
    actor: { id: number; name: string; avatar: string | null } | null
}

defineProps<{ activities: ActivityEntry[] }>()

function moduleColor(action: string): string {
    const prefix = action.split('.')[0]
    const colors: Record<string, string> = {
        auth:         'bg-violet-400',
        member:       'bg-blue-400',
        department:   'bg-brand-400',
        announcement: 'bg-amber-400',
        event:        'bg-green-400',
        task:         'bg-orange-400',
        attendance:   'bg-cyan-400',
        schedule:     'bg-brand-400',
        sermon:       'bg-rose-400',
        settings:     'bg-gray-400',
        file:         'bg-slate-400',
        media:        'bg-slate-400',
        notification: 'bg-teal-400',
    }
    return colors[prefix] ?? 'bg-gray-400'
}

function formatAction(action: string): string {
    return action.split('.').map(p => p.replace(/_/g, ' ')).join(' › ')
}

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime()
    const mins  = Math.floor(diff / 60000)
    if (mins < 1)  return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs   = Math.floor(mins / 60)
    if (hrs < 24)  return `${hrs}h ago`
    return `${Math.floor(hrs / 24)}d ago`
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3.5">
            <div class="flex items-center gap-2">
                <ShieldCheck class="h-4 w-4 text-gray-400" />
                <h2 class="text-sm font-semibold text-gray-900">Recent Activity</h2>
            </div>
            <Link href="/dashboard/audit" class="text-xs text-brand-600 hover:underline">
                View all →
            </Link>
        </div>

        <!-- Empty state -->
        <div v-if="activities.length === 0" class="px-5 py-6 text-center">
            <p class="text-xs text-gray-400">No recent activity.</p>
        </div>

        <!-- Activity list -->
        <ul v-else class="divide-y divide-gray-50">
            <li
                v-for="entry in activities"
                :key="entry.id"
                class="flex items-start gap-3 px-5 py-3"
            >
                <!-- Module colour dot -->
                <span
                    :class="['mt-1.5 h-2 w-2 flex-shrink-0 rounded-full', moduleColor(entry.action)]"
                />

                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-medium capitalize text-gray-800">
                        {{ formatAction(entry.action) }}
                        <span v-if="entry.target_name" class="font-normal text-gray-500">
                            — {{ entry.target_name }}
                        </span>
                    </p>
                    <p class="text-[11px] text-gray-400">{{ entry.actor?.name ?? 'System' }}</p>
                </div>

                <span class="shrink-0 text-[11px] text-gray-400">{{ timeAgo(entry.created_at) }}</span>
            </li>
        </ul>
    </div>
</template>
