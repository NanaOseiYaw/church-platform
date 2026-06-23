<script setup lang="ts">
import { CheckCircle2 } from 'lucide-vue-next'

type TimelineType = 'attendance' | 'task_assigned' | 'task_completed' | 'dept_joined' | 'dept_left' | 'role_changed'

interface TimelineEntry {
    type: TimelineType
    label: string
    meta: string | null
    timestamp: string
    formatted: string
}

defineProps<{ entries: TimelineEntry[] }>()

const typeConfig: Record<TimelineType, { dot: string }> = {
    attendance:     { dot: 'bg-emerald-500' },
    task_assigned:  { dot: 'bg-blue-500'    },
    task_completed: { dot: 'bg-indigo-500'  },
    dept_joined:    { dot: 'bg-brand-500'  },
    dept_left:      { dot: 'bg-neutral-400' },
    role_changed:   { dot: 'bg-amber-500'   },
}

function dot(type: TimelineType): string {
    return typeConfig[type]?.dot ?? 'bg-neutral-300'
}
</script>

<template>
    <div v-if="entries.length === 0" class="py-12 text-center">
        <CheckCircle2 class="w-7 h-7 text-neutral-200 mx-auto mb-2" />
        <p class="text-sm text-neutral-400">No activity recorded yet.</p>
    </div>

    <ol v-else class="relative pl-5 space-y-0">
        <li
            v-for="(entry, i) in entries"
            :key="i"
            class="relative pb-5 last:pb-0"
        >
            <!-- Vertical line -->
            <div
                v-if="i < entries.length - 1"
                class="absolute left-[-8px] top-3 bottom-0 w-px bg-neutral-100"
            />

            <!-- Dot -->
            <span :class="['absolute left-[-11px] top-[7px] w-2.5 h-2.5 rounded-full ring-2 ring-white', dot(entry.type)]" />

            <!-- Content -->
            <div class="ml-2">
                <p class="text-sm text-neutral-800 leading-snug">{{ entry.label }}</p>
                <p v-if="entry.meta" class="text-xs text-neutral-400 mt-0.5">{{ entry.meta }}</p>
                <p class="text-xs text-neutral-300 mt-0.5">{{ entry.formatted }}</p>
            </div>
        </li>
    </ol>
</template>
