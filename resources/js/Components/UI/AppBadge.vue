<script setup lang="ts">
withDefaults(defineProps<{
    /**
     * Color variant.
     * Prefer semantic names: success | warning | error | info | brand | neutral
     * Legacy Tailwind color names still accepted: emerald | amber | rose | blue | violet
     */
    color?: 'brand' | 'success' | 'warning' | 'error' | 'info' | 'neutral'
          | 'emerald' | 'amber' | 'rose' | 'blue' | 'violet'
    size?:  'sm' | 'md'
    /** Prepend a small colored dot — useful for status labels like "● Active" */
    dot?:   boolean
}>(), {
    color: 'neutral',
    size:  'md',
    dot:   false,
})

const colorMap: Record<string, string> = {
    // Semantic (preferred)
    brand:   'bg-brand-50   text-brand-700',
    success: 'bg-emerald-50 text-emerald-700',
    warning: 'bg-amber-50   text-amber-700',
    error:   'bg-rose-50    text-rose-700',
    info:    'bg-blue-50    text-blue-700',
    neutral: 'bg-neutral-100 text-neutral-600',
    // Legacy aliases
    emerald: 'bg-emerald-50 text-emerald-700',
    amber:   'bg-amber-50   text-amber-700',
    rose:    'bg-rose-50    text-rose-700',
    blue:    'bg-blue-50    text-blue-700',
    violet:  'bg-brand-50  text-brand-700',
}

const dotColorMap: Record<string, string> = {
    brand:   'bg-brand-500',
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    error:   'bg-rose-500',
    info:    'bg-blue-500',
    neutral: 'bg-neutral-400',
    emerald: 'bg-emerald-500',
    amber:   'bg-amber-500',
    rose:    'bg-rose-500',
    blue:    'bg-blue-500',
    violet:  'bg-brand-500',
}

const sizeMap: Record<string, string> = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-2.5 py-1 text-xs',
}
</script>

<template>
    <span :class="['inline-flex items-center gap-1.5 font-medium rounded-full', colorMap[color], sizeMap[size]]">
        <span v-if="dot" :class="['w-1.5 h-1.5 rounded-full shrink-0', dotColorMap[color]]" />
        <slot />
    </span>
</template>
