<script setup lang="ts">
import type { Component } from 'vue'
defineProps<{
    icon?: Component
    title: string
    description?: string
}>()
</script>

<template>
    <div class="flex flex-col items-center justify-center py-16 text-center px-4">
        <div v-if="icon" class="w-12 h-12 bg-neutral-100 rounded-2xl flex items-center justify-center mb-4">
            <component :is="icon" class="w-6 h-6 text-neutral-400" />
        </div>
        <p class="text-sm font-semibold text-neutral-700 mb-1">{{ title }}</p>
        <p v-if="description" class="text-sm text-neutral-400 max-w-xs">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
