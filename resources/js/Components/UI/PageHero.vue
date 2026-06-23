<script setup lang="ts">
withDefaults(defineProps<{
    eyebrow?: string
    title:    string
    subtitle?: string
    size?:    'sm' | 'md' | 'lg'
}>(), {
    size: 'md',
})

const paddingMap = {
    sm: 'py-20 md:py-24',
    md: 'py-24 md:py-32',
    lg: 'py-28 md:py-36',
}
</script>

<template>
    <section class="relative gradient-dark-mesh overflow-hidden">
        <!-- Top edge line -->
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/30 to-transparent" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-7xl px-6 lg:px-8" :class="paddingMap[size]">
            <!-- Eyebrow -->
            <div v-if="eyebrow" class="flex items-center gap-3 mb-6 reveal">
                <div class="h-px w-8 bg-brand-500 shrink-0"></div>
                <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-400">{{ eyebrow }}</span>
            </div>

            <!-- Title slot or prop -->
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-serif font-normal text-white leading-tight max-w-3xl reveal reveal-delay-1">
                <slot name="title">{{ title }}</slot>
            </h1>

            <!-- Subtitle -->
            <p v-if="subtitle" class="mt-5 text-lg text-white/45 max-w-xl leading-relaxed reveal reveal-delay-2">
                {{ subtitle }}
            </p>

            <!-- Extra slot for CTAs / tags below -->
            <div v-if="$slots.actions" class="mt-8 reveal reveal-delay-3">
                <slot name="actions" />
            </div>
        </div>

        <!-- Bottom fade to next section -->
        <div class="absolute bottom-0 left-0 right-0 h-20 bg-gradient-to-t from-white to-transparent pointer-events-none" aria-hidden="true"></div>
    </section>
</template>
