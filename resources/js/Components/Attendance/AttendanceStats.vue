<script setup lang="ts">
/**
 * AttendanceStats
 *
 * A row of stat chips used on the session Show page and the Index
 * dashboard header.  Accepts the raw counts; the parent decides layout.
 */
import { CalendarCheck2, Users, TrendingUp, Clock } from 'lucide-vue-next'
import type { AttendanceSession } from '@/types'

const props = defineProps<{
    /** If session passed, auto-derive stats from it */
    session?: AttendanceSession | null
    /** Or pass stats directly for the dashboard header */
    totalSessions?: number
    totalPresent?: number
    avgRate?: number | null
    variant?: 'session' | 'dashboard'
}>()

interface StatItem {
    label: string
    value: string | number
    icon: any
    colorClass: string
}

function sessionStats(): StatItem[] {
    const s = props.session
    if (!s) return []
    const total = s.attendances_count ?? 0

    return [
        {
            label: 'Total',
            value: total,
            icon: Users,
            colorClass: 'text-neutral-600 bg-neutral-100',
        },
        {
            label: 'Present',
            value: s.present_count ?? 0,
            icon: CalendarCheck2,
            colorClass: 'text-emerald-600 bg-emerald-50',
        },
        {
            label: 'Late',
            value: s.late_count ?? 0,
            icon: Clock,
            colorClass: 'text-amber-600 bg-amber-50',
        },
        {
            label: 'Absent',
            value: s.absent_count ?? 0,
            icon: CalendarCheck2,
            colorClass: 'text-rose-600 bg-rose-50',
        },
        {
            label: 'Excused',
            value: s.excused_count ?? 0,
            icon: CalendarCheck2,
            colorClass: 'text-blue-600 bg-blue-50',
        },
    ]
}

function dashboardStats(): StatItem[] {
    return [
        {
            label: 'Sessions this week',
            value: props.totalSessions ?? 0,
            icon: CalendarCheck2,
            colorClass: 'text-brand-600 bg-brand-50',
        },
        {
            label: 'Present this month',
            value: props.totalPresent ?? 0,
            icon: Users,
            colorClass: 'text-emerald-600 bg-emerald-50',
        },
        {
            label: 'Avg. rate (30d)',
            value: props.avgRate != null ? `${props.avgRate}%` : '—',
            icon: TrendingUp,
            colorClass: 'text-brand-600 bg-brand-50',
        },
    ]
}

const stats = props.variant === 'dashboard' ? dashboardStats() : sessionStats()
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div
            v-for="stat in stats"
            :key="stat.label"
            class="bg-white border border-neutral-100 rounded-xl p-4"
        >
            <div :class="['w-8 h-8 rounded-lg flex items-center justify-center mb-2', stat.colorClass]">
                <component :is="stat.icon" class="w-4 h-4" />
            </div>
            <p class="text-xl font-semibold text-neutral-900">{{ stat.value }}</p>
            <p class="text-xs text-neutral-500 mt-0.5">{{ stat.label }}</p>
        </div>
    </div>
</template>
