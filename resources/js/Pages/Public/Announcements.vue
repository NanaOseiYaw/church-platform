<script setup lang="ts">
import { ref, computed } from 'vue'
import FlyerImage from '@/Components/UI/FlyerImage.vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import AppBadge from '@/Components/UI/AppBadge.vue'
import type { Announcement } from '@/types'
import { useChurch } from '@/composables/useChurch'

const props = defineProps<{
    announcements: Announcement[]
    categories: string[]
}>()

const { church } = useChurch()

const activeCategory = ref('All')
const filtered = computed(() =>
    activeCategory.value === 'All'
        ? props.announcements
        : props.announcements.filter(a => a.category === activeCategory.value)
)

const categoryColors: Record<string, any> = {
    Community: 'brand', Outreach: 'emerald', Church: 'blue', Service: 'amber',
}
</script>

<template>
    <PublicLayout title="Announcements" :description="`Stay up to date with what's happening at ${church.name}.`">

        <PageHero
            eyebrow="Church News"
            title="Announcements"
            subtitle="Stay informed about everything happening in our community."
        />

        <SectionWrapper bg="white">
            <!-- Category filter -->
            <div class="flex flex-wrap gap-2 mb-10">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    class="px-4 py-2 text-sm font-medium rounded-full border transition-all duration-150"
                    :class="activeCategory === cat
                        ? 'bg-neutral-900 text-white border-neutral-900'
                        : 'bg-white text-neutral-600 border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'"
                    @click="activeCategory = cat"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- Announcement list -->
            <div class="space-y-4">
                <article
                    v-for="(ann, i) in filtered"
                    :key="ann.id"
                    class="group bg-white border border-neutral-100 rounded-2xl p-6 md:p-7 hover:shadow-elevated hover:-translate-y-0.5 transition-all duration-300 reveal"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <div :class="ann.image ? 'flex flex-col sm:flex-row gap-5' : ''">
                    <!--
                        The frame takes the image's own shape once loaded, from a 4:5
                        portrait flyer to a 16:10 landscape photo, so either fills it;
                        a fixed portrait frame shrank landscape photos to under half
                        its area. self-start stops the row stretching it out of shape.
                        Opens full size to read a flyer's small print.
                    -->
                    <FlyerImage
                        v-if="ann.image"
                        :src="ann.image"
                        :alt="`Image for ${ann.title}`"
                        :href="ann.image"
                        :adapt-ratio="[0.8, 1.6]"
                        class="w-full sm:w-52 shrink-0 self-start aspect-[4/3] rounded-xl"
                    />
                    <div class="min-w-0 flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <AppBadge :color="categoryColors[ann.category ?? ''] ?? 'neutral'">{{ ann.category }}</AppBadge>
                            <span
                                v-if="ann.priority === 'high'"
                                class="text-xs font-semibold text-rose-500 bg-rose-50 border border-rose-100 px-2.5 py-0.5 rounded-full"
                            >
                                Important
                            </span>
                        </div>
                        <span class="text-xs text-neutral-400 shrink-0">{{ ann.date }}</span>
                    </div>
                    <h2 class="text-base font-semibold text-neutral-900 mb-2 group-hover:text-brand-600 transition-colors duration-200">
                        {{ ann.title }}
                    </h2>
                    <p v-if="ann.excerpt" class="text-sm text-neutral-500 leading-relaxed">
                        {{ ann.excerpt }}
                    </p>
                    </div>
                    </div>
                </article>

                <!-- Empty state -->
                <div v-if="!filtered.length" class="text-center py-20 text-neutral-400">
                    <p class="font-semibold mb-1">No announcements</p>
                    <p class="text-sm">Check back soon for updates.</p>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
