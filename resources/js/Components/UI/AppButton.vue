<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Loader2 } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
    href?:     string
    variant?:  'primary' | 'secondary' | 'ghost' | 'outline' | 'danger'
    size?:     'xs' | 'sm' | 'md' | 'lg'
    /**
     * When true: renders a square button suitable for icon-only usage.
     * Replaces the asymmetric text-button padding with equal padding on all sides.
     * Example: <AppButton square size="sm" variant="ghost"><Pencil /></AppButton>
     */
    square?:   boolean
    external?: boolean
    disabled?: boolean
    loading?:  boolean
    type?:     'button' | 'submit' | 'reset'
}>(), {
    variant: 'primary',
    size:    'md',
    square:  false,
    type:    'button',
})

const base =
    'inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-all duration-200 ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 ' +
    'disabled:opacity-50 disabled:cursor-not-allowed select-none'

const variants: Record<string, string> = {
    primary:   'bg-brand-600 text-white hover:bg-brand-700 active:bg-brand-800 shadow-sm',
    secondary: 'bg-brand-50 text-brand-700 hover:bg-brand-100 active:bg-brand-200',
    outline:   'border border-neutral-200 text-neutral-700 bg-white hover:bg-neutral-50 hover:border-neutral-300',
    ghost:     'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900',
    danger:    'border border-rose-200 text-rose-600 bg-white hover:bg-rose-50 hover:border-rose-300',
}

type Size = 'xs' | 'sm' | 'md' | 'lg'

/** Padding for text buttons (asymmetric: more horizontal than vertical) */
const sizes: Record<Size, string> = {
    xs: 'px-2.5 py-1.5 text-xs gap-1',
    sm: 'px-3.5 py-2 text-sm',
    md: 'px-5 py-2.5 text-sm',
    lg: 'px-7 py-3.5 text-base',
}

/** Padding for icon-only square buttons (equal on all sides) */
const squareSizes: Record<Size, string> = {
    xs: 'p-1.5 text-xs',
    sm: 'p-2 text-sm',
    md: 'p-2.5 text-sm',
    lg: 'p-3 text-base',
}

const isDisabled = computed(() => props.disabled || props.loading)
const classes    = computed(() => [
    base,
    variants[props.variant],
    props.square ? squareSizes[props.size] : sizes[props.size],
])
</script>

<template>
    <!-- Inertia Link (internal href) -->
    <Link
        v-if="href && !external"
        :href="href"
        :class="classes"
        :aria-disabled="isDisabled || undefined"
        @click="isDisabled ? $event.preventDefault() : undefined"
    >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        <slot />
    </Link>

    <!-- Native anchor (external href) -->
    <a
        v-else-if="href && external"
        :href="href"
        target="_blank"
        rel="noopener noreferrer"
        :class="classes"
        :aria-disabled="isDisabled || undefined"
        @click="isDisabled ? $event.preventDefault() : undefined"
    >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        <slot />
    </a>

    <!-- Button -->
    <button
        v-else
        :type="type"
        :disabled="isDisabled"
        :aria-disabled="isDisabled || undefined"
        :class="classes"
    >
        <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
        <slot />
    </button>
</template>
