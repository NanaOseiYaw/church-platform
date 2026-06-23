<script setup lang="ts">
/**
 * AttendanceCheckInButton
 *
 * A compact four-segment control for quickly toggling a member's
 * attendance status: Present · Late · Excused · Absent.
 *
 * Clicking the active status deselects it (sets status back to null).
 * Emits `update:status` so the parent can track changes.
 */
import type { AttendanceStatus } from '@/types'

const props = defineProps<{
    status: AttendanceStatus | null
    disabled?: boolean
}>()

const emit = defineEmits<{
    'update:status': [status: AttendanceStatus | null]
}>()

interface Option {
    value: AttendanceStatus
    label: string
    activeClasses: string
    hoverClasses: string
}

const options: Option[] = [
    {
        value: 'present',
        label: 'P',
        activeClasses: 'bg-emerald-500 text-white border-emerald-500 shadow-sm',
        hoverClasses:  'hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-300',
    },
    {
        value: 'late',
        label: 'L',
        activeClasses: 'bg-amber-500 text-white border-amber-500 shadow-sm',
        hoverClasses:  'hover:bg-amber-50 hover:text-amber-700 hover:border-amber-300',
    },
    {
        value: 'excused',
        label: 'E',
        activeClasses: 'bg-blue-500 text-white border-blue-500 shadow-sm',
        hoverClasses:  'hover:bg-blue-50 hover:text-blue-700 hover:border-blue-300',
    },
    {
        value: 'absent',
        label: 'A',
        activeClasses: 'bg-rose-500 text-white border-rose-500 shadow-sm',
        hoverClasses:  'hover:bg-rose-50 hover:text-rose-700 hover:border-rose-300',
    },
]

function select(value: AttendanceStatus) {
    if (props.disabled) return
    // Clicking the active status deselects (clear)
    emit('update:status', props.status === value ? null : value)
}
</script>

<template>
    <div
        class="inline-flex rounded-lg border border-neutral-200 overflow-hidden"
        :class="disabled ? 'opacity-50 pointer-events-none' : ''"
        role="group"
        aria-label="Attendance status"
    >
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            :class="[
                'w-8 h-7 text-xs font-semibold border-r border-neutral-200 last:border-r-0 transition-colors focus:outline-none focus:z-10',
                status === opt.value
                    ? opt.activeClasses
                    : ['text-neutral-400 bg-white', opt.hoverClasses],
            ]"
            :title="opt.value.charAt(0).toUpperCase() + opt.value.slice(1)"
            :aria-pressed="status === opt.value"
            @click="select(opt.value)"
        >
            {{ opt.label }}
        </button>
    </div>
</template>
