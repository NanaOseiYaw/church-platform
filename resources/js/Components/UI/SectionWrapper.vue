<script setup lang="ts">
withDefaults(defineProps<{
    id?: string
    bg?: 'white' | 'surface' | 'dark' | 'brand' | 'none'
    size?: 'sm' | 'md' | 'lg'
    centered?: boolean
}>(), {
    bg: 'white',
    size: 'md',
    centered: false,
})

const bgMap = {
    white:   'bg-white',
    surface: 'bg-neutral-50',
    dark:    'bg-neutral-950 text-white',
    brand:   'gradient-brand text-white',
    none:    '',
}

const sizeMap = {
    sm: 'py-16 md:py-20',
    md: 'py-20 md:py-28',
    lg: 'py-28 md:py-36',
}
</script>

<template>
    <section :id="id" :class="[bgMap[bg], sizeMap[size]]">
        <div :class="['mx-auto max-w-7xl px-6 lg:px-8', centered && 'text-center']">
            <slot />
        </div>
    </section>
</template>
