<!-- resources/js/Components/Scheduling/DeptSidebar.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Building2, Plus, CalendarCheck2, ArrowRight } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'
import type { ServicePlan, ServicePlanPosition } from '@/types'

interface DeptGroup {
    id: number
    name: string
    icon: string | null
    color: string | null
    positions: ServicePlanPosition[]
}

interface AttendanceSessionLink {
    id: number
    title: string
    status: string
}

const props = defineProps<{
    plan: ServicePlan
    deptGroups: DeptGroup[]
    selectedDeptId: number | null
    canManage: boolean
    canPublish: boolean
    attendanceSession: AttendanceSessionLink | null
}>()

const emit = defineEmits<{
    selectDept: [id: number]
    addPosition: []
    publish: []
    archive: []
}>()

function filledCount(positions: ServicePlanPosition[]): number {
    return positions.filter(p => p.is_filled).length
}

function publishPlan() {
    router.patch(`/dashboard/scheduling/plans/${props.plan.id}/publish`)
}

function archivePlan() {
    if (confirm('Archive this plan? It will become read-only.')) {
        router.patch(`/dashboard/scheduling/plans/${props.plan.id}/archive`)
    }
}
</script>

<template>
    <aside class="flex w-60 flex-shrink-0 flex-col border-r border-gray-200 bg-white">
        <!-- Plan header -->
        <div class="border-b border-gray-100 px-4 py-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900">{{ plan.title }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ plan.scheduled_at_formatted }}
                        <span v-if="plan.scheduled_time"> · {{ plan.scheduled_time }}</span>
                    </p>
                    <p v-if="plan.location" class="text-xs text-gray-400">{{ plan.location }}</p>
                </div>
                <StatusBadge :status="plan.status" />
            </div>
        </div>

        <!-- Department list -->
        <nav class="flex-1 overflow-y-auto py-2">
            <button
                v-for="group in deptGroups"
                :key="group.id"
                type="button"
                :class="[
                    'flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm transition',
                    selectedDeptId === group.id
                        ? 'bg-brand-50 text-brand-700 font-medium'
                        : 'text-gray-700 hover:bg-gray-50',
                ]"
                @click="emit('selectDept', group.id)"
            >
                <Building2 class="h-4 w-4 flex-shrink-0 text-gray-400" />
                <span class="min-w-0 flex-1 truncate">{{ group.name }}</span>
                <span class="text-xs font-mono text-gray-400">
                    {{ filledCount(group.positions) }}/{{ group.positions.length }}
                </span>
            </button>

            <button
                v-if="canManage && plan.status !== 'archived'"
                type="button"
                class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-brand-600 hover:bg-brand-50"
                @click="emit('addPosition')"
            >
                <Plus class="h-4 w-4" />
                Add position
            </button>
        </nav>

        <!-- Actions -->
        <div v-if="canManage || canPublish" class="border-t border-gray-100 px-4 py-3 space-y-2">
            <button
                v-if="canPublish && plan.status === 'draft'"
                type="button"
                class="w-full rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700"
                @click="publishPlan"
            >
                Publish Plan
            </button>
            <button
                v-if="canManage && plan.status !== 'archived'"
                type="button"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                @click="archivePlan"
            >
                Archive Plan
            </button>
        </div>

        <!-- Attendance session link -->
        <div class="border-t border-gray-100 px-4 py-3">
            <p class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-2">
                <CalendarCheck2 class="h-3 w-3" />
                Attendance
            </p>
            <template v-if="attendanceSession">
                <a
                    :href="`/dashboard/attendance/${attendanceSession.id}`"
                    class="flex items-center gap-1.5 text-xs font-medium text-brand-600 hover:text-brand-800 hover:underline truncate"
                >
                    <span class="truncate">{{ attendanceSession.title }}</span>
                    <ArrowRight class="h-3 w-3 shrink-0" />
                </a>
                <p class="text-[10px] text-gray-400 mt-0.5 capitalize">{{ attendanceSession.status }}</p>
            </template>
            <template v-else-if="canManage">
                <a
                    :href="`/dashboard/attendance/create?service_plan_id=${plan.id}`"
                    class="flex items-center gap-1 text-xs text-brand-600 hover:underline"
                >
                    Track attendance
                    <ArrowRight class="h-3 w-3" />
                </a>
            </template>
            <p v-else class="text-xs text-gray-400 italic">Not linked</p>
        </div>
    </aside>
</template>
