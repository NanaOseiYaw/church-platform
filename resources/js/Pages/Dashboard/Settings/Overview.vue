<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import {
    Building2, Palette, Clock, ScrollText,
    Users, Building, CalendarDays, Megaphone,
    ArrowRight, RefreshCw, CheckCircle2,
} from 'lucide-vue-next'

interface Church {
    id: number
    name: string
    tagline: string | null
    logo: string | null
    primary_color: string | null
    subscription_plan: string
}
interface Stats {
    members_count: number
    departments_count: number
    upcoming_events: number
    announcements: number
    last_updated_at: string | null
}
interface ActivityItem {
    id: number
    action: string
    actor: string | null
    created_at: string | null
}

const props = defineProps<{
    church: Church
    stats: Stats
    recentActivity: ActivityItem[]
}>()

const planLabel = computed(() => {
    const map: Record<string, string> = {
        free: 'Free Plan',
        starter: 'Starter',
        pro: 'Pro',
        enterprise: 'Enterprise',
    }
    return map[props.church.subscription_plan] ?? props.church.subscription_plan
})

const lastUpdated = computed(() => {
    if (!props.stats.last_updated_at) return 'Never'
    const d = new Date(props.stats.last_updated_at)
    const diffMs   = Date.now() - d.getTime()
    const diffMins = Math.floor(diffMs / 60000)
    if (diffMins < 60)  return `${diffMins}m ago`
    const diffHrs = Math.floor(diffMins / 60)
    if (diffHrs < 24)   return `${diffHrs}h ago`
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
})

function formatAction(action: string): string {
    return action.replace(/\./g, ' › ').replace(/_/g, ' ')
}
</script>

<template>
    <SettingsLayout section="overview">

        <!-- Page header -->
        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Administration Overview</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                A snapshot of your church platform and quick access to key settings.
            </p>
        </div>

        <!-- ── Church identity banner ──────────────────────────────────────── -->
        <div class="bg-white border border-neutral-100 rounded-xl p-5 mb-5 flex items-center gap-4">
            <!-- Logo placeholder -->
            <div class="w-12 h-12 rounded-xl gradient-brand flex items-center justify-center shrink-0 text-white font-bold text-base">
                {{ church.name.slice(0, 2).toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base font-semibold text-neutral-900 truncate">{{ church.name }}</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-brand-50 text-brand-700 border border-brand-200">
                        {{ planLabel }}
                    </span>
                </div>
                <p v-if="church.tagline" class="text-sm text-neutral-500 truncate mt-0.5">{{ church.tagline }}</p>
            </div>
            <p class="text-xs text-neutral-400 shrink-0 hidden sm:block">
                Updated {{ lastUpdated }}
            </p>
        </div>

        <!-- ── Stats grid ──────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div
                v-for="stat in [
                    { label: 'Members',     value: stats.members_count,     icon: Users,        href: '/dashboard/members' },
                    { label: 'Departments', value: stats.departments_count, icon: Building,     href: '/dashboard/departments' },
                    { label: 'Events',      value: stats.upcoming_events,   icon: CalendarDays, href: '/dashboard/events' },
                    { label: 'Announcements', value: stats.announcements,   icon: Megaphone,    href: '/dashboard/announcements' },
                ]"
                :key="stat.label"
                class="bg-white border border-neutral-100 rounded-xl p-4"
            >
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs text-neutral-500 font-medium">{{ stat.label }}</p>
                    <component :is="stat.icon" class="w-3.5 h-3.5 text-neutral-300" />
                </div>
                <p class="text-2xl font-bold text-neutral-900 tabular-nums">{{ stat.value }}</p>
                <Link :href="stat.href" class="text-[11px] text-brand-600 hover:text-brand-700 mt-1 inline-flex items-center gap-0.5">
                    View <ArrowRight class="w-2.5 h-2.5" />
                </Link>
            </div>
        </div>

        <!-- ── Quick actions ──────────────────────────────────────────────── -->
        <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100 mb-5">
            <div class="px-5 py-4">
                <h3 class="text-sm font-semibold text-neutral-900">Quick actions</h3>
                <p class="text-xs text-neutral-500 mt-0.5">Jump to the most commonly updated settings.</p>
            </div>
            <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
                <Link
                    v-for="action in [
                        { label: 'Edit Profile',    href: '/dashboard/settings/profile',    icon: Building2 },
                        { label: 'Branding',        href: '/dashboard/settings/branding',   icon: Palette },
                        { label: 'Service Times',   href: '/dashboard/settings/services',   icon: Clock },
                        { label: 'Audit Logs',      href: '/dashboard/settings/audit',      icon: ScrollText },
                    ]"
                    :key="action.label"
                    :href="action.href"
                    class="flex flex-col items-center gap-2 px-3 py-4 rounded-lg border border-neutral-100 hover:border-brand-200 hover:bg-brand-50 transition-all duration-150 group text-center"
                >
                    <component :is="action.icon" class="w-5 h-5 text-neutral-400 group-hover:text-brand-600 transition-colors" />
                    <span class="text-xs font-medium text-neutral-600 group-hover:text-brand-700">{{ action.label }}</span>
                </Link>
            </div>
        </div>

        <!-- ── Recent activity ────────────────────────────────────────────── -->
        <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
            <div class="px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-neutral-900">Recent activity</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Latest changes made by admins.</p>
                </div>
                <Link href="/dashboard/settings/audit" class="text-xs text-brand-600 hover:text-brand-700 flex items-center gap-1">
                    View all <ArrowRight class="w-3 h-3" />
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="!recentActivity.length" class="px-5 py-10 flex flex-col items-center text-center">
                <CheckCircle2 class="w-8 h-8 text-neutral-200 mb-2" />
                <p class="text-sm text-neutral-400">No activity logged yet.</p>
                <p class="text-xs text-neutral-300 mt-0.5">Changes you make to settings will appear here.</p>
            </div>

            <!-- Activity rows -->
            <div v-else class="divide-y divide-neutral-50">
                <div
                    v-for="item in recentActivity"
                    :key="item.id"
                    class="flex items-center gap-3 px-5 py-3"
                >
                    <div class="w-6 h-6 rounded-full bg-neutral-100 flex items-center justify-center shrink-0">
                        <RefreshCw class="w-3 h-3 text-neutral-400" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-neutral-700 capitalize">{{ formatAction(item.action) }}</p>
                        <p class="text-[11px] text-neutral-400">{{ item.actor ?? 'System' }}</p>
                    </div>
                    <p v-if="item.created_at" class="text-[11px] text-neutral-400 shrink-0">
                        {{ new Date(item.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' }) }}
                    </p>
                </div>
            </div>
        </div>

    </SettingsLayout>
</template>
