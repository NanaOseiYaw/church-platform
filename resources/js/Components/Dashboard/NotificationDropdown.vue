<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { onClickOutside } from '@vueuse/core'
import { useAuthStore } from '@/stores/useAuthStore'
import type { NotificationPayload } from '@/Services/realtime/channels'
import {
    Bell, CheckSquare, Megaphone, CalendarDays, Building2,
    CheckCheck, X, ExternalLink, Loader2, CalendarCheck2, Mic2, AlertCircle, Radio, Shield, Heart,
} from 'lucide-vue-next'
import type { InAppNotification, InAppNotificationType } from '@/types'

// ── Auth store (for the badge count) ──────────────────────────────────────────

const auth = useAuthStore()

// ── Dropdown state ─────────────────────────────────────────────────────────────

const open          = ref(false)
const loading       = ref(false)
const loaded        = ref(false)
const notifications = ref<InAppNotification[]>([])
const dropdownRef   = ref<HTMLElement | null>(null)

onClickOutside(dropdownRef, () => { open.value = false })

// ── Open / fetch ───────────────────────────────────────────────────────────────

async function toggle() {
    open.value = !open.value
    if (open.value && !loaded.value) {
        await fetchRecent()
    }
}

async function fetchRecent() {
    loading.value = true
    try {
        const { data } = await window.axios.get<{
            notifications: InAppNotification[]
            unread_count:  number
        }>('/dashboard/notifications/recent')
        notifications.value = data.notifications
        loaded.value = true
    } catch {
        // Silently fail — badge still shows count
    } finally {
        loading.value = false
    }
}

// ── Mark read ──────────────────────────────────────────────────────────────────

async function markRead(n: InAppNotification) {
    if (n.read_at) return
    n.read_at = new Date().toISOString()
    try {
        await window.axios.patch(`/dashboard/notifications/${n.id}/read`)
        router.reload({ only: ['auth'] })
    } catch {
        n.read_at = null  // revert on error
    }
}

async function markAllRead() {
    notifications.value.forEach(n => { n.read_at = n.read_at ?? new Date().toISOString() })
    try {
        await window.axios.post('/dashboard/notifications/read-all')
        router.reload({ only: ['auth'] })
    } catch { /* swallow */ }
}

// ── Navigate on click ──────────────────────────────────────────────────────────

function onNotifClick(n: InAppNotification) {
    markRead(n)
    open.value = false
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
    'scheduling.assigned':  { icon: CalendarDays,   bg: 'bg-brand-100',  text: 'text-brand-600'   },
    'scheduling.published': { icon: CalendarCheck2, bg: 'bg-green-100',  text: 'text-green-600'   },
    'scheduling.removed':   { icon: CalendarDays,   bg: 'bg-red-100',    text: 'text-red-600'     },
    'scheduling.declined':  { icon: CalendarDays,   bg: 'bg-orange-100', text: 'text-orange-600'  },
    'sermon.sync.done':     { icon: Mic2,           bg: 'bg-brand-100', text: 'text-brand-600'  },
    'sermon.sync.failed':   { icon: AlertCircle,    bg: 'bg-red-100',    text: 'text-red-600'     },
    'sermon.published':     { icon: Mic2,           bg: 'bg-brand-100',  text: 'text-brand-600'   },
    'broadcast':            { icon: Radio,          bg: 'bg-brand-100',  text: 'text-brand-600'   },
    'broadcast.sent':       { icon: Radio,          bg: 'bg-emerald-100', text: 'text-emerald-600' },
    'event.rsvp':           { icon: CalendarDays,   bg: 'bg-sky-100',     text: 'text-sky-600'     },
    'role_changed':         { icon: Shield,         bg: 'bg-brand-100',  text: 'text-brand-600'  },
    'prayer_request':       { icon: Heart,          bg: 'bg-rose-100',   text: 'text-rose-600'    },
}

function iconFor(type: string): IconConfig {
    return TYPE_ICONS[type] ?? { icon: Bell, bg: 'bg-neutral-100', text: 'text-neutral-500' }
}

// ── Time formatting ────────────────────────────────────────────────────────────

function relativeTime(iso: string): string {
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
    if (diff < 60)   return 'just now'
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}

// ── Computed unread list ───────────────────────────────────────────────────────

const hasUnread    = computed(() => auth.notificationsCount > 0)
const unreadInList = computed(() => notifications.value.filter(n => !n.read_at).length)

// ── Real-time: listen for new notifications pushed by WebSocket ───────────────
// DashboardLayout mounts the WebSocket subscription; it communicates here via
// a custom DOM event so the dropdown can react without prop-drilling or a store.

function onRealtimeNotification(e: Event) {
    const payload = (e as CustomEvent<NotificationPayload>).detail

    // Prepend to the dropdown list if it's already open/loaded
    if (loaded.value && payload.notification.id) {
        const already = notifications.value.some(n => n.id === payload.notification.id)
        if (!already) {
            notifications.value.unshift({
                id:         payload.notification.id!,
                type:       '',  // FQCN not needed for display
                data: {
                    type:       payload.notification.type as any,
                    title:      payload.notification.title,
                    body:       payload.notification.body,
                    action_url: payload.notification.action_url,
                    actor:      payload.notification.actor,
                },
                read_at:    null,
                created_at: payload.notification.created_at,
            })
        }
    } else if (!open.value) {
        // Dropdown is closed — a silent re-fetch on next open will pick it up;
        // the badge has already been updated via useAuthStore.setLiveNotificationsCount
    }
}

onMounted(()  => window.addEventListener('realtime:notification', onRealtimeNotification))
onUnmounted(() => window.removeEventListener('realtime:notification', onRealtimeNotification))
</script>

<template>
    <div ref="dropdownRef" class="relative">

        <!-- ── Bell trigger ──────────────────────────────────────────────────── -->
        <button
            type="button"
            class="relative p-2 rounded-lg hover:bg-neutral-100 text-neutral-500 hover:text-neutral-700 transition-colors"
            :class="open && 'bg-neutral-100 text-neutral-700'"
            aria-label="Notifications"
            @click="toggle"
        >
            <Bell class="w-[18px] h-[18px]" />
            <span
                v-if="hasUnread"
                class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 bg-brand-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-0.5 pointer-events-none"
            >
                {{ auth.notificationsCount > 9 ? '9+' : auth.notificationsCount }}
            </span>
        </button>

        <!-- ── Dropdown panel ────────────────────────────────────────────────── -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 top-full mt-2 w-[380px] bg-white border border-neutral-100 rounded-2xl shadow-2xl shadow-neutral-900/10 overflow-hidden z-50"
                style="max-height: min(520px, calc(100vh - 80px)); display: flex; flex-direction: column;"
            >
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 border-b border-neutral-100 shrink-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold text-neutral-900">Notifications</h3>
                        <span
                            v-if="unreadInList > 0"
                            class="min-w-[18px] h-[18px] bg-brand-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1"
                        >
                            {{ unreadInList }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            v-if="unreadInList > 0"
                            type="button"
                            class="flex items-center gap-1 px-2 py-1 text-xs font-medium text-neutral-500 hover:text-neutral-800 rounded-md hover:bg-neutral-100 transition-colors"
                            @click="markAllRead"
                        >
                            <CheckCheck class="w-3.5 h-3.5" />
                            Mark all read
                        </button>
                        <button
                            type="button"
                            class="p-1 text-neutral-400 hover:text-neutral-600 rounded-md hover:bg-neutral-100 transition-colors"
                            @click="open = false"
                        >
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Loading state -->
                <div v-if="loading" class="flex items-center justify-center py-10">
                    <Loader2 class="w-5 h-5 text-neutral-300 animate-spin" />
                </div>

                <!-- Empty state -->
                <div
                    v-else-if="loaded && notifications.length === 0"
                    class="flex flex-col items-center justify-center py-10 px-4 text-center"
                >
                    <div class="w-10 h-10 bg-neutral-100 rounded-full flex items-center justify-center mb-3">
                        <Bell class="w-5 h-5 text-neutral-400" />
                    </div>
                    <p class="text-sm font-medium text-neutral-600">All caught up</p>
                    <p class="text-xs text-neutral-400 mt-0.5">No notifications yet.</p>
                </div>

                <!-- Notification list -->
                <ul
                    v-else-if="notifications.length > 0"
                    class="overflow-y-auto flex-1 divide-y divide-neutral-50"
                    role="list"
                >
                    <li
                        v-for="n in notifications"
                        :key="n.id"
                        class="relative flex gap-3 px-4 py-3 cursor-pointer transition-colors"
                        :class="n.read_at ? 'hover:bg-neutral-50' : 'bg-brand-50/40 hover:bg-brand-50'"
                        @click="onNotifClick(n)"
                        role="listitem"
                    >
                        <!-- Unread dot -->
                        <span
                            v-if="!n.read_at"
                            class="absolute left-2 top-1/2 -translate-y-1/2 w-1.5 h-1.5 bg-brand-500 rounded-full shrink-0"
                        />

                        <!-- Type icon -->
                        <div
                            :class="[
                                'w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5',
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
                            <p class="text-xs font-semibold text-neutral-900 leading-snug"
                               :class="!n.read_at && 'text-neutral-900'">
                                {{ n.data.title }}
                            </p>
                            <p class="text-xs text-neutral-500 leading-snug mt-0.5 line-clamp-2">
                                {{ n.data.body }}
                            </p>
                            <p class="text-[10px] text-neutral-400 mt-1">
                                {{ relativeTime(n.created_at) }}
                            </p>
                        </div>

                        <!-- Mark read button (shown on hover) -->
                        <button
                            v-if="!n.read_at"
                            type="button"
                            class="opacity-0 group-hover:opacity-100 shrink-0 self-start mt-0.5 p-1 rounded text-neutral-300 hover:text-brand-600 hover:bg-brand-50 transition-all"
                            title="Mark as read"
                            @click.stop="markRead(n)"
                        >
                            <CheckCheck class="w-3.5 h-3.5" />
                        </button>
                    </li>
                </ul>

                <!-- Footer -->
                <div class="border-t border-neutral-100 px-4 py-2.5 shrink-0">
                    <Link
                        href="/dashboard/notifications"
                        class="flex items-center justify-center gap-1.5 text-xs font-medium text-brand-600 hover:text-brand-700 transition-colors"
                        @click="open = false"
                    >
                        View all notifications
                        <ExternalLink class="w-3 h-3" />
                    </Link>
                </div>
            </div>
        </Transition>
    </div>
</template>
