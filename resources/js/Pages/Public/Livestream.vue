<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useChurch } from '@/composables/useChurch'
import { Play, Clock, Radio } from 'lucide-vue-next'

interface PastStream {
    id: number
    title: string
    date: string
    thumbnail: string | null
    duration: string
}

interface NextService {
    title: string
    date: string
    time: string
}

const { church } = useChurch()

const props = defineProps<{
    isLive: boolean
    streamUrl: string | null
    chatEnabled: boolean
    nextService: NextService
    pastStreams: PastStream[]
}>()
</script>

<template>
    <PublicLayout title="Watch Live" :description="`Watch ${church.name} live online.`">

        <!-- Live hero — full dark treatment -->
        <section class="gradient-dark-mesh relative overflow-hidden min-h-[85vh] flex flex-col">
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/30 to-transparent" aria-hidden="true"></div>
            <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
                <div class="absolute inset-0" style="background: radial-gradient(ellipse 60% 50% at 50% 30%, rgba(30,90,168,0.12) 0%, transparent 70%);"></div>
            </div>

            <div class="relative mx-auto max-w-5xl w-full px-6 lg:px-8 flex-1 flex flex-col justify-center py-20">

                <!-- Live / Not Live badge -->
                <div class="flex justify-center mb-8">
                    <div
                        v-if="isLive"
                        class="inline-flex items-center gap-2.5 bg-rose-600 text-white text-sm font-bold px-5 py-2.5 rounded-full"
                    >
                        <Radio class="w-4 h-4" />
                        LIVE NOW
                    </div>
                    <div v-else class="inline-flex items-center gap-2 bg-white/8 border border-white/15 rounded-full px-4 py-2 text-sm text-white/60">
                        <Clock class="w-4 h-4" />
                        Not currently live
                    </div>
                </div>

                <!-- Video frame -->
                <div class="aspect-video bg-neutral-900 border border-white/10 rounded-2xl overflow-hidden flex items-center justify-center shadow-overlay">
                    <iframe
                        v-if="isLive && streamUrl"
                        :src="streamUrl"
                        allow="autoplay; fullscreen"
                        class="w-full h-full"
                        frameborder="0"
                    ></iframe>
                    <div v-else class="text-center px-6">
                        <div class="w-20 h-20 rounded-full bg-white/8 border border-white/12 flex items-center justify-center mx-auto mb-6 cursor-pointer hover:bg-white/15 hover:scale-105 transition-all duration-300">
                            <Play class="w-9 h-9 text-white/50 ml-1" />
                        </div>
                        <p class="text-white/50 text-base mb-1">Stream is not currently live</p>
                        <p class="text-white/25 text-sm">Join us online this Sunday!</p>
                    </div>
                </div>

                <!-- Next service info -->
                <div v-if="!isLive" class="mt-8 text-center">
                    <p class="text-white/35 text-xs uppercase tracking-widest mb-2">Next service</p>
                    <h2 class="text-white font-semibold text-xl mb-1">{{ nextService.title }}</h2>
                    <p class="text-white/40 text-sm">{{ nextService.date }} at {{ nextService.time }}</p>
                </div>
            </div>

        </section>

        <!-- Past streams -->
        <SectionWrapper bg="white" v-if="pastStreams.length">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-2xl font-display text-neutral-900">Recent Services</h2>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <article
                    v-for="(stream, i) in pastStreams"
                    :key="stream.id"
                    class="group bg-white border border-neutral-100 rounded-2xl overflow-hidden hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 reveal"
                    :class="`reveal-delay-${i + 1}`"
                >
                    <div class="relative aspect-video bg-neutral-900 overflow-hidden">
                        <img v-if="stream.thumbnail" :src="stream.thumbnail" :alt="stream.title"
                            class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700" />
                        <div v-else class="absolute inset-0 bg-gradient-to-br from-neutral-800 to-neutral-950 flex items-center justify-center">
                            <Play class="w-8 h-8 text-white/20 group-hover:text-white/40 transition-colors" />
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                        <div class="absolute bottom-2.5 right-2.5">
                            <span class="text-[10px] font-medium bg-black/60 text-white px-2.5 py-1 rounded-full backdrop-blur-sm">{{ stream.duration }}</span>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-sm text-neutral-900 group-hover:text-brand-600 transition-colors mb-1">{{ stream.title }}</h3>
                        <p class="text-xs text-neutral-400">{{ stream.date }}</p>
                    </div>
                </article>
            </div>
        </SectionWrapper>

        <!-- Subscribe CTA -->
        <SectionWrapper bg="surface" centered size="sm">
            <div class="flex items-center justify-center gap-3 mb-4">
                <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-500">Stay Connected</span>
            </div>
            <h2 class="text-3xl font-display text-neutral-900 mb-4">Never miss a service</h2>
            <p class="text-neutral-500 mb-7 max-w-sm mx-auto">Explore our archive of past messages and sermon series.</p>
            <AppButton href="/sermons" variant="primary">Browse Sermon Archive</AppButton>
        </SectionWrapper>
    </PublicLayout>
</template>
