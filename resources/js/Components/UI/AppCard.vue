<script setup lang="ts">
/**
 * AppCard — the standard surface container for all dashboard modules.
 *
 * Replaces the ad-hoc `bg-white border border-neutral-100 rounded-xl/2xl` pattern
 * used throughout the codebase with a single, consistent component.
 *
 * Variants:
 *   default  — white bg, subtle border, card shadow (standard dashboard card)
 *   flat     — white bg, border only, no shadow (table wrappers, filter panels)
 *   elevated — white bg, stronger shadow, no border (modals, popovers)
 *   ghost    — off-white bg, no border, no shadow (secondary content areas)
 *
 * Padding presets:
 *   none — no padding (use for tables, images, full-bleed content)
 *   sm   — p-4 (compact cards)
 *   md   — p-5 lg:p-6 (standard)
 *   lg   — p-6 lg:p-8 (spacious)
 *
 * Usage:
 *   <AppCard>Standard card</AppCard>
 *   <AppCard padding="none" class="overflow-hidden">Table card</AppCard>
 *   <AppCard variant="elevated" padding="lg">Featured content</AppCard>
 *   <AppCard :hover="true" as="button" @click="handleClick">Clickable card</AppCard>
 */
withDefaults(defineProps<{
    variant?: 'default' | 'flat' | 'elevated' | 'ghost'
    padding?: 'none' | 'sm' | 'md' | 'lg'
    hover?:   boolean
    /** Rendered HTML element or component — useful for `as="button"` or `as="article"` */
    as?:      string
}>(), {
    variant: 'default',
    padding: 'md',
    hover:   false,
    as:      'div',
})
</script>

<template>
    <component
        :is="as"
        :class="[
            'rounded-xl transition-all duration-200',
            // Variant
            variant === 'default'  && 'bg-white border border-neutral-100 shadow-card',
            variant === 'flat'     && 'bg-white border border-neutral-100',
            variant === 'elevated' && 'bg-white shadow-elevated',
            variant === 'ghost'    && 'bg-neutral-50/80',
            // Padding
            padding === 'none' && '',
            padding === 'sm'   && 'p-4',
            padding === 'md'   && 'p-5 lg:p-6',
            padding === 'lg'   && 'p-6 lg:p-8',
            // Hover lift — for clickable cards
            hover && 'hover:shadow-elevated hover:-translate-y-0.5 cursor-pointer',
        ]"
    >
        <slot />
    </component>
</template>
