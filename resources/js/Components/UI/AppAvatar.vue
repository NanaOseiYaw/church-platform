<script setup lang="ts">
withDefaults(defineProps<{
    name?: string
    src?: string | null
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
    color?: string
}>(), { size: 'md' })

function initials(name?: string) {
    if (!name) return '?'
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}

const sizes = {
    xs: 'w-6 h-6 text-[10px]',
    sm: 'w-8 h-8 text-xs',
    md: 'w-9 h-9 text-sm',
    lg: 'w-11 h-11 text-base',
    xl: 'w-14 h-14 text-lg',
}
</script>

<template>
    <div :class="['rounded-full flex items-center justify-center shrink-0 overflow-hidden', sizes[size]]">
        <img v-if="src" :src="src" :alt="name" class="w-full h-full object-cover" />
        <div v-else class="w-full h-full gradient-brand flex items-center justify-center">
            <span class="font-semibold text-white leading-none">{{ initials(name) }}</span>
        </div>
    </div>
</template>
