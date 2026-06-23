<!-- resources/js/Components/Dashboard/UpcomingAssignmentsWidget.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { CalendarDays, ArrowRight } from 'lucide-vue-next'

interface AssignmentRow {
    id: number
    status: string
    plan_title: string
    plan_status: string
    scheduled_at_formatted: string
    position: string | null
}

defineProps<{ assignments: AssignmentRow[] }>()

const statusStyles: Record<string, string> = {
    pending:   'bg-yellow-100 text-yellow-700',
    confirmed: 'bg-green-100 text-green-700',
}

function statusLabel(s: string): string {
    if (s === 'pending')   return 'Pending'
    if (s === 'confirmed') return 'Confirmed'
    return s
}
</script>

<template>
    <div class="bg-white border border-neutral-100 rounded-2xl p-5">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-neutral-900 flex items-center gap-2">
                <CalendarDays class="w-4 h-4 text-indigo-500" />
                My Upcoming Schedule
            </h3>
            <Link
                href="/dashboard/scheduling/my-schedule"
                class="flex items-center gap-1 text-xs text-indigo-600 hover:underline"
            >
                View all
                <ArrowRight class="w-3 h-3" />
            </Link>
        </div>

        <!-- Assignment list -->
        <div v-if="assignments.length > 0" class="space-y-2">
            <Link
                v-for="a in assignments"
                :key="a.id"
                href="/dashboard/scheduling/my-schedule"
                class="flex items-start justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2.5 hover:bg-indigo-50/40 transition-colors"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ a.plan_title }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ a.scheduled_at_formatted }}
                        <span v-if="a.position" class="text-indigo-600"> · {{ a.position }}</span>
                    </p>
                    <span
                        v-if="a.plan_status === 'draft'"
                        class="mt-1 inline-block text-[10px] font-medium bg-yellow-50 text-yellow-600 border border-yellow-200 px-1.5 py-0.5 rounded"
                    >
                        Draft schedule
                    </span>
                </div>
                <span
                    :class="['shrink-0 mt-0.5 text-[11px] font-medium px-2 py-0.5 rounded-full', statusStyles[a.status] ?? 'bg-neutral-100 text-neutral-600']"
                >
                    {{ statusLabel(a.status) }}
                </span>
            </Link>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-6 text-center">
            <CalendarDays class="w-8 h-8 text-gray-200 mb-2" />
            <p class="text-sm text-gray-400">No upcoming assignments</p>
            <Link
                href="/dashboard/scheduling"
                class="mt-1 text-xs text-indigo-500 hover:underline"
            >
                View scheduling →
            </Link>
        </div>
    </div>
</template>
