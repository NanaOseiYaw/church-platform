<script setup lang="ts">
import { ref, computed } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import FeaturedSermonHero from '@/Components/Sermons/FeaturedSermonHero.vue'
import SermonGrid from '@/Components/Sermons/SermonGrid.vue'
import type { PublicSermon } from '@/types'
import { useChurch } from '@/composables/useChurch'

const { church } = useChurch()

const props = defineProps<{
    featured:        PublicSermon | null
    recent:          PublicSermon[]
    series:          Array<{ id: number | null; title: string; slug: string | null; sermon_count?: number }>
    sermonsSubtitle: string | null
}>()

const activeSeries = ref<string | null>(null)

const filtered = computed(() => {
    if (! activeSeries.value) return props.recent
    return props.recent.filter(s =>
        s.series === activeSeries.value ||
        (s.sermon_series && s.sermon_series.title === activeSeries.value)
    )
})

const uniqueSeries = computed(() => {
    const titles = new Set(props.series.map(s => s.title))
    return [...titles]
})
</script>

<template>
    <PublicLayout title="Sermons" :description="`Browse our library of sermons and teaching series at ${church.name}.`">

        <PageHero
            title="Sermons & Series"
            :subtitle="sermonsSubtitle ?? 'Deep, scripture-rooted teaching to strengthen your faith and equip you for daily life.'"
        />

        <!-- Featured sermon — dark continuation -->
        <div v-if="featured" class="gradient-dark-mesh relative">
            <div class="mx-auto max-w-7xl px-6 lg:px-8 pb-12">
                <FeaturedSermonHero :sermon="featured" />
            </div>
            <div class="h-24 bg-gradient-to-t from-white to-transparent"></div>
        </div>

        <!-- Sermon library -->
        <SectionWrapper bg="white">
            <!-- Series filter -->
            <div v-if="uniqueSeries.length" class="flex flex-wrap gap-2 mb-10">
                <button
                    type="button"
                    class="px-4 py-2 text-sm font-medium rounded-full border transition-all duration-150"
                    :class="activeSeries === null
                        ? 'bg-neutral-900 text-white border-neutral-900'
                        : 'bg-white text-neutral-600 border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'"
                    @click="activeSeries = null"
                >
                    All sermons
                </button>
                <button
                    v-for="s in uniqueSeries"
                    :key="s"
                    type="button"
                    class="px-4 py-2 text-sm font-medium rounded-full border transition-all duration-150"
                    :class="activeSeries === s
                        ? 'bg-neutral-900 text-white border-neutral-900'
                        : 'bg-white text-neutral-600 border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'"
                    @click="activeSeries = s"
                >
                    {{ s }}
                </button>
            </div>

            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-neutral-900">
                    {{ activeSeries ?? 'Recent sermons' }}
                </h2>
                <p v-if="filtered.length" class="text-sm text-neutral-400">
                    {{ filtered.length }} sermon{{ filtered.length !== 1 ? 's' : '' }}
                </p>
            </div>

            <SermonGrid
                :sermons="filtered"
                empty-title="No sermons in this series yet"
                empty-message="Check back soon for new content."
            />
        </SectionWrapper>
    </PublicLayout>
</template>
