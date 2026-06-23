<script setup lang="ts">
import type { Sermon } from '@/types'
import { Play, BookOpen, Clock, User } from 'lucide-vue-next'

defineProps<{ sermon: Sermon }>()

// sermon.date is pre-formatted by the controller — no new Date() needed.
</script>

<template>
    <article class="group bg-white border border-neutral-100 rounded-2xl overflow-hidden hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 cursor-pointer">
        <!-- Thumbnail — 16/9 cinematic -->
        <div class="relative aspect-video bg-neutral-950 overflow-hidden">
            <img
                v-if="sermon.thumbnail"
                :src="sermon.thumbnail"
                :alt="sermon.title"
                class="w-full h-full object-cover opacity-75 group-hover:opacity-90 group-hover:scale-105 transition-all duration-700"
            />
            <!-- No thumbnail: textured dark panel -->
            <div v-else class="absolute inset-0 bg-gradient-to-br from-neutral-800 to-neutral-950">
                <div class="absolute inset-0 opacity-5"
                    style="background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,0.4) 1px, transparent 0); background-size: 24px 24px;">
                </div>
            </div>

            <!-- Cinematic overlay gradient -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>

            <!-- Play button — centered -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="w-14 h-14 rounded-full bg-white/15 border border-white/25 backdrop-blur-sm flex items-center justify-center group-hover:bg-white/25 group-hover:scale-110 group-hover:border-white/40 transition-all duration-300">
                    <Play class="w-6 h-6 text-white fill-white ml-0.5" />
                </div>
            </div>

            <!-- Duration chip — bottom right -->
            <div class="absolute bottom-3 right-3">
                <span class="inline-flex items-center gap-1.5 bg-black/55 backdrop-blur-sm text-white text-[10px] font-medium px-2.5 py-1 rounded-full">
                    <Clock class="w-3 h-3" />
                    {{ sermon.duration }}
                </span>
            </div>
        </div>

        <!-- Content -->
        <div class="p-5">
            <!-- Series label -->
            <div class="flex items-center gap-1.5 mb-2.5">
                <BookOpen class="w-3 h-3 text-neutral-350 shrink-0" />
                <span class="text-xs text-neutral-400 font-medium truncate">{{ sermon.series }}</span>
            </div>

            <!-- Title -->
            <h3 class="font-semibold text-neutral-900 text-base leading-snug mb-3 group-hover:text-brand-600 transition-colors duration-200 line-clamp-2">
                {{ sermon.title }}
            </h3>

            <!-- Footer -->
            <div class="flex items-center justify-between text-xs text-neutral-400 pt-3 border-t border-neutral-75">
                <div class="flex items-center gap-1.5">
                    <User class="w-3.5 h-3.5 shrink-0" />
                    <span class="font-medium text-neutral-600">{{ sermon.speaker }}</span>
                </div>
                <span>{{ sermon.date }}</span>
            </div>
        </div>
    </article>
</template>
