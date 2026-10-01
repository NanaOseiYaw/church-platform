<script setup lang="ts">
withDefaults(defineProps<{
    eyebrow?: string
    title: string
    subtitle?: string
    centered?: boolean
}>(), {
    centered: false,
})
</script>

<template>
    <div :class="['mb-12 md:mb-16', centered && 'text-center mx-auto max-w-2xl']">
        <p v-if="eyebrow" class="text-sm font-semibold tracking-widest uppercase text-brand-500 mb-3">
            {{ eyebrow }}
        </p>
        <h2 class="text-3xl md:text-4xl font-display text-neutral-900 leading-tight mb-4">
            {{ title }}
        </h2>
        <p v-if="subtitle" class="text-lg text-neutral-500 leading-relaxed">
            {{ subtitle }}
        </p>
    </div>
</template>
