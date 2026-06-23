<script setup lang="ts">
import type { AttendanceStatus } from '@/types'

const props = withDefaults(defineProps<{
    status: AttendanceStatus | null
    size?: 'sm' | 'md'
}>(), {
    size: 'md',
})

const config: Record<NonNullable<typeof props.status>, { label: string; classes: string; dot: string }> = {
    present: { label: 'Present', classes: 'bg-emerald-50 text-emerald-700 ring-emerald-200',  dot: 'bg-emerald-500' },
    late:    { label: 'Late',    classes: 'bg-amber-50  text-amber-700  ring-amber-200',      dot: 'bg-amber-500'  },
    excused: { label: 'Excused', classes: 'bg-blue-50   text-blue-700   ring-blue-200',       dot: 'bg-blue-500'   },
    absent:  { label: 'Absent',  classes: 'bg-rose-50   text-rose-700   ring-rose-200',       dot: 'bg-rose-400'   },
}

const noRecord = { label: 'Unmarked', classes: 'bg-neutral-50 text-neutral-400 ring-neutral-200', dot: 'bg-neutral-300' }

const current = props.status ? (config[props.status] ?? noRecord) : noRecord
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 font-medium ring-1 ring-inset rounded-full',
            current.classes,
            size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs',
        ]"
    >
        <span :class="['rounded-full shrink-0', current.dot, size === 'sm' ? 'w-1.5 h-1.5' : 'w-2 h-2']" />
        {{ current.label }}
    </span>
</template>
