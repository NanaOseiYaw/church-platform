<script setup lang="ts">
import { computed } from 'vue'
import { Mic2, Headphones, ExternalLink } from 'lucide-vue-next'
import type { PublicSermon } from '@/types'

const props = defineProps<{
    sermon: PublicSermon
    /** Controls whether to render a compact inline player or a full hero player */
    size?: 'compact' | 'full'
}>()

const isYouTube = computed(() =>
    props.sermon.embed_url?.includes('youtube.com') ?? false,
)

const isVimeo = computed(() =>
    props.sermon.embed_url?.includes('vimeo.com') ?? false,
)

const hasVideo = computed(() => !!props.sermon.embed_url)
const hasAudio = computed(() => !!props.sermon.audio_url)
</script>

<template>
    <!-- ── Video embed (YouTube / Vimeo / iframe) ──────────────────────────── -->
    <div v-if="hasVideo" class="w-full">
        <div
            class="relative w-full rounded-xl overflow-hidden bg-black shadow-2xl"
            :class="size === 'full' ? 'rounded-2xl' : ''"
            style="padding-top: 56.25%;"
        >
            <iframe
                :src="sermon.embed_url!"
                class="absolute inset-0 w-full h-full"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
                :title="sermon.title"
                loading="lazy"
            />
        </div>

        <!-- Provider label -->
        <div class="flex items-center justify-between mt-2 px-1">
            <span class="text-xs text-neutral-400">
                {{ isYouTube ? 'YouTube' : isVimeo ? 'Vimeo' : 'Video' }}
            </span>
            <a
                v-if="sermon.provider_video_id && isYouTube"
                :href="`https://www.youtube.com/watch?v=${sermon.provider_video_id}`"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 text-xs text-neutral-400 hover:text-brand-600 transition-colors"
            >
                Watch on YouTube <ExternalLink class="w-3 h-3" />
            </a>
        </div>
    </div>

    <!-- ── Audio-only player ────────────────────────────────────────────────── -->
    <div
        v-else-if="hasAudio"
        class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 rounded-2xl p-6"
    >
        <div class="flex items-center gap-4 mb-4">
            <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center shrink-0">
                <Headphones class="w-5 h-5 text-amber-600" />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-neutral-800 truncate">{{ sermon.title }}</p>
                <p v-if="sermon.duration" class="text-xs text-neutral-500">{{ sermon.duration }}</p>
            </div>
        </div>
        <audio
            :src="sermon.audio_url!"
            controls
            class="w-full"
            preload="metadata"
        />
    </div>

    <!-- ── No media placeholder ──────────────────────────────────────────────── -->
    <div
        v-else
        class="w-full aspect-video rounded-2xl bg-gradient-to-br from-brand-50 to-brand-100 flex flex-col items-center justify-center gap-3"
    >
        <div class="w-16 h-16 bg-white/60 rounded-2xl flex items-center justify-center">
            <Mic2 class="w-8 h-8 text-brand-400" />
        </div>
        <p class="text-sm text-brand-400">No video or audio available yet</p>
    </div>
</template>
