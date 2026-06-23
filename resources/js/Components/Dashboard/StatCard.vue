<script setup lang="ts">
import type { Component } from 'vue'
import AppSkeleton from '@/Components/UI/AppSkeleton.vue'
import AppCard from '@/Components/UI/AppCard.vue'

defineProps<{
    label:    string
    value:    string | number
    icon?:    Component
    trend?:   { value: string; direction: 'up' | 'down' | 'flat' }
    color?:   'brand' | 'emerald' | 'amber' | 'rose' | 'violet' | 'blue'
    loading?: boolean
}>()

const colorMap: Record<string, string> = {
    brand:   'bg-brand-50   text-brand-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    amber:   'bg-amber-50   text-amber-600',
    rose:    'bg-rose-50    text-rose-600',
    violet:  'bg-brand-50  text-brand-600',
    blue:    'bg-blue-50    text-blue-600',
}
</script>

<template>
    <!-- rounded-xl (standardized from rounded-2xl), shadow-card (design token) -->
    <AppCard padding="sm">
        <!-- Loading skeleton -->
        <template v-if="loading">
            <div class="flex items-center justify-between mb-4">
                <AppSkeleton type="rect" class="w-9 h-9 rounded-xl" />
            </div>
            <AppSkeleton class="h-7 w-2/3 mb-2" />
            <AppSkeleton class="h-3 w-1/2" />
        </template>

        <!-- Content -->
        <template v-else>
            <div v-if="icon || trend" class="flex items-center justify-between mb-4">
                <div
                    v-if="icon"
                    :class="['w-9 h-9 rounded-xl flex items-center justify-center', colorMap[color ?? 'brand']]"
                >
                    <component :is="icon" class="w-[18px] h-[18px]" />
                </div>
                <span
                    v-if="trend"
                    :class="[
                        'text-xs font-semibold px-2 py-0.5 rounded-full',
                        trend.direction === 'up'   ? 'bg-emerald-50 text-emerald-600' :
                        trend.direction === 'down' ? 'bg-rose-50    text-rose-600'    :
                                                     'bg-neutral-100 text-neutral-500',
                    ]"
                >
                    {{ trend.direction === 'up' ? '↑' : trend.direction === 'down' ? '↓' : '→' }} {{ trend.value }}
                </span>
            </div>
            <p class="text-2xl font-semibold text-neutral-900 tracking-tight mb-0.5">{{ value }}</p>
            <p class="text-xs text-neutral-500">{{ label }}</p>
        </template>
    </AppCard>
</template>
