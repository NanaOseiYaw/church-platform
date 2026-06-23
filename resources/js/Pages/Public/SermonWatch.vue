<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import SermonPlayer from '@/Components/Sermons/SermonPlayer.vue'
import SermonCard from '@/Components/Sermons/SermonCard.vue'
import { ArrowLeft, Clock, Calendar, BookOpen, Share2 } from 'lucide-vue-next'
import { onMounted, onUnmounted } from 'vue'
import type { PublicSermon } from '@/types'

const props = defineProps<{
    sermon:  PublicSermon
    related: PublicSermon[]
}>()

function share() {
    if (navigator.share) {
        navigator.share({
            title: props.sermon.title,
            text:  props.sermon.description ?? undefined,
            url:   window.location.href,
        })
    } else {
        navigator.clipboard.writeText(window.location.href)
    }
}

// ── Sermon JSON-LD (VideoObject / AudioObject) ─────────────────────────────────
// Injected directly into <head> so Google can index sermon as a rich video result.
let ldScript: HTMLScriptElement | null = null

onMounted(() => {
    const s = props.sermon
    // Build the schema — use VideoObject when embed URL exists, AudioObject otherwise
    const type = s.embed_url ? 'VideoObject' : 'AudioObject'
    const schema: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type':    type,
        'name':     s.title,
        'url':      window.location.href,
    }
    if (s.description)    schema['description']    = s.description
    if (s.thumbnail)      schema['thumbnailUrl']   = s.thumbnail
    if (s.preached_at)    schema['uploadDate']      = s.preached_at
    if (s.embed_url)      schema['embedUrl']        = s.embed_url
    if (s.audio_url)      schema['contentUrl']      = s.audio_url
    if (s.duration_seconds) {
        const h = Math.floor(s.duration_seconds / 3600)
        const m = Math.floor((s.duration_seconds % 3600) / 60)
        const sec = s.duration_seconds % 60
        schema['duration'] = `PT${h ? h + 'H' : ''}${m ? m + 'M' : ''}${sec ? sec + 'S' : ''}`
    }
    if (s.speaker) schema['author'] = { '@type': 'Person', 'name': s.speaker }

    ldScript = document.createElement('script')
    ldScript.type = 'application/ld+json'
    ldScript.text = JSON.stringify(schema)
    document.head.appendChild(ldScript)
})

onUnmounted(() => {
    if (ldScript && document.head.contains(ldScript)) {
        document.head.removeChild(ldScript)
    }
    ldScript = null
})
</script>

<template>
    <PublicLayout
        :title="sermon.title"
        :description="sermon.description ?? ''"
        :og-image="sermon.thumbnail ?? null"
    >

        <!-- ── Breadcrumb bar ────────────────────────────────────────────────── -->
        <div class="bg-neutral-950 border-b border-white/5">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-4 flex items-center gap-3 text-sm">
                <a href="/sermons" class="flex items-center gap-1.5 text-neutral-400 hover:text-white transition-colors">
                    <ArrowLeft class="w-3.5 h-3.5" />
                    Sermons
                </a>
                <span class="text-neutral-700">/</span>
                <span class="text-neutral-300 truncate">{{ sermon.title }}</span>
            </div>
        </div>

        <!-- ── Player hero ───────────────────────────────────────────────────── -->
        <div class="bg-neutral-950 pb-0">
            <div class="mx-auto max-w-5xl px-6 lg:px-8 pt-8 pb-0">
                <SermonPlayer :sermon="sermon" size="full" />
            </div>
        </div>

        <!-- ── Content: title + meta + description ───────────────────────────── -->
        <SectionWrapper bg="white" class="pt-8">
            <div class="max-w-5xl mx-auto">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- ── Left: title + description ──────────────────────────── -->
                    <div class="lg:col-span-2">

                        <!-- Series pill -->
                        <p
                            v-if="sermon.series"
                            class="text-xs font-bold uppercase tracking-widest text-brand-500 mb-2"
                        >
                            {{ sermon.series }}
                        </p>

                        <!-- Title -->
                        <h1 class="text-2xl lg:text-3xl font-bold text-neutral-900 leading-tight mb-4">
                            {{ sermon.title }}
                        </h1>

                        <!-- Meta row -->
                        <div class="flex items-center gap-4 text-sm text-neutral-500 mb-6 flex-wrap">
                            <span v-if="sermon.speaker" class="font-semibold text-neutral-700">
                                {{ sermon.speaker }}
                            </span>
                            <span v-if="sermon.preached_at_formatted" class="flex items-center gap-1.5">
                                <Calendar class="w-3.5 h-3.5 text-neutral-400" />
                                {{ sermon.preached_at_formatted }}
                            </span>
                            <span v-if="sermon.duration" class="flex items-center gap-1.5">
                                <Clock class="w-3.5 h-3.5 text-neutral-400" />
                                {{ sermon.duration }}
                            </span>
                            <button
                                type="button"
                                class="ml-auto inline-flex items-center gap-1.5 text-xs font-medium text-neutral-400 hover:text-brand-600 transition-colors"
                                @click="share"
                            >
                                <Share2 class="w-3.5 h-3.5" /> Share
                            </button>
                        </div>

                        <!-- Description -->
                        <div
                            v-if="sermon.description"
                            class="bg-neutral-50 rounded-2xl p-5 lg:p-6"
                        >
                            <h2 class="text-xs font-bold uppercase tracking-widest text-neutral-400 mb-3 flex items-center gap-2">
                                <BookOpen class="w-3.5 h-3.5" /> About this sermon
                            </h2>
                            <p class="text-sm text-neutral-600 leading-relaxed whitespace-pre-line">
                                {{ sermon.description }}
                            </p>
                        </div>
                    </div>

                    <!-- ── Right: related sermons ──────────────────────────────── -->
                    <div v-if="related.length" class="lg:col-span-1">
                        <h2 class="text-xs font-bold uppercase tracking-widest text-neutral-400 mb-4">
                            {{ sermon.series ? 'More from this series' : 'More sermons' }}
                        </h2>

                        <div class="space-y-3">
                            <SermonCard
                                v-for="r in related"
                                :key="r.id"
                                :sermon="r"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
