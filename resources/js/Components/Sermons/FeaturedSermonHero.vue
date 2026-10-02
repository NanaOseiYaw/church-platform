<script setup lang="ts">
import { Play, Clock, ChevronRight } from 'lucide-vue-next'
import type { PublicSermon } from '@/types'

defineProps<{ sermon: PublicSermon }>()
</script>

<template>
    <a
        :href="`/sermons/${sermon.slug ?? sermon.id}`"
        class="group block relative overflow-hidden rounded-2xl"
    >
        <!-- Background image / gradient -->
        <!--
            w-full is load-bearing. With an aspect ratio and a min-height, the
            browser carries the 280px minimum height through the ratio into a
            minimum width (280 x 16/7 = 640px), so on a phone the card was laid
            out twice as wide as the screen and the right half was clipped. A
            definite width stops that transfer.
        -->
        <div class="relative w-full aspect-[16/7] sm:aspect-[16/6] lg:aspect-[16/5] min-h-[280px] overflow-hidden">
            <img
                v-if="sermon.thumbnail"
                :src="sermon.thumbnail"
                :alt="sermon.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
            />
            <div
                v-else
                class="w-full h-full bg-gradient-to-br from-neutral-900 via-brand-950 to-neutral-950"
            />

            <!-- Gradient overlay — stronger at bottom for text legibility -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

            <!-- Content anchored to bottom-left -->
            <div class="absolute inset-0 flex flex-col justify-end p-6 sm:p-8 lg:p-10">

                <!-- Series + featured badge -->
                <div class="flex items-center gap-2 mb-3 flex-wrap">
                    <span
                        v-if="sermon.series"
                        class="text-[10px] font-bold uppercase tracking-widest text-brand-400"
                    >
                        {{ sermon.series }}
                    </span>
                    <span class="w-1 h-1 rounded-full bg-white/30 hidden sm:inline-block" />
                    <span class="inline-flex items-center gap-1 bg-brand-500/90 text-white text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full">
                        ★ Featured
                    </span>
                </div>

                <!-- Title -->
                <h2 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white leading-tight mb-3 max-w-2xl group-hover:text-brand-200 transition-colors">
                    {{ sermon.title }}
                </h2>

                <!-- Meta row -->
                <div class="flex items-center gap-4 text-sm text-white/70 flex-wrap">
                    <span v-if="sermon.speaker" class="font-medium text-white/90">{{ sermon.speaker }}</span>
                    <span v-if="sermon.preached_at_formatted" class="text-white/60">
                        {{ sermon.preached_at_formatted }}
                    </span>
                    <span
                        v-if="sermon.duration"
                        class="flex items-center gap-1 text-white/60"
                    >
                        <Clock class="w-3.5 h-3.5" />
                        {{ sermon.duration }}
                    </span>
                </div>

                <!-- Watch CTA -->
                <div class="mt-4">
                    <span class="inline-flex items-center gap-2 bg-white text-neutral-900 font-semibold text-sm px-4 py-2.5 rounded-full shadow-lg group-hover:bg-brand-500 group-hover:text-white transition-all duration-300">
                        <Play class="w-4 h-4 fill-current" />
                        Watch Now
                        <ChevronRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
                    </span>
                </div>
            </div>
        </div>
    </a>
</template>
