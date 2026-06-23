<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import {
    Bell, CheckSquare, Megaphone, CalendarDays, Building2,
    CheckCheck, Circle
} from 'lucide-vue-next'
import type { InAppNotification, InAppNotificationType } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    notifications: {
        data:         InAppNotification[]
        links:        { url: string | null; label: string; active: boolean }[]
        current_page: number
        last_page:    number
        total:        number
        per_page:     number
    }
    unreadCount: number
    filter:      string
}>()

// ── Tab navigation ─────────────────────────────────────────────────────────────

function setFilter(f: string) {
    router.get('/dashboard/notifications', f !== 'all' ? { filter: f } : {}, {
        preserveState: true,
        replace:       true,
    })
}

// ── Mark read ──────────────────────────────────────────────────────────────────

async function markRead(n: InAppNotification) {
    if (n.read_at) return
    n.read_at = new Date().toISOString()
    try {
        await window.axios.patch(`/dashboard/notifications/${n.id}/read`)
        router.reload({ only: ['notifications', 'unreadCount'] })
    } catch {
        n.read_at = null
    }
}

async function markAllRead() {
    props.notifications.data.forEach(n => { n.read_at = n.read_at ?? new Date().toISOString() })
    try {
        await window.axios.post('/dashboard/notifications/read-all')
        router.reload({ only: ['notifications', 'unreadCount'] })
    } catch { /* swallow */ }
}

function onRowClick(n: InAppNotification) {
    markRead(n)
    if (n.data.action_url) {
        router.visit(n.data.action_url)
    }
}

// ── Type → icon + colour mapping ──────────────────────────────────────────────

type IconConfig = { icon: any; bg: string; text: string }

const TYPE_ICONS: Record<InAppNotificationType | string, IconConfig> = {
    task_assigned:           { icon: CheckSquare,  bg: 'bg-brand-100',   text: 'text-brand-600'   },
    task_updated:            { icon: CheckSquare,  bg: 'bg-neutral-100', text: 'text-neutral-600' },
    task_completed:          { icon: CheckSquare,  bg: 'bg-emerald-100', text: 'text-emerald-600' },
    announcement_published:  { icon: Megaphone,    bg: 'bg-amber-100',   text: 'text-amber-600'   },
    event_created:           { icon: CalendarDays, bg: 'bg-brand-100',  text: 'text-brand-600'  },
    event_updated:           { icon: CalendarDays, bg: 'bg-brand-100',  text: 'text-brand-600'  },
    department_member_added: { icon: Building2,    bg: 'bg-sky-100',     text: 'text-sky-600'     },
    department_role_changed: { icon: Building2,    bg: 'bg-sky-100',     text: 'text-sky-600'     },
}

function iconFor(type: string): IconConfig {
    return TYPE_ICONS[type] ?? { icon: Bell, bg: 'bg-neutral-100', text: 'text-neutral-500' }
}

// ── Date grouping ──────────────────────────────────────────────────────────────

interface Group {
    label:         string
    notifications: InAppNotification[]
}

const groups = computed<Group[]>(() => {
    const now       = new Date()
    const todayStr  = now.toDateString()
    const yesterday = new Date(now); yesterday.setDate(now.getDate() - 1)
    const yestStr   = yesterday.toDateString()
    const weekAgo   = new Date(now); weekAgo.setDate(now.getDate() - 7)

    const buckets: Record<string, InAppNotification[]> = {
        Today:      [],
        Yesterday:  [],
        'This week': [],
        Older:      [],
    }

    for (const n of props.notifications.data) {
        const d    = new Date(n.created_at)
        const dStr = d.toDateString()
        if (dStr === todayStr)       buckets['Today'].push(n)
        else if (dStr === yestStr)   buckets['Yesterday'].push(n)
        else if (d >= weekAgo)       buckets['This week'].push(n)
        else                         buckets['Older'].push(n)
    }

    return Object.entries(buckets)
        .filter(([, list]) => list.length > 0)
        .map(([label, notifications]) => ({ label, notifications }))
})

// ── Time formatting ────────────────────────────────────────────────────────────

function relativeTime(iso: string): string {
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
    if (diff < 60)    return 'just now'
    if (diff < 3600)  return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function typeLabel(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())
}
</script>

<template>
    <DashboardLayout
        title="Notifications"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Notifications' }]"
    >
        <div class="max-w-3xl mx-auto space-y-6">

            <!-- ── Page header ──────────────────────────────────────────────── -->
            <PageHeader title="Notifications">
                <template #actions>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:text-neutral-900 border border-neutral-200 rounded-lg hover:bg-neutral-50 transition-colors"
                        @click="markAllRead"
                    >
                        <CheckCheck class="w-4 h-4" />
                        Mark all read
                    </button>
                </template>
            </PageHeader>

            <!-- ── Tabs ──────────────────────────────────────────────────────── -->
            <div class="flex items-center gap-1 bg-neutral-100 rounded-xl p-1 w-fit">
                <button
                    v-for="tab in [{ key: 'all', label: 'All' }, { key: 'unread', label: 'Unread' }]"
                    :key="tab.key"
                    type="button"
                    :class="[
                        'flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium rounded-lg transition-colors',
                        filter === tab.key
                            ? 'bg-white text-neutral-900 shadow-sm'
                            : 'text-neutral-500 hover:text-neutral-700',
                    ]"
                    @click="setFilter(tab.key)"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.key === 'unread' && unreadCount > 0"
                        class="min-w-[18px] h-[18px] bg-brand-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1"
                    >
                        {{ unreadCount > 99 ? '99+' : unreadCount }}
                    </span>
                </button>
            </div>

            <!-- ── Notification groups ────────────────────────────────────────── -->
            <div
                v-if="notifications.data.length > 0"
                class="space-y-6"
            >
                <section v-for="group in groups" :key="group.label">
                    <!-- Group label -->
                    <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2 px-1">
                        {{ group.label }}
                    </p>

                    <!-- Notification rows -->
                    <div class="bg-white border border-neutral-100 rounded-2xl overflow-hidden divide-y divide-neutral-50">
                        <div
                            v-for="n in group.notifications"
                            :key="n.id"
                            class="relative flex items-start gap-4 px-5 py-4 transition-colors"
                            :class="[
                                n.data.action_url ? 'cursor-pointer' : 'cursor-default',
                                n.read_at ? 'hover:bg-neutral-50/60' : 'bg-brand-50/40 hover:bg-brand-50/70',
                            ]"
                            @click="onRowClick(n)"
                        >
                            <!-- Unread dot -->
                            <span
                                v-if="!n.read_at"
                                class="absolute left-1.5 top-1/2 -translate-y-1/2 w-1.5 h-1.5 bg-brand-500 rounded-full shrink-0"
                            />

                            <!-- Type icon -->
                            <div
                                :class="[
                                    'w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
                                    iconFor(n.data.type).bg,
                                ]"
                            >
                                <component
                                    :is="iconFor(n.data.type).icon"
                                    :class="['w-4 h-4', iconFor(n.data.type).text]"
                                />
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p
                                            class="text-sm leading-snug"
                                            :class="n.read_at ? 'text-neutral-700 font-normal' : 'text-neutral-900 font-semibold'"
                                        >
                                            {{ n.data.title }}
                                        </p>
                                        <p class="text-sm text-neutral-500 leading-snug mt-0.5 line-clamp-2">
                                            {{ n.data.body }}
                                        </p>
                                    </div>

                                    <!-- Right: time + mark-read -->
                                    <div class="flex flex-col items-end gap-1.5 shrink-0">
                                        <span class="text-xs text-neutral-400 whitespace-nowrap">
                                            {{ relativeTime(n.created_at) }}
                                        </span>
                                        <button
                                            v-if="!n.read_at"
                                            type="button"
                                            class="flex items-center gap-1 text-[11px] font-medium text-neutral-400 hover:text-brand-600 transition-colors"
                                            title="Mark as read"
                                            @click.stop="markRead(n)"
                                        >
                                            <Circle class="w-3 h-3 fill-brand-500 text-brand-500" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Type chip -->
                                <div class="mt-1.5">
                                    <span class="text-[10px] font-medium text-neutral-400 bg-neutral-100 rounded px-1.5 py-0.5">
                                        {{ typeLabel(n.data.type) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ── Empty state ────────────────────────────────────────────────── -->
            <div
                v-else
                class="bg-white border border-neutral-100 rounded-2xl flex flex-col items-center justify-center py-16 px-6 text-center"
            >
                <div class="w-12 h-12 bg-neutral-100 rounded-full flex items-center justify-center mb-4">
                    <Bell class="w-6 h-6 text-neutral-400" />
                </div>
                <p class="text-sm font-semibold text-neutral-700">
                    {{ filter === 'unread' ? 'All caught up!' : 'No notifications yet' }}
                </p>
                <p class="text-sm text-neutral-400 mt-1">
                    {{ filter === 'unread'
                        ? 'You have no unread notifications.'
                        : 'Notifications for tasks, events, and more will appear here.' }}
                </p>
                <button
                    v-if="filter === 'unread'"
                    type="button"
                    class="mt-4 text-sm font-medium text-brand-600 hover:text-brand-700 transition-colors"
                    @click="setFilter('all')"
                >
                    View all notifications →
                </button>
            </div>

            <!-- ── Pagination ─────────────────────────────────────────────────── -->
            <AppPagination
                v-if="notifications.last_page > 1"
                :links="notifications.links"
                :current-page="notifications.current_page"
                :last-page="notifications.last_page"
                :total="notifications.total"
                :per-page="notifications.per_page"
            />

        </div>
    </DashboardLayout>
</template>
