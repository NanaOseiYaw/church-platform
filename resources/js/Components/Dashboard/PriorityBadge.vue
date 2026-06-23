<script setup lang="ts">
import type { AnnouncementPriority } from '@/types'

defineProps<{ priority: AnnouncementPriority | string }>()

const config: Record<string, { label: string; classes: string; dot: string }> = {
    low:    { label: 'Low',    classes: 'bg-neutral-100 text-neutral-500',  dot: 'bg-neutral-400' },
    medium: { label: 'Normal', classes: 'bg-blue-50 text-blue-600',         dot: 'bg-blue-400' },
    high:   { label: 'High',   classes: 'bg-amber-50 text-amber-700',       dot: 'bg-amber-400' },
    urgent: { label: 'Urgent', classes: 'bg-rose-50 text-rose-600',         dot: 'bg-rose-500' },
}

function get(p: string) {
    return config[p] ?? config.medium
}
</script>

<template>
    <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold', get(priority).classes]">
        <span :class="['w-1.5 h-1.5 rounded-full', get(priority).dot]" />
        {{ get(priority).label }}
    </span>
</template>
