<script setup lang="ts">
import { Play, Headphones, Clock, Mic2 } from 'lucide-vue-next'
import type { PublicSermon } from '@/types'

const props = defineProps<{
    sermon: PublicSermon
    href?:  string
}>()

const link = props.href ?? `/sermons/${props.sermon.slug ?? props.sermon.id}`
</script>

<template>
    <a
        :href="link"
        class="group block bg-white rounded-2xl overflow-hidden border border-neutral-100 hover:border-neutral-200 hover:shadow-lg transition-all duration-300"
    >
        <!-- Thumbnail -->
        <div class="relative aspect-video overflow-hidden bg-neutral-900">
            <img
                v-if="sermon.thumbnail"
                :src="sermon.thumbnail"
                :alt="sermon.title"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                loading="lazy"
            />
            <div
                v-else
                class="w-full h-full flex items-center justify-center bg-gradient-to-br from-neutral-800 to-neutral-900"
            >
                <Mic2 class="w-10 h-10 text-neutral-600 group-hover:text-neutral-500 transition-colors" />
            </div>

            <!-- Play overlay -->
            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 bg-black/30">
                <div class="w-14 h-14 bg-white rounded-full flex items-center justify-center shadow-xl">
                    <Play class="w-5 h-5 text-neutral-900 fill-neutral-900 translate-x-0.5" />
                </div>
            </div>

            <!-- Duration badge -->
            <div
                v-if="sermon.duration"
                class="absolute bottom-2.5 right-2.5 flex items-center gap-1 bg-black/70 backdrop-blur-sm text-white text-xs px-2.5 py-1 rounded-full"
            >
                <Clock class="w-2.5 h-2.5" />
                {{ sermon.duration }}
            </div>

            <!-- Audio-only badge -->
            <div
                v-if="!sermon.embed_url && sermon.audio_url"
                class="absolute top-2.5 left-2.5 flex items-center gap-1 bg-amber-500 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full"
            >
                <Headphones class="w-2.5 h-2.5" /> Audio
            </div>
        </div>

        <!-- Content -->
        <div class="p-4">
            <!-- Series label -->
            <p
                v-if="sermon.series"
                class="text-[10px] font-bold uppercase tracking-widest text-brand-500 mb-1.5"
            >
                {{ sermon.series }}
            </p>

            <!-- Title -->
            <h3 class="font-semibold text-neutral-900 text-sm leading-snug line-clamp-2 mb-2 group-hover:text-brand-700 transition-colors">
                {{ sermon.title }}
            </h3>

            <!-- Speaker + date -->
            <div class="flex items-center justify-between text-xs text-neutral-400 mt-auto pt-2 border-t border-neutral-50">
                <span class="font-medium text-neutral-600 truncate mr-2">{{ sermon.speaker ?? 'Unknown' }}</span>
                <span class="shrink-0">{{ sermon.preached_at_formatted }}</span>
            </div>
        </div>
    </a>
</template>
