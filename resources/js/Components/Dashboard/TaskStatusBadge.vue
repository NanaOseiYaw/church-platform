<script setup lang="ts">
import type { TaskStatus, TaskPriority } from '@/types'
import { Circle, Clock, CheckCircle2, AlertTriangle, XCircle, Flame, ArrowUp, ArrowRight, ArrowDown } from 'lucide-vue-next'

const props = defineProps<{
    status?:   TaskStatus
    priority?: TaskPriority
}>()

// ── Status config ──────────────────────────────────────────────────────────────

const statusConfig: Record<TaskStatus, { label: string; classes: string; dot?: string; pulse?: boolean }> = {
    pending:     { label: 'Pending',     classes: 'bg-neutral-100 text-neutral-600',                  dot: 'bg-neutral-400' },
    in_progress: { label: 'In Progress', classes: 'bg-blue-50 text-blue-700',                         dot: 'bg-blue-500',  pulse: true },
    completed:   { label: 'Completed',   classes: 'bg-emerald-50 text-emerald-700',                   dot: 'bg-emerald-500' },
    overdue:     { label: 'Overdue',     classes: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',    dot: 'bg-rose-500' },
    cancelled:   { label: 'Cancelled',   classes: 'bg-neutral-100 text-neutral-400 line-through',     dot: 'bg-neutral-300' },
}

// ── Priority config ────────────────────────────────────────────────────────────

const priorityConfig: Record<TaskPriority, { label: string; classes: string; icon: unknown }> = {
    low:    { label: 'Low',    classes: 'text-neutral-400',    icon: ArrowDown },
    medium: { label: 'Medium', classes: 'text-blue-500',       icon: ArrowRight },
    high:   { label: 'High',   classes: 'text-amber-500',      icon: ArrowUp },
    urgent: { label: 'Urgent', classes: 'text-rose-600',       icon: Flame },
}
</script>

<template>
    <!-- Status badge -->
    <span
        v-if="status"
        class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium"
        :class="statusConfig[status].classes"
    >
        <span
            class="w-1.5 h-1.5 rounded-full shrink-0"
            :class="[statusConfig[status].dot, { 'animate-pulse': statusConfig[status].pulse }]"
        />
        {{ statusConfig[status].label }}
    </span>

    <!-- Priority badge -->
    <span
        v-if="priority"
        class="inline-flex items-center gap-1 text-xs font-medium"
        :class="priorityConfig[priority].classes"
    >
        <component :is="priorityConfig[priority].icon" class="w-3 h-3" />
        {{ priorityConfig[priority].label }}
    </span>
</template>
