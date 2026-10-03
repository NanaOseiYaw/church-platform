<script setup lang="ts">
import { computed } from 'vue'
import FlyerImage from '@/Components/UI/FlyerImage.vue'
import { Link } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import { useChurch } from '@/composables/useChurch'
import type { Event } from '@/types'
import { CalendarDays, Clock, MapPin, ArrowLeft, Tag } from 'lucide-vue-next'

const props = defineProps<{
    event: Event
}>()

const { church } = useChurch()

const pageTitle = computed(() => props.event.title)
const pageDescription = computed(() =>
    props.event.description ?? `${props.event.date} at ${church.value?.name ?? ''}`
)
</script>

<template>
    <PublicLayout :title="pageTitle" :description="pageDescription">

        <!--
            With an image: shown whole, never cropped, and the title sits below it
            rather than over it. A flyer carries its own date and venue, which a
            wide banner crop and an overlaid title would both hide.
        -->
        <div v-if="event.image" class="bg-neutral-950">
            <FlyerImage
                :src="event.image"
                :alt="`Image for ${event.title}`"
                :href="event.image"
                class="w-full h-[min(70vh,640px)]"
            />
            <div class="mx-auto max-w-4xl w-full px-6 pt-8 pb-10">
                <span v-if="event.category" class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.15em] text-white/60 mb-3">
                    <Tag class="w-3 h-3" />
                    {{ event.category }}
                </span>
                <h1 class="text-3xl sm:text-4xl font-bold text-white leading-tight">{{ event.title }}</h1>
            </div>
        </div>

        <!-- Without an image: the original gradient header with the title overlaid. -->
        <div v-else class="relative w-full bg-neutral-950" style="min-height: 320px; max-height: 520px; overflow: hidden;">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-900 via-neutral-900 to-neutral-950">
                <div class="absolute inset-0 opacity-10"
                    style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 32px 32px;">
                </div>
                <CalendarDays class="absolute bottom-10 right-10 w-24 h-24 text-white/5" />
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>

            <!-- Title overlay -->
            <div class="absolute bottom-0 left-0 right-0 px-6 pb-8 pt-20 max-w-4xl mx-auto w-full">
                <span v-if="event.category" class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-[0.15em] text-white/60 mb-3">
                    <Tag class="w-3 h-3" />
                    {{ event.category }}
                </span>
                <h1 class="text-3xl sm:text-4xl font-bold text-white leading-tight">{{ event.title }}</h1>
            </div>
        </div>

        <!-- ── Main content ──────────────────────────────────────────────────────── -->
        <SectionWrapper class="py-12">
            <div class="max-w-4xl mx-auto">

                <!-- Back link -->
                <Link href="/events" class="inline-flex items-center gap-1.5 text-sm text-brand-600 hover:text-brand-700 font-medium mb-8 transition-colors">
                    <ArrowLeft class="w-4 h-4" />
                    All Events
                </Link>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- ── LEFT: description ──────────────────────────────── -->
                    <div class="lg:col-span-2">
                        <p v-if="event.description" class="text-neutral-700 text-base leading-relaxed whitespace-pre-wrap">{{ event.description }}</p>
                        <p v-else class="text-neutral-400 italic">No additional details provided.</p>
                    </div>

                    <!-- ── RIGHT: event details card ─────────────────────── -->
                    <aside class="space-y-4">
                        <div class="bg-white border border-neutral-100 rounded-2xl p-5 shadow-sm">
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-4">Event Details</h2>

                            <!-- Date -->
                            <div class="flex items-start gap-3 mb-3">
                                <span class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center shrink-0">
                                    <CalendarDays class="w-4 h-4 text-brand-600" />
                                </span>
                                <div>
                                    <p class="text-xs text-neutral-400 font-medium">Date</p>
                                    <p class="text-sm font-semibold text-neutral-900">{{ event.date_range || event.date }}</p>
                                </div>
                            </div>

                            <!-- Time -->
                            <div class="flex items-start gap-3 mb-3">
                                <span class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center shrink-0">
                                    <Clock class="w-4 h-4 text-brand-600" />
                                </span>
                                <div>
                                    <p class="text-xs text-neutral-400 font-medium">Time</p>
                                    <p class="text-sm font-semibold text-neutral-900">{{ event.time_range || event.time }}</p>
                                </div>
                            </div>

                            <!-- Location -->
                            <div v-if="event.location" class="flex items-start gap-3">
                                <span class="w-8 h-8 bg-brand-50 rounded-lg flex items-center justify-center shrink-0">
                                    <MapPin class="w-4 h-4 text-brand-600" />
                                </span>
                                <div>
                                    <p class="text-xs text-neutral-400 font-medium">Location</p>
                                    <p class="text-sm font-semibold text-neutral-900">{{ event.location }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Add to calendar link -->
                        <a
                            v-if="event.start_at"
                            :href="`https://calendar.google.com/calendar/r/eventedit?text=${encodeURIComponent(event.title)}&dates=${event.start_at.replace(/[-:]/g,'').replace(/\.\d+/,'')}&details=${encodeURIComponent(event.description ?? '')}&location=${encodeURIComponent(event.location ?? '')}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold transition-colors"
                        >
                            <CalendarDays class="w-4 h-4" />
                            Add to Google Calendar
                        </a>
                    </aside>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
