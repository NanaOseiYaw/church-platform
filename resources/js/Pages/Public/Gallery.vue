<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import { useChurch } from '@/composables/useChurch'
import { Images } from 'lucide-vue-next'

interface GalleryImage {
    id: number
    title: string | null
    caption: string | null
    url: string
}

defineProps<{
    images: GalleryImage[]
}>()

const { church } = useChurch()
</script>

<template>
    <PublicLayout
        title="Gallery"
        :description="`Photos and memories from ${church?.name ?? 'our church community'}.`"
    >
        <PageHero
            eyebrow="Gallery"
            :title="`Our Church in Photos`"
            :description="`A glimpse into the life and ministry of ${church?.name ?? 'our church'}.`"
        />

        <SectionWrapper class="py-16">
            <!-- Empty state -->
            <div v-if="images.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
                <div class="w-16 h-16 bg-neutral-100 rounded-2xl flex items-center justify-center mb-4">
                    <Images class="w-8 h-8 text-neutral-300" />
                </div>
                <h3 class="text-lg font-semibold text-neutral-700 mb-1">No photos yet</h3>
                <p class="text-neutral-400 text-sm max-w-xs">Check back soon — we'll be sharing moments from our community.</p>
            </div>

            <!-- Masonry-style grid -->
            <div v-else class="columns-1 sm:columns-2 lg:columns-3 xl:columns-4 gap-4 space-y-4">
                <div
                    v-for="image in images"
                    :key="image.id"
                    class="break-inside-avoid rounded-2xl overflow-hidden border border-neutral-100 bg-white shadow-sm hover:shadow-elevated transition-all duration-300 group cursor-pointer"
                >
                    <img
                        :src="image.url"
                        :alt="image.title ?? 'Church gallery photo'"
                        class="w-full object-cover group-hover:scale-105 transition-transform duration-700"
                        loading="lazy"
                    />
                    <div v-if="image.title || image.caption" class="px-4 py-3">
                        <p v-if="image.title"   class="text-sm font-semibold text-neutral-900">{{ image.title }}</p>
                        <p v-if="image.caption" class="text-xs text-neutral-500 mt-0.5 leading-relaxed">{{ image.caption }}</p>
                    </div>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
