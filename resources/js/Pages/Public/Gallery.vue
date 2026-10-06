<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import InstagramGallery from '@/Components/Public/InstagramGallery.vue'
import { useChurch } from '@/composables/useChurch'
import { Images, Instagram } from 'lucide-vue-next'
import type { InstagramPost } from '@/types'

interface GalleryImage {
    id: number
    title: string | null
    caption: string | null
    url: string
}

defineProps<{
    images:         GalleryImage[]
    /** Newest posts from the church's Instagram — cached metadata, media stays on Meta's CDN. */
    instagramPosts: InstagramPost[]
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

        <!--
            Instagram first: it updates itself, so it is the freshest thing on
            the page. Hidden entirely when there is nothing to show.
        -->
        <SectionWrapper v-if="instagramPosts.length > 0" class="py-16">
            <div class="flex flex-wrap items-end justify-between gap-4 mb-8">
                <div>
                    <p class="text-sm font-semibold tracking-widest uppercase text-brand-500 mb-1">Instagram</p>
                    <h2 class="text-2xl md:text-3xl font-display text-neutral-900">Latest from Instagram</h2>
                </div>
                <AppButton v-if="church?.socials?.instagram" :href="church.socials.instagram" external variant="outline" size="sm">
                    <Instagram class="w-3.5 h-3.5" aria-hidden="true" /> Follow on Instagram
                </AppButton>
            </div>
            <InstagramGallery :posts="instagramPosts" />
        </SectionWrapper>

        <SectionWrapper v-if="images.length > 0 || instagramPosts.length === 0" class="py-16">
            <h2 v-if="instagramPosts.length > 0 && images.length > 0" class="text-2xl md:text-3xl font-display text-neutral-900 mb-8">
                Photo gallery
            </h2>

            <!-- Empty state: only when there is neither Instagram nor uploaded photos -->
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
