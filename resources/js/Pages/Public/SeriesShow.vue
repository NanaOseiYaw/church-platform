<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, Play, Mic2 } from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    series: {
        id:          number
        title:       string
        description: string | null
        cover_image: string | null
        started_at:  string | null
        ended_at:    string | null
    }
    sermons: Array<{
        id:          number
        title:       string
        slug:        string
        speaker:     string | null
        preached_at: string | null
        thumbnail:   string | null
        duration:    string | null
        video_url:   string | null
    }>
}>()

// ── Helpers ────────────────────────────────────────────────────────────────────

function dateRange(item: { started_at: string | null; ended_at: string | null }): string {
    if (!item.started_at) return ''
    if (!item.ended_at)   return `${item.started_at} – Ongoing`
    return `${item.started_at} – ${item.ended_at}`
}
</script>

<template>
    <PublicLayout
        :title="series.title"
        :description="series.description ?? undefined"
        :og-image="series.cover_image ?? null"
    >

        <!-- ── Dark hero header ──────────────────────────────────────────────── -->
        <div class="bg-neutral-950 text-white">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 pt-20 pb-12">

                <!-- Back link -->
                <Link href="/series" class="inline-flex items-center gap-1.5 text-sm text-neutral-400 hover:text-white mb-6 transition-colors">
                    <ArrowLeft class="w-4 h-4" />
                    All series
                </Link>

                <h1 class="text-4xl md:text-5xl font-display text-white leading-tight mb-4">
                    {{ series.title }}
                </h1>
                <p v-if="series.description" class="text-lg text-neutral-400 max-w-2xl mb-3">
                    {{ series.description }}
                </p>
                <p v-if="dateRange(series)" class="text-sm text-neutral-500">
                    {{ dateRange(series) }} &middot; {{ sermons.length }} message{{ sermons.length !== 1 ? 's' : '' }}
                </p>
            </div>
        </div>

        <!-- ── Sermons grid ──────────────────────────────────────────────────── -->
        <SectionWrapper bg="white">

            <div v-if="sermons.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link
                    v-for="sermon in sermons"
                    :key="sermon.id"
                    :href="`/sermons/${sermon.slug}`"
                    class="group block bg-white border border-neutral-200 rounded-2xl overflow-hidden hover:border-neutral-300 hover:shadow-md transition-all duration-200"
                >
                    <!-- Thumbnail -->
                    <div class="relative aspect-video bg-neutral-100 overflow-hidden">
                        <img
                            v-if="sermon.thumbnail"
                            :src="sermon.thumbnail"
                            :alt="sermon.title"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center bg-neutral-900">
                            <Mic2 class="w-8 h-8 text-neutral-600" />
                        </div>
                        <!-- Play overlay -->
                        <div v-if="sermon.video_url"
                             class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                            <div class="w-12 h-12 rounded-full bg-white/90 flex items-center justify-center shadow-lg">
                                <Play class="w-5 h-5 text-neutral-900 ml-0.5" />
                            </div>
                        </div>
                        <!-- Duration badge -->
                        <span
                            v-if="sermon.duration"
                            class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded bg-black/70 text-white text-[10px] font-medium"
                        >
                            {{ sermon.duration }}
                        </span>
                    </div>

                    <!-- Card body -->
                    <div class="p-4">
                        <h3 class="text-sm font-semibold text-neutral-900 group-hover:text-brand-700 transition-colors leading-snug mb-1 line-clamp-2">
                            {{ sermon.title }}
                        </h3>
                        <p v-if="sermon.speaker" class="text-xs text-neutral-500 mb-2">
                            {{ sermon.speaker }}
                        </p>
                        <p class="text-xs text-neutral-400">{{ sermon.preached_at }}</p>
                    </div>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-20">
                <Mic2 class="w-12 h-12 text-neutral-200 mx-auto mb-4" />
                <p class="text-lg font-medium text-neutral-400">No sermons in this series yet</p>
                <p class="text-sm text-neutral-300 mt-1">Check back soon for new content.</p>
                <Link href="/series" class="inline-flex items-center gap-1.5 text-sm text-brand-600 hover:text-brand-700 mt-4 transition-colors">
                    <ArrowLeft class="w-4 h-4" />
                    Back to all series
                </Link>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
