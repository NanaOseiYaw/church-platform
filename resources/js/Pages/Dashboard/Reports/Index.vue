<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import {
    Users, Building2, CalendarDays, CheckSquare,
    Megaphone, Mic2, UserCheck, BarChart2,
    TrendingUp, TrendingDown, Download, AlertTriangle,
    CalendarCheck2, ClipboardList, Star, Mail,
} from 'lucide-vue-next'

// ── Types ──────────────────────────────────────────────────────────────────────

interface MonthValue  { month: string; [key: string]: number | string }
interface NameCount   { name: string; count: number }
interface NameRate    { name: string; sessions: number; avg_rate: number }
interface TypeRate    { type: string; sessions: number; avg_rate: number }
interface DeptTask    { name: string; total: number; completed: number }
interface PriorityTask{ priority: string; total: number; completed: number }
interface TopEvent    { title: string; date: string | null; going: number; maybe: number }
interface TopVol      { name: string; assignments: number }
interface RecentBroadcast {
    title: string
    sent_at: string | null
    recipients: number
    delivered: number
    delivery_rate: number
}

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    exportSections: string[]
    filters: { date_range: string; department_id: number | null }
    departments: { id: number; name: string }[]
    stats: {
        people: {
            total_members:    number
            verified_members: number
            active_depts:     number
            total_depts:      number
            role_breakdown:   { role: string; count: number }[]
        }
        events: { total: number; upcoming: number; past: number }
        tasks: { total: number; completed: number; pending: number; completion_rate: number | null }
        content: {
            announcements_total: number; announcements_published: number
            sermons_total: number; sermons_public: number
        }
        attendance: {
            total_sessions: number; completed_sessions: number
            avg_rate: number | null
            recent_rates: { label: string; rate: number }[]
        }
    }
    analytics: {
        attendance: {
            monthly_trend: (MonthValue & { sessions: number; avg_rate: number })[]
            by_department: NameRate[]
            by_type:       TypeRate[]
        }
        members: {
            monthly_growth: (MonthValue & { count: number })[]
            active_count:   number
            by_department:  NameCount[]
        }
        tasks: {
            overdue:       number
            by_department: DeptTask[]
            by_priority:   PriorityTask[]
        }
        events: {
            rsvp_totals: { going: number; maybe: number }
            top_events:  TopEvent[]
        }
        volunteers: {
            total_assignments: number
            confirmed:         number
            declined:          number
            pending:           number
            top_volunteers:    TopVol[]
        }
        communication: {
            total_broadcasts:      number
            sent:                  number
            draft:                 number
            failed:                number
            overall_delivery_rate: number | null
            total_recipients:      number
            total_delivered:       number
            recent_broadcasts:     RecentBroadcast[]
        }
    }
}>()

// ── Filters ────────────────────────────────────────────────────────────────────

const dateRange = ref(props.filters.date_range)
const deptId    = ref<string>(props.filters.department_id ? String(props.filters.department_id) : '')

const dateRangeOptions = [
    { value: '30d', label: 'Last 30 days' },
    { value: '90d', label: 'Last 90 days' },
    { value: '6m',  label: 'Last 6 months' },
    { value: '12m', label: 'Last 12 months' },
    { value: 'all', label: 'All time' },
]

function applyFilters() {
    router.get('/dashboard/reports', {
        date_range:    dateRange.value,
        department_id: deptId.value || undefined,
    }, { preserveScroll: true, replace: true })
}

watch([dateRange, deptId], applyFilters)

// ── Export helpers ────────────────────────────────────────────────────────────

function exportUrl(section: string): string {
    return `/dashboard/reports/export?section=${section}`
}

const exportLabels: Record<string, string> = {
    members: 'Members', attendance: 'Attendance', tasks: 'Tasks', events: 'Events', volunteers: 'Volunteers',
}

// ── Chart helpers ─────────────────────────────────────────────────────────────

function barPct(value: number, max: number): string {
    return max > 0 ? `${Math.round((value / max) * 100)}%` : '0%'
}

function rateColor(rate: number): string {
    if (rate >= 75) return 'bg-green-400'
    if (rate >= 50) return 'bg-amber-400'
    return 'bg-rose-400'
}

function rateText(rate: number): string {
    if (rate >= 75) return 'text-green-600'
    if (rate >= 50) return 'text-amber-600'
    return 'text-rose-500'
}

// Existing mini-chart helpers
const maxRate = computed(() =>
    Math.max(...props.stats.attendance.recent_rates.map(r => r.rate), 1),
)
function barHeight(rate: number): string {
    return `${Math.round((rate / maxRate.value) * 100)}%`
}

// Analytics chart maxima
const maxMonthlyRate = computed(() =>
    Math.max(...props.analytics.attendance.monthly_trend.map(d => d.avg_rate), 1),
)
const maxMonthlyGrowth = computed(() =>
    Math.max(...props.analytics.members.monthly_growth.map(d => d.count), 1),
)
const maxDeptMembers = computed(() =>
    Math.max(...props.analytics.members.by_department.map(d => d.count), 1),
)

// ── Role / priority label formatting ──────────────────────────────────────────

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin', church_admin: 'Church Admin',
    coordinator: 'Coordinator', member: 'Member',
}
function roleLabel(role: string): string {
    return roleLabels[role] ?? role.replace(/_/g, ' ')
}

const priorityColors: Record<string, string> = {
    Urgent: 'bg-rose-100 text-rose-700',
    High:   'bg-amber-100 text-amber-700',
    Medium: 'bg-blue-100 text-blue-700',
    Low:    'bg-neutral-100 text-neutral-500',
    None:   'bg-neutral-100 text-neutral-400',
}

// ── Computed summary values ───────────────────────────────────────────────────

const verificationPct = computed(() => {
    const { total_members, verified_members } = props.stats.people
    return total_members > 0 ? Math.round(verified_members / total_members * 100) : null
})

const volunteerParticipationRate = computed(() => {
    const { total_assignments, confirmed } = props.analytics.volunteers
    return total_assignments > 0 ? Math.round(confirmed / total_assignments * 100) : null
})

const dateRangeLabel = computed(() =>
    dateRangeOptions.find(o => o.value === dateRange.value)?.label ?? 'Selected period',
)

const typeLabels: Record<string, string> = {
    service: 'Service', meeting: 'Meeting', rehearsal: 'Rehearsal',
    outreach: 'Outreach', volunteer: 'Volunteer', other: 'Other',
}
</script>

<template>
    <DashboardLayout
        title="Reports"
        :breadcrumbs="[{ label: 'Reports' }]"
    >
        <!-- ── Header ─────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">Reports &amp; Analytics</h1>
                <p class="text-sm text-neutral-500 mt-0.5">Operational insights for church leadership.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs text-neutral-400 hidden sm:inline">Export CSV:</span>
                <a
                    v-for="section in exportSections"
                    :key="section"
                    :href="exportUrl(section)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-neutral-200 bg-white text-sm font-medium text-neutral-600 hover:bg-neutral-50 hover:border-neutral-300 transition-colors"
                    :download="`church-report-${section}.csv`"
                >
                    <Download class="w-3.5 h-3.5" />
                    {{ exportLabels[section] ?? section }}
                </a>
            </div>
        </div>

        <!-- ── Filters ────────────────────────────────────────────────────── -->
        <div class="flex items-center gap-3 flex-wrap mb-7 p-3 bg-white border border-neutral-100 rounded-xl">
            <span class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">Filter analytics:</span>

            <!-- Date range -->
            <select
                v-model="dateRange"
                class="h-8 pl-3 pr-8 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
                <option v-for="opt in dateRangeOptions" :key="opt.value" :value="opt.value">
                    {{ opt.label }}
                </option>
            </select>

            <!-- Department -->
            <select
                v-model="deptId"
                class="h-8 pl-3 pr-8 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
                <option value="">All departments</option>
                <option v-for="d in departments" :key="d.id" :value="String(d.id)">
                    {{ d.name }}
                </option>
            </select>

            <span class="text-xs text-neutral-400 ml-auto hidden sm:inline">
                Showing: {{ dateRangeLabel }}{{ deptId ? ` · ${departments.find(d => String(d.id) === deptId)?.name}` : '' }}
            </span>
        </div>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!--  STAT CARDS (all-time overview)                                 -->
        <!-- ════════════════════════════════════════════════════════════════ -->

        <!-- People -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <Users class="w-3.5 h-3.5" /> People
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-900 tabular-nums">
                        {{ stats.people.total_members.toLocaleString() }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Total members</p>
                    <p v-if="verificationPct !== null" class="text-[10px] text-neutral-400 mt-1">
                        {{ verificationPct }}% email-verified
                    </p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-green-600 tabular-nums">
                        {{ analytics.members.active_count.toLocaleString() }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Active members</p>
                    <p class="text-[10px] text-neutral-400 mt-1">Attended in last 90 days</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-500 tabular-nums">
                        {{ (stats.people.total_members - analytics.members.active_count).toLocaleString() }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Inactive members</p>
                    <p class="text-[10px] text-neutral-400 mt-1">No attendance in 90 days</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-xs font-semibold text-neutral-500 mb-2">Roles</p>
                    <ul class="space-y-1">
                        <li
                            v-for="r in stats.people.role_breakdown"
                            :key="r.role"
                            class="flex items-center justify-between text-xs"
                        >
                            <span class="text-neutral-600">{{ roleLabel(r.role) }}</span>
                            <span class="font-semibold text-neutral-800 tabular-nums">{{ r.count }}</span>
                        </li>
                        <li v-if="!stats.people.role_breakdown.length" class="text-xs text-neutral-400 italic">
                            No role data
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Events -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <CalendarDays class="w-3.5 h-3.5" /> Events
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-900 tabular-nums">{{ stats.events.total }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">Total</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-brand-600 tabular-nums">{{ stats.events.upcoming }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">Upcoming</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-400 tabular-nums">{{ stats.events.past }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">Past</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-green-600 tabular-nums">
                        {{ analytics.events.rsvp_totals.going.toLocaleString() }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">RSVPs going</p>
                    <p class="text-[10px] text-neutral-400 mt-1">{{ dateRangeLabel }}</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-amber-500 tabular-nums">
                        {{ analytics.events.rsvp_totals.maybe.toLocaleString() }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">RSVPs maybe</p>
                    <p class="text-[10px] text-neutral-400 mt-1">{{ dateRangeLabel }}</p>
                </div>
            </div>
        </section>

        <!-- Tasks -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <CheckSquare class="w-3.5 h-3.5" /> Tasks
            </h2>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-900 tabular-nums">{{ stats.tasks.total }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">Total</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-green-600 tabular-nums">{{ stats.tasks.completed }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">Completed</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-amber-600 tabular-nums">{{ stats.tasks.pending }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">In progress / pending</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <p class="text-2xl font-bold tabular-nums"
                           :class="stats.tasks.completion_rate !== null
                               ? (stats.tasks.completion_rate >= 75 ? 'text-green-600' : stats.tasks.completion_rate >= 50 ? 'text-amber-500' : 'text-rose-500')
                               : 'text-neutral-400'"
                        >
                            {{ stats.tasks.completion_rate !== null ? `${stats.tasks.completion_rate}%` : '—' }}
                        </p>
                        <TrendingUp   v-if="(stats.tasks.completion_rate ?? 0) >= 75" class="w-4 h-4 text-green-500" />
                        <TrendingDown v-else-if="(stats.tasks.completion_rate ?? 0) < 50 && stats.tasks.completion_rate !== null" class="w-4 h-4 text-rose-400" />
                    </div>
                    <p class="text-xs text-neutral-500">Completion rate</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold tabular-nums"
                       :class="analytics.tasks.overdue > 0 ? 'text-rose-500' : 'text-neutral-900'">
                        {{ analytics.tasks.overdue }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5 flex items-center gap-1">
                        <AlertTriangle v-if="analytics.tasks.overdue > 0" class="w-3 h-3 text-rose-400" />
                        Overdue tasks
                    </p>
                </div>
            </div>
        </section>

        <!-- Attendance -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <UserCheck class="w-3.5 h-3.5" /> Attendance
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-900 tabular-nums">{{ stats.attendance.total_sessions }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">Total sessions</p>
                    <p class="text-[10px] text-neutral-400 mt-1">{{ stats.attendance.completed_sessions }} completed</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold tabular-nums"
                       :class="stats.attendance.avg_rate !== null
                           ? (stats.attendance.avg_rate >= 75 ? 'text-green-600' : stats.attendance.avg_rate >= 50 ? 'text-amber-500' : 'text-rose-500')
                           : 'text-neutral-400'"
                    >
                        {{ stats.attendance.avg_rate !== null ? `${stats.attendance.avg_rate}%` : '—' }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Avg. attendance rate</p>
                    <p class="text-[10px] text-neutral-400 mt-1">Completed sessions only</p>
                </div>
                <!-- Recent 5 sessions mini chart -->
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-xs font-semibold text-neutral-500 mb-3 flex items-center gap-1.5">
                        <BarChart2 class="w-3.5 h-3.5 text-neutral-400" />
                        Recent sessions
                    </p>
                    <div v-if="stats.attendance.recent_rates.length" class="flex items-end gap-1.5 h-14">
                        <div v-for="(r, i) in stats.attendance.recent_rates" :key="i"
                             class="flex-1 flex flex-col items-center justify-end gap-0.5 h-full">
                            <span class="text-[8px] text-neutral-400 tabular-nums leading-none">{{ r.rate }}%</span>
                            <div :class="['w-full rounded-sm transition-all', rateColor(r.rate)]"
                                 :style="{ height: barHeight(r.rate) }"
                                 :title="`${r.label}: ${r.rate}%`" />
                            <span class="text-[8px] text-neutral-400 truncate w-full text-center leading-none">
                                {{ r.label }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 italic text-center py-3">No completed sessions yet</p>
                </div>
            </div>
        </section>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!--  PHASE 1 ANALYTICS                                              -->
        <!-- ════════════════════════════════════════════════════════════════ -->

        <div class="border-t border-neutral-100 pt-6 mb-6">
            <h2 class="text-sm font-semibold text-neutral-900 mb-1">Detailed Analytics</h2>
            <p class="text-xs text-neutral-400">
                Showing data for <strong class="text-neutral-600">{{ dateRangeLabel }}</strong>
                <span v-if="deptId"> · {{ departments.find(d => String(d.id) === deptId)?.name }}</span>
            </p>
        </div>

        <!-- ── Attendance Trends ───────────────────────────────────────────── -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <CalendarCheck2 class="w-3.5 h-3.5" /> Attendance Trends
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Monthly trend bar chart -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5">
                    <p class="text-xs font-semibold text-neutral-700 mb-4">Average attendance rate by month</p>
                    <div v-if="analytics.attendance.monthly_trend.length" class="flex items-end gap-2 h-28">
                        <div
                            v-for="m in analytics.attendance.monthly_trend"
                            :key="m.month"
                            class="flex-1 flex flex-col items-center justify-end gap-1 h-full min-w-0"
                        >
                            <span class="text-[8px] text-neutral-400 tabular-nums leading-none">
                                {{ m.avg_rate }}%
                            </span>
                            <div
                                :class="['w-full rounded-t transition-all', rateColor(m.avg_rate)]"
                                :style="{ height: barPct(m.avg_rate, maxMonthlyRate) }"
                                :title="`${m.month}: ${m.avg_rate}% avg rate (${m.sessions} sessions)`"
                            />
                            <span class="text-[8px] text-neutral-400 truncate w-full text-center leading-none mt-0.5">
                                {{ m.month.slice(0, 3) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-neutral-400 italic text-center py-6">
                        No completed sessions in this period.
                    </p>
                </div>

                <!-- By department + by type -->
                <div class="space-y-4">
                    <!-- By department -->
                    <div class="bg-white border border-neutral-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-neutral-700 mb-3">By department</p>
                        <div v-if="analytics.attendance.by_department.length" class="space-y-2">
                            <div
                                v-for="d in analytics.attendance.by_department"
                                :key="d.name"
                                class="flex items-center gap-3 text-xs"
                            >
                                <span class="w-24 text-neutral-600 truncate shrink-0">{{ d.name }}</span>
                                <div class="flex-1 bg-neutral-100 rounded-full h-2 overflow-hidden">
                                    <div
                                        :class="['h-full rounded-full transition-all', rateColor(d.avg_rate)]"
                                        :style="{ width: `${d.avg_rate}%` }"
                                    />
                                </div>
                                <span :class="['w-10 text-right tabular-nums font-semibold shrink-0', rateText(d.avg_rate)]">
                                    {{ d.avg_rate }}%
                                </span>
                                <span class="text-neutral-400 w-16 shrink-0">{{ d.sessions }} sessions</span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-neutral-400 italic">No data for this period.</p>
                    </div>

                    <!-- By type -->
                    <div class="bg-white border border-neutral-100 rounded-xl p-5">
                        <p class="text-xs font-semibold text-neutral-700 mb-3">By session type</p>
                        <div v-if="analytics.attendance.by_type.length" class="flex flex-wrap gap-2">
                            <div
                                v-for="t in analytics.attendance.by_type"
                                :key="t.type"
                                class="flex items-center gap-2 bg-neutral-50 border border-neutral-100 rounded-lg px-3 py-2"
                            >
                                <span class="text-xs font-medium text-neutral-700">
                                    {{ typeLabels[t.type] ?? t.type }}
                                </span>
                                <span class="text-xs text-neutral-400">{{ t.sessions }}×</span>
                                <span :class="['text-xs font-semibold', rateText(t.avg_rate)]">
                                    {{ t.avg_rate }}%
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-neutral-400 italic">No data for this period.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Member Insights ────────────────────────────────────────────── -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <Users class="w-3.5 h-3.5" /> Member Insights
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Growth trend -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5">
                    <p class="text-xs font-semibold text-neutral-700 mb-4">New members by month</p>
                    <div v-if="analytics.members.monthly_growth.length" class="flex items-end gap-2 h-28">
                        <div
                            v-for="m in analytics.members.monthly_growth"
                            :key="m.month"
                            class="flex-1 flex flex-col items-center justify-end gap-1 h-full min-w-0"
                        >
                            <span class="text-[8px] text-neutral-400 tabular-nums leading-none">{{ m.count }}</span>
                            <div
                                class="w-full bg-brand-400 rounded-t transition-all"
                                :style="{ height: barPct(m.count, maxMonthlyGrowth) }"
                                :title="`${m.month}: ${m.count} new members`"
                            />
                            <span class="text-[8px] text-neutral-400 truncate w-full text-center leading-none mt-0.5">
                                {{ m.month.slice(0, 3) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-neutral-400 italic text-center py-6">
                        No new members in this period.
                    </p>
                </div>

                <!-- By department -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5">
                    <p class="text-xs font-semibold text-neutral-700 mb-3">Members by department</p>
                    <div v-if="analytics.members.by_department.length" class="space-y-2">
                        <div
                            v-for="d in analytics.members.by_department"
                            :key="d.name"
                            class="flex items-center gap-3 text-xs"
                        >
                            <span class="w-28 text-neutral-600 truncate shrink-0">{{ d.name }}</span>
                            <div class="flex-1 bg-neutral-100 rounded-full h-2 overflow-hidden">
                                <div
                                    class="h-full bg-brand-400 rounded-full transition-all"
                                    :style="{ width: barPct(d.count, maxDeptMembers) }"
                                />
                            </div>
                            <span class="w-8 text-right tabular-nums font-semibold text-neutral-700 shrink-0">
                                {{ d.count }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 italic">No department data.</p>
                </div>
            </div>
        </section>

        <!-- ── Task Insights ──────────────────────────────────────────────── -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <ClipboardList class="w-3.5 h-3.5" /> Task Insights
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- By department -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5">
                    <p class="text-xs font-semibold text-neutral-700 mb-3">Tasks by department</p>
                    <div v-if="analytics.tasks.by_department.length" class="space-y-2">
                        <div
                            v-for="d in analytics.tasks.by_department"
                            :key="d.name"
                            class="flex items-center gap-3 text-xs"
                        >
                            <span class="w-24 text-neutral-600 truncate shrink-0">{{ d.name }}</span>
                            <div class="flex-1 bg-neutral-100 rounded-full h-2 overflow-hidden">
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all',
                                        d.total > 0 ? rateColor(Math.round(d.completed / d.total * 100)) : 'bg-neutral-300',
                                    ]"
                                    :style="{ width: d.total > 0 ? `${Math.round(d.completed / d.total * 100)}%` : '0%' }"
                                />
                            </div>
                            <span class="text-neutral-400 w-16 text-right shrink-0">
                                {{ d.completed }}/{{ d.total }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 italic">No tasks in this period.</p>
                </div>

                <!-- By priority -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5">
                    <p class="text-xs font-semibold text-neutral-700 mb-3">Tasks by priority</p>
                    <div v-if="analytics.tasks.by_priority.length" class="space-y-2">
                        <div
                            v-for="p in analytics.tasks.by_priority"
                            :key="p.priority"
                            class="flex items-center justify-between text-xs"
                        >
                            <span
                                :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0', priorityColors[p.priority] ?? 'bg-neutral-100 text-neutral-500']"
                            >
                                {{ p.priority }}
                            </span>
                            <div class="flex-1 mx-3 bg-neutral-100 rounded-full h-1.5 overflow-hidden">
                                <div
                                    :class="['h-full rounded-full', p.total > 0 ? rateColor(Math.round(p.completed / p.total * 100)) : 'bg-neutral-200']"
                                    :style="{ width: p.total > 0 ? `${Math.round(p.completed / p.total * 100)}%` : '0%' }"
                                />
                            </div>
                            <span class="text-neutral-500 tabular-nums shrink-0">
                                {{ p.completed }}/{{ p.total }} done
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 italic">No tasks in this period.</p>
                </div>
            </div>
        </section>

        <!-- ── Event RSVPs ────────────────────────────────────────────────── -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <CalendarDays class="w-3.5 h-3.5" /> Event RSVPs
            </h2>

            <div v-if="analytics.events.top_events.length" class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400 uppercase tracking-wider">
                            <th class="text-left px-5 py-3 font-semibold">Event</th>
                            <th class="text-left px-5 py-3 font-semibold">Date</th>
                            <th class="text-right px-5 py-3 font-semibold">Going</th>
                            <th class="text-right px-5 py-3 font-semibold">Maybe</th>
                            <th class="text-right px-5 py-3 font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        <tr
                            v-for="e in analytics.events.top_events"
                            :key="e.title"
                            class="hover:bg-neutral-50 transition-colors"
                        >
                            <td class="px-5 py-3 font-medium text-neutral-800 max-w-xs truncate">{{ e.title }}</td>
                            <td class="px-5 py-3 text-neutral-500 text-xs">{{ e.date ?? '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-green-700 font-semibold">{{ e.going }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-amber-600">{{ e.maybe }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-neutral-700 font-bold">{{ e.going + e.maybe }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t border-neutral-100 bg-neutral-50">
                        <tr class="text-xs text-neutral-500">
                            <td colspan="2" class="px-5 py-2.5 font-semibold">Period total (all RSVP events)</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-green-700 font-bold">
                                {{ analytics.events.rsvp_totals.going }}
                            </td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-amber-600 font-bold">
                                {{ analytics.events.rsvp_totals.maybe }}
                            </td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-neutral-700 font-bold">
                                {{ analytics.events.rsvp_totals.going + analytics.events.rsvp_totals.maybe }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div v-else class="bg-white border border-neutral-100 rounded-xl p-8 text-center">
                <p class="text-sm text-neutral-400">No RSVP data for this period.</p>
                <p class="text-xs text-neutral-300 mt-1">Enable RSVPs on events to track participation.</p>
            </div>
        </section>

        <!-- ── Volunteer Participation ─────────────────────────────────────── -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <Star class="w-3.5 h-3.5" /> Volunteer Participation
            </h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Stats -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-white border border-neutral-100 rounded-xl p-4">
                        <p class="text-2xl font-bold text-neutral-900 tabular-nums">
                            {{ analytics.volunteers.total_assignments.toLocaleString() }}
                        </p>
                        <p class="text-xs text-neutral-500 mt-0.5">Total assignments</p>
                        <p class="text-[10px] text-neutral-400 mt-1">{{ dateRangeLabel }}</p>
                    </div>
                    <div class="bg-white border border-neutral-100 rounded-xl p-4">
                        <p class="text-2xl font-bold tabular-nums"
                           :class="volunteerParticipationRate !== null
                               ? (volunteerParticipationRate >= 75 ? 'text-green-600' : volunteerParticipationRate >= 50 ? 'text-amber-500' : 'text-rose-500')
                               : 'text-neutral-400'"
                        >
                            {{ volunteerParticipationRate !== null ? `${volunteerParticipationRate}%` : '—' }}
                        </p>
                        <p class="text-xs text-neutral-500 mt-0.5">Participation rate</p>
                        <p class="text-[10px] text-neutral-400 mt-1">Confirmed / total</p>
                    </div>
                    <div class="bg-white border border-neutral-100 rounded-xl p-4">
                        <p class="text-2xl font-bold text-green-600 tabular-nums">
                            {{ analytics.volunteers.confirmed }}
                        </p>
                        <p class="text-xs text-neutral-500 mt-0.5">Confirmed</p>
                    </div>
                    <div class="bg-white border border-neutral-100 rounded-xl p-4">
                        <p class="text-2xl font-bold tabular-nums"
                           :class="analytics.volunteers.declined > 0 ? 'text-rose-500' : 'text-neutral-400'">
                            {{ analytics.volunteers.declined }}
                        </p>
                        <p class="text-xs text-neutral-500 mt-0.5">Declined</p>
                    </div>
                </div>

                <!-- Top volunteers -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5">
                    <p class="text-xs font-semibold text-neutral-700 mb-3">Top volunteers (confirmed assignments)</p>
                    <div v-if="analytics.volunteers.top_volunteers.length" class="space-y-2">
                        <div
                            v-for="(v, i) in analytics.volunteers.top_volunteers"
                            :key="v.name"
                            class="flex items-center gap-3 text-xs"
                        >
                            <span class="w-5 h-5 rounded-full bg-brand-50 flex items-center justify-center text-[10px] font-bold text-brand-600 shrink-0">
                                {{ i + 1 }}
                            </span>
                            <span class="flex-1 text-neutral-700 truncate">{{ v.name }}</span>
                            <span class="tabular-nums font-semibold text-neutral-800">
                                {{ v.assignments }} assignment{{ v.assignments !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-neutral-400 italic text-center py-4">
                        No confirmed assignments in this period.
                    </p>
                </div>
            </div>
        </section>

        <!-- ── Communication Analytics ────────────────────────────────────── -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <Mail class="w-3.5 h-3.5" /> Communication
            </h2>

            <!-- Stat cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-900 tabular-nums">
                        {{ analytics.communication.total_broadcasts.toLocaleString() }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Total broadcasts</p>
                    <p class="text-[10px] text-neutral-400 mt-1">{{ dateRangeLabel }}</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-green-600 tabular-nums">
                        {{ analytics.communication.sent.toLocaleString() }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Sent</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold tabular-nums"
                       :class="analytics.communication.overall_delivery_rate !== null
                           ? (analytics.communication.overall_delivery_rate >= 90 ? 'text-green-600' : analytics.communication.overall_delivery_rate >= 70 ? 'text-amber-500' : 'text-rose-500')
                           : 'text-neutral-400'"
                    >
                        {{ analytics.communication.overall_delivery_rate !== null
                            ? `${analytics.communication.overall_delivery_rate}%`
                            : '—' }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Delivery rate</p>
                    <p class="text-[10px] text-neutral-400 mt-1">
                        {{ analytics.communication.total_delivered.toLocaleString() }} /
                        {{ analytics.communication.total_recipients.toLocaleString() }} delivered
                    </p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold tabular-nums"
                       :class="analytics.communication.failed > 0 ? 'text-rose-500' : 'text-neutral-400'">
                        {{ analytics.communication.failed }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5 flex items-center gap-1">
                        <AlertTriangle v-if="analytics.communication.failed > 0" class="w-3 h-3 text-rose-400" />
                        Failed
                    </p>
                </div>
            </div>

            <!-- Recent broadcasts table -->
            <div v-if="analytics.communication.recent_broadcasts.length" class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-neutral-100 text-xs text-neutral-400 uppercase tracking-wider">
                            <th class="text-left px-5 py-3 font-semibold">Broadcast</th>
                            <th class="text-left px-5 py-3 font-semibold">Sent</th>
                            <th class="text-right px-5 py-3 font-semibold">Recipients</th>
                            <th class="text-right px-5 py-3 font-semibold">Delivered</th>
                            <th class="text-right px-5 py-3 font-semibold">Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        <tr
                            v-for="b in analytics.communication.recent_broadcasts"
                            :key="b.title"
                            class="hover:bg-neutral-50 transition-colors"
                        >
                            <td class="px-5 py-3 font-medium text-neutral-800 max-w-xs truncate">{{ b.title }}</td>
                            <td class="px-5 py-3 text-neutral-500 text-xs">{{ b.sent_at ?? '—' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-neutral-600">{{ b.recipients.toLocaleString() }}</td>
                            <td class="px-5 py-3 text-right tabular-nums text-green-700 font-semibold">{{ b.delivered.toLocaleString() }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-semibold"
                                :class="b.delivery_rate >= 90 ? 'text-green-600' : b.delivery_rate >= 70 ? 'text-amber-600' : 'text-rose-500'">
                                {{ b.delivery_rate }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="bg-white border border-neutral-100 rounded-xl p-8 text-center">
                <p class="text-sm text-neutral-400">No sent broadcasts in this period.</p>
                <p class="text-xs text-neutral-300 mt-1">Send a broadcast to see delivery analytics here.</p>
            </div>
        </section>

        <!-- Content -->
        <section class="mb-8">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3 flex items-center gap-2">
                <Megaphone class="w-3.5 h-3.5" /> Content
            </h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-900 tabular-nums">
                        {{ stats.content.announcements_total }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Announcements</p>
                    <p class="text-[10px] text-neutral-400 mt-1">{{ stats.content.announcements_published }} published</p>
                </div>
                <div class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-2xl font-bold text-neutral-900 tabular-nums">
                        {{ stats.content.sermons_total }}
                    </p>
                    <p class="text-xs text-neutral-500 mt-0.5">Sermons</p>
                    <p class="text-[10px] text-neutral-400 mt-1">{{ stats.content.sermons_public }} public</p>
                </div>
            </div>
        </section>
    </DashboardLayout>
</template>
