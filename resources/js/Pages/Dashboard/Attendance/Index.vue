<script setup lang="ts">
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import {
    CalendarCheck2, Plus, ChevronRight, MoreHorizontal,
    CheckCircle2, Clock, XCircle, Users, BarChart3
} from 'lucide-vue-next'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AttendanceStats from '@/Components/Attendance/AttendanceStats.vue'
import AttendanceFilters from '@/Components/Attendance/AttendanceFilters.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import AppBadge from '@/Components/UI/AppBadge.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import type { AttendanceSession, AttendanceDashboardStats, Department } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    sessions: {
        data: AttendanceSession[]
        links: any
        meta: any
    }
    stats: AttendanceDashboardStats | null
    departments: Pick<Department, 'id' | 'name' | 'icon' | 'color'>[]
    filters: {
        type?: string
        status?: string
        department_id?: string
        from?: string
        to?: string
    }
}>()

const auth = useAuthStore()

// ── Filters ────────────────────────────────────────────────────────────────────

function onFiltersChange(filters: typeof props.filters) {
    router.get(
        '/dashboard/attendance',
        filters,
        { preserveState: true, replace: true }
    )
}

// ── Session type config ────────────────────────────────────────────────────────

const typeConfig: Record<string, { color: string; bg: string }> = {
    service:   { color: 'text-brand-600',   bg: 'bg-brand-50' },
    meeting:   { color: 'text-blue-600',    bg: 'bg-blue-50'  },
    rehearsal: { color: 'text-orange-600',  bg: 'bg-orange-50'},
    outreach:  { color: 'text-emerald-600', bg: 'bg-emerald-50'},
    volunteer: { color: 'text-teal-600',    bg: 'bg-teal-50'  },
    other:     { color: 'text-neutral-600', bg: 'bg-neutral-100'},
}

const statusConfig: Record<string, { icon: any; label: string; badge: string }> = {
    planned:   { icon: Clock,         label: 'Planned',   badge: 'bg-neutral-100 text-neutral-600' },
    active:    { icon: CalendarCheck2, label: 'Active',   badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' },
    completed: { icon: CheckCircle2,  label: 'Completed', badge: 'bg-blue-50 text-blue-700' },
    cancelled: { icon: XCircle,       label: 'Cancelled', badge: 'bg-rose-50 text-rose-600' },
}

function rateColor(rate: number | null | undefined) {
    if (rate == null) return 'text-neutral-300'
    if (rate >= 80) return 'text-emerald-600'
    if (rate >= 60) return 'text-amber-600'
    return 'text-rose-600'
}
</script>

<template>
    <DashboardLayout
        title="Attendance"
        :breadcrumbs="[{ label: 'Attendance' }]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-neutral-900">Attendance</h1>
                <p class="text-neutral-500 text-sm mt-1">Track sessions, mark attendance, and monitor participation.</p>
            </div>
            <Link
                v-if="auth.can('attendance.manage')"
                href="/dashboard/attendance/create"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-xl transition-colors"
            >
                <Plus class="w-4 h-4" />
                New session
            </Link>
        </div>

        <!-- Stats row -->
        <div v-if="stats" class="mb-6">
            <AttendanceStats
                variant="dashboard"
                :total-sessions="stats.sessions_this_week"
                :total-present="stats.present_count_month"
                :avg-rate="stats.avg_attendance_rate"
            />
        </div>

        <!-- Trend sparkline (future analytics preparation) -->
        <div v-if="stats?.recent_session_rates?.length" class="bg-white border border-neutral-100 rounded-xl p-5 mb-6">
            <div class="flex items-center gap-2 mb-4">
                <BarChart3 class="w-4 h-4 text-neutral-400" />
                <h3 class="text-sm font-semibold text-neutral-700">Recent session attendance rates</h3>
            </div>
            <div class="flex items-end gap-2 h-12">
                <div
                    v-for="item in stats.recent_session_rates"
                    :key="item.label"
                    class="flex-1 flex flex-col items-center gap-1"
                >
                    <div class="w-full rounded-t-sm bg-brand-100 relative overflow-hidden" style="height:40px">
                        <div
                            class="absolute bottom-0 left-0 right-0 bg-brand-500 rounded-t-sm transition-all duration-500"
                            :style="{ height: `${item.rate}%` }"
                        />
                    </div>
                    <span class="text-[10px] text-neutral-400 truncate w-full text-center">{{ item.label }}</span>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4">
            <AttendanceFilters
                :departments="departments"
                :filters="filters"
                @change="onFiltersChange"
            />
        </div>

        <!-- Sessions list -->
        <div class="bg-white border border-neutral-100 rounded-2xl overflow-hidden">

            <!-- Empty state -->
            <div
                v-if="!sessions.data?.length"
                class="py-16 flex flex-col items-center gap-3 text-neutral-400"
            >
                <CalendarCheck2 class="w-10 h-10" />
                <p class="font-medium text-neutral-600">No sessions found</p>
                <p class="text-sm">Create an attendance session to get started.</p>
                <Link
                    v-if="auth.can('attendance.manage')"
                    href="/dashboard/attendance/create"
                    class="mt-2 inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-xl transition-colors"
                >
                    <Plus class="w-4 h-4" />
                    New session
                </Link>
            </div>

            <!-- Table header -->
            <div v-else>
                <div class="hidden md:grid grid-cols-12 gap-4 px-5 py-3 bg-neutral-50 border-b border-neutral-100 text-xs font-medium text-neutral-500 uppercase tracking-wide">
                    <div class="col-span-4">Session</div>
                    <div class="col-span-2">Type</div>
                    <div class="col-span-2">Date</div>
                    <div class="col-span-2 text-center">Attendance</div>
                    <div class="col-span-1 text-center">Rate</div>
                    <div class="col-span-1"></div>
                </div>

                <!-- Session rows -->
                <div class="divide-y divide-neutral-50">
                    <Link
                        v-for="session in sessions.data"
                        :key="session.id"
                        :href="`/dashboard/attendance/${session.id}`"
                        class="group block"
                    >
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center px-5 py-4 hover:bg-neutral-50/60 transition-colors">

                            <!-- Title + status -->
                            <div class="md:col-span-4">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-neutral-900 text-sm group-hover:text-brand-600 transition-colors">
                                        {{ session.title }}
                                    </span>
                                    <span
                                        :class="['text-xs px-2 py-0.5 rounded-full font-medium', statusConfig[session.status]?.badge ?? 'bg-neutral-100 text-neutral-600']"
                                    >
                                        {{ statusConfig[session.status]?.label ?? session.status }}
                                    </span>
                                </div>
                                <p v-if="session.department" class="text-xs text-neutral-400 mt-0.5">
                                    {{ session.department.name }}
                                </p>
                                <p v-else-if="session.event" class="text-xs text-neutral-400 mt-0.5">
                                    {{ session.event.title }}
                                </p>
                            </div>

                            <!-- Type chip -->
                            <div class="md:col-span-2">
                                <span
                                    :class="[
                                        'inline-flex px-2 py-0.5 rounded-md text-xs font-medium',
                                        typeConfig[session.type]?.bg ?? 'bg-neutral-100',
                                        typeConfig[session.type]?.color ?? 'text-neutral-600',
                                    ]"
                                >
                                    {{ session.type_label }}
                                </span>
                            </div>

                            <!-- Date -->
                            <div class="md:col-span-2 text-sm text-neutral-600">
                                <p>{{ session.scheduled_date_short }}</p>
                                <p class="text-xs text-neutral-400">{{ session.scheduled_time }}</p>
                            </div>

                            <!-- Attendance numbers -->
                            <div class="md:col-span-2 text-center">
                                <span v-if="session.attendances_count != null" class="text-sm font-medium text-neutral-700">
                                    {{ session.present_count ?? 0 }}
                                    <span class="text-neutral-400 font-normal">/</span>
                                    {{ session.attendances_count }}
                                </span>
                                <span v-else class="text-xs text-neutral-300">—</span>
                            </div>

                            <!-- Rate badge -->
                            <div class="md:col-span-1 text-center">
                                <span
                                    v-if="session.attendance_rate != null"
                                    :class="['text-sm font-semibold', rateColor(session.attendance_rate)]"
                                >
                                    {{ session.attendance_rate }}%
                                </span>
                                <span v-else class="text-xs text-neutral-300">—</span>
                            </div>

                            <!-- Chevron -->
                            <div class="md:col-span-1 flex justify-end">
                                <ChevronRight class="w-4 h-4 text-neutral-300 group-hover:text-neutral-500 transition-colors" />
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="sessions.meta?.last_page > 1" class="mt-6">
            <AppPagination :links="sessions.meta?.links ?? []" />
        </div>
    </DashboardLayout>
</template>
