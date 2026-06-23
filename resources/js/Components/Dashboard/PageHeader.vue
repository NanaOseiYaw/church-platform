<script setup lang="ts">
const props = defineProps<{
    title: string
    /** Alias for subtitle — prefer `description`. */
    subtitle?: string
    description?: string
    breadcrumbs?: { label: string; href?: string }[]
}>()

// Accept either prop name
const body = props.description ?? props.subtitle
</script>

<template>
    <div class="mb-7">
        <!-- Breadcrumbs -->
        <nav v-if="breadcrumbs?.length" class="flex items-center gap-1.5 text-xs text-neutral-400 mb-3">
            <template v-for="(crumb, i) in breadcrumbs" :key="i">
                <a v-if="crumb.href" :href="crumb.href" class="hover:text-neutral-600 transition-colors">{{ crumb.label }}</a>
                <span v-else :class="i === breadcrumbs.length - 1 ? 'text-neutral-600 font-medium' : ''">{{ crumb.label }}</span>
                <span v-if="i < breadcrumbs.length - 1" class="text-neutral-300">/</span>
            </template>
        </nav>

        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">{{ title }}</h1>
                <p v-if="body" class="text-sm text-neutral-500 mt-0.5">{{ body }}</p>
            </div>
            <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
