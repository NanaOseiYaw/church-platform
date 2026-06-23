<script setup lang="ts">
import { Mic2 } from 'lucide-vue-next'
import SermonCard from '@/Components/Sermons/SermonCard.vue'
import type { PublicSermon } from '@/types'

defineProps<{
    sermons:      PublicSermon[]
    emptyTitle?:  string
    emptyMessage?: string
}>()
</script>

<template>
    <!-- Populated grid -->
    <div
        v-if="sermons.length"
        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5"
    >
        <SermonCard
            v-for="sermon in sermons"
            :key="sermon.id"
            :sermon="sermon"
        />
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-20">
        <div class="w-14 h-14 bg-neutral-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <Mic2 class="w-7 h-7 text-neutral-300" />
        </div>
        <p class="font-semibold text-neutral-600">{{ emptyTitle ?? 'No sermons found' }}</p>
        <p class="text-sm text-neutral-400 mt-1">{{ emptyMessage ?? 'Check back soon.' }}</p>
    </div>
</template>
