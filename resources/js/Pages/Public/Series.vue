<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import { Link } from '@inertiajs/vue3'
import { useChurch } from '@/composables/useChurch'
import { BookOpen } from 'lucide-vue-next'

const { church } = useChurch()

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    seriesList: Array<{
        id:           number
        title:        string
        description:  string | null
        started_at:   string | null
        ended_at:     string | null
        sermon_count: number
    }>
}>()

// ── Helpers ────────────────────────────────────────────────────────────────────

function dateRange(item: { started_at: string | null; ended_at: string | null }): string {
    if (!item.started_at) return 'Ongoing'
    if (!item.ended_at)   return `${item.started_at} – Ongoing`
    return `${item.started_at} – ${item.ended_at}`
}
</script>

<template>
    <PublicLayout title="Sermon Series" :description="`Browse sermon series and teaching collections at ${church.name}.`">

        <!-- ── Dark hero header ──────────────────────────────────────────────── -->
        <div class="bg-neutral-950 text-white">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 pt-20 pb-12">
                <p class="text-xs font-bold tracking-[0.2em] uppercase text-brand-400 mb-3">Teaching Series</p>
                <h1 class="text-4xl md:text-5xl font-display text-white leading-tight mb-4">
                    Sermon Series
                </h1>
                <p class="text-lg text-neutral-400 max-w-2xl">
                    Explore in-depth teaching series from our church.
                </p>
            </div>
        </div>

        <!-- ── Series grid ───────────────────────────────────────────────────── -->
        <SectionWrapper bg="white">

            <div v-if="seriesList.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link
                    v-for="series in seriesList"
                    :key="series.id"
                    :href="`/series/${series.id}`"
                    class="group block bg-white border border-neutral-200 rounded-2xl p-6 hover:border-neutral-300 hover:shadow-md transition-all duration-200"
                >
                    <!-- Icon -->
                    <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center mb-4 group-hover:bg-brand-100 transition-colors">
                        <BookOpen class="w-5 h-5 text-brand-600" />
                    </div>

                    <!-- Title -->
                    <h2 class="text-base font-semibold text-neutral-900 group-hover:text-brand-700 transition-colors leading-snug mb-2">
                        {{ series.title }}
                    </h2>

                    <!-- Description -->
                    <p
                        v-if="series.description"
                        class="text-sm text-neutral-500 leading-relaxed line-clamp-2 mb-4"
                    >
                        {{ series.description }}
                    </p>
                    <div v-else class="mb-4" />

                    <!-- Footer -->
                    <div class="flex items-center justify-between text-xs text-neutral-400 pt-4 border-t border-neutral-100">
                        <span>{{ dateRange(series) }}</span>
                        <span class="font-medium text-neutral-600">
                            {{ series.sermon_count }} message{{ series.sermon_count !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-20">
                <BookOpen class="w-12 h-12 text-neutral-200 mx-auto mb-4" />
                <p class="text-lg font-medium text-neutral-400">No series available yet</p>
                <p class="text-sm text-neutral-300 mt-1">Check back soon for new teaching series.</p>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
