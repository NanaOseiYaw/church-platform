<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import SectionHeader from '@/Components/UI/SectionHeader.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import EventCard from '@/Components/Cards/EventCard.vue'
import SermonCard from '@/Components/Cards/SermonCard.vue'
import AnnouncementCard from '@/Components/Cards/AnnouncementCard.vue'
import { useChurch } from '@/composables/useChurch'
import type { Event, Sermon, Announcement, Stat, ServiceTime, Testimonial, MinistryHighlight } from '@/types'
import { ArrowRight, Play, Calendar } from 'lucide-vue-next'
import { computed } from 'vue'

const props = defineProps<{
    serviceTimes:        ServiceTime[]
    featuredEvents:      Event[]
    latestSermons:       Sermon[]
    announcements:       Announcement[]
    stats:               Stat[]
    testimonials:        Testimonial[]
    ministryHighlights:  MinistryHighlight[]
    heroDescription:     string | null
    heroImage:           string | null
    hasLivestream:       boolean
    eventsSubtitle:      string | null
    ministryHeading:     string | null
    ministryBody:        string | null
    sermonsSubtitle:     string | null
    testimonialsSubtitle: string | null
    livestreamCta:       string | null
    sectionVisibility: {
        events:        boolean
        ministry:      boolean
        sermons:       boolean
        testimonials:  boolean
        announcements: boolean
        livestream:    boolean
    }
}>()

const { church } = useChurch()

// Split tagline into "all-but-last-word" + "last-word." for the editorial headline.
// e.g. "A Place to Belong" → { start: "A Place to", last: "Belong." }
const heroHeadline = computed(() => {
    const words = (church.value.tagline || '').trim().split(/\s+/)
    if (words.length <= 1) return { start: '', last: words[0] ? words[0] + '.' : '' }
    return {
        start: words.slice(0, -1).join(' '),
        last:  words[words.length - 1] + '.',
    }
})

// Derive the main service day from service_times (first 'main' type, else first entry).
const mainServiceDay = computed(() => {
    if (!props.serviceTimes.length) return 'Sunday'
    const main = props.serviceTimes.find(s => (s as any).type === 'main') ?? props.serviceTimes[0]
    return (main as any).day ?? 'Sunday'
})
</script>

<template>
    <PublicLayout
        title="Home"
        :description="`${church.name} — ${church.tagline}.`"
    >
        <!-- ── Hero ──────────────────────────────────────────────────────────── -->
        <section
            class="relative min-h-screen flex flex-col overflow-hidden"
            :class="props.heroImage ? '' : 'gradient-dark-mesh'"
            :style="props.heroImage ? { backgroundImage: `url(${props.heroImage})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}"
        >
            <!-- Top accent line -->
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/40 to-transparent" aria-hidden="true"></div>
            <!-- Dark overlay for legibility when hero image is set -->
            <div v-if="props.heroImage" class="absolute inset-0 bg-black/55 pointer-events-none" aria-hidden="true"></div>

            <!--
                z-10 keeps this above the bottom fade-to-white. Without it the fade
                is later in the DOM at the same stacking level and paints over the
                content, washing out the service times that sit lowest in the hero.
            -->
            <div class="relative z-10 mx-auto w-full max-w-7xl px-6 lg:px-8 flex-1 flex flex-col justify-center pt-28 pb-12">

                <!-- Eyebrow + live chip row -->
                <div class="flex flex-wrap items-center gap-4 md:gap-8 mb-8 reveal">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-400 whitespace-nowrap">
                            {{ church.name }}
                        </span>
                    </div>
                    <div v-if="props.hasLivestream && sectionVisibility.livestream" class="inline-flex items-center gap-2 bg-white/5 border border-white/10 rounded-full px-3.5 py-1.5 shrink-0">
                        <span class="w-1.5 h-1.5 bg-rose-500 rounded-full animate-pulse shrink-0"></span>
                        <span class="text-xs text-white/50 whitespace-nowrap">Live every {{ mainServiceDay }}</span>
                    </div>
                </div>

                <!-- Massive editorial headline — driven by church.tagline -->
                <h1 class="font-display leading-[0.95] tracking-tight mb-8 reveal reveal-delay-1"
                    style="font-size: clamp(3rem, 10vw, 8.5rem);">
                    <span v-if="heroHeadline.start" class="block text-white">{{ heroHeadline.start }}</span>
                    <span class="block gradient-text-light">{{ heroHeadline.last }}</span>
                </h1>

                <!-- Subhead + CTA side-by-side on desktop -->
                <div class="grid md:grid-cols-[1fr_auto] gap-6 md:gap-12 items-end mb-10 reveal reveal-delay-2">
                    <p class="text-base sm:text-lg text-white/45 leading-relaxed max-w-lg">
                        {{ heroDescription ?? church.description ?? 'A community united in faith, purpose, and love — welcoming all who seek to know God.' }}
                    </p>
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <AppButton href="/about" size="lg" variant="primary">
                            I'm New Here
                            <ArrowRight class="w-4 h-4" />
                        </AppButton>
                        <AppButton
                            v-if="props.hasLivestream && sectionVisibility.livestream"
                            href="/live"
                            size="lg"
                            variant="outline"
                            class="!border-white/20 !text-white/70 hover:!text-white hover:!bg-white/10 hover:!border-white/30"
                        >
                            <Play class="w-4 h-4" />
                            Watch Service
                        </AppButton>
                    </div>
                </div>

                <!-- Bottom divider row: stats + service times -->
                <div class="flex flex-wrap items-center justify-between gap-y-5 gap-x-8 pt-8 border-t border-white/8 reveal reveal-delay-3">
                    <!-- Stats — shown only when configured in Settings → Homepage -->
                    <div v-if="stats.length > 0" class="flex flex-wrap gap-x-8 gap-y-3">
                        <div v-for="stat in stats" :key="stat.label" class="flex items-baseline gap-2">
                            <span class="font-display text-xl text-white">{{ stat.value }}</span>
                            <span class="text-xs text-white/30">{{ stat.label }}</span>
                        </div>
                    </div>
                    <!--
                        Service times. This is practical information a visitor is
                        actively looking for, so it is sized and weighted to be read
                        rather than treated as decoration. Contrast is set for the
                        worst case — a light hero photo behind the bottom fade.
                    -->
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2.5">
                        <div
                            v-for="service in serviceTimes"
                            :key="service.day"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Calendar class="w-4 h-4 text-brand-300 shrink-0" aria-hidden="true" />
                            <span class="font-semibold text-white">{{ service.day }}</span>
                            <span class="whitespace-nowrap text-white/80">{{ service.times.join(' · ') }}</span>
                        </div>
                        <AppButton href="/contact" variant="ghost" size="xs"
                            class="!text-white/85 hover:!text-white hover:!bg-white/15 !px-2 font-medium">
                            Directions →
                        </AppButton>
                    </div>
                </div>
            </div>

            <!-- Fade to next section -->
            <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-white to-transparent pointer-events-none" aria-hidden="true"></div>
        </section>

        <!-- ── Featured Events ────────────────────────────────────────────────── -->
        <SectionWrapper v-if="featuredEvents.length > 0 && sectionVisibility.events" bg="white">
            <SectionHeader
                eyebrow="What's On"
                title="Upcoming Events"
                :subtitle="eventsSubtitle ?? 'Join us for worship, community, and service. There is always something happening.'"
            />
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                <EventCard
                    v-for="(event, i) in featuredEvents"
                    :key="event.id"
                    :event="event"
                    class="reveal"
                    :class="`reveal-delay-${i + 1}`"
                />
            </div>
            <div class="text-center">
                <AppButton href="/events" variant="outline">
                    View All Events
                    <ArrowRight class="w-4 h-4" />
                </AppButton>
            </div>
        </SectionWrapper>

        <!-- ── Ministry Highlights ────────────────────────────────────────────── -->
        <section v-if="ministryHighlights.length > 0 && sectionVisibility.ministry" class="gradient-dark-mesh py-24 md:py-32 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/20 to-transparent" aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    <div class="reveal">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-400">Community</span>
                        </div>
                        <h2 class="text-4xl md:text-5xl font-display text-white leading-tight mb-6">
                            {{ ministryHeading ?? 'Find your place in our community.' }}
                        </h2>
                        <p class="text-white/45 text-lg leading-relaxed mb-8">
                            {{ ministryBody ?? "From worship to outreach, from youth to women's ministry — there is a place for every person in the family of God." }}
                        </p>
                        <AppButton href="/ministries" variant="primary">
                            Explore Ministries
                            <ArrowRight class="w-4 h-4" />
                        </AppButton>
                    </div>

                    <div class="grid grid-cols-2 gap-3 reveal reveal-delay-2">
                        <div
                            v-for="ministry in ministryHighlights"
                            :key="ministry.name"
                            class="group rounded-2xl p-6 border border-white/8 bg-white/4 hover:bg-white/8 hover:-translate-y-1 transition-all duration-300 cursor-pointer"
                        >
                            <p class="text-white font-semibold mb-2">{{ ministry.name }}</p>
                            <p class="text-white/35 text-sm leading-relaxed">{{ ministry.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-24 bg-gradient-to-t from-neutral-50 to-transparent pointer-events-none" aria-hidden="true"></div>
        </section>

        <!-- ── Latest Sermons ──────────────────────────────────────────────────── -->
        <SectionWrapper v-if="latestSermons.length > 0 && sectionVisibility.sermons" bg="surface">
            <SectionHeader
                eyebrow="Messages"
                title="Recent Sermons"
                :subtitle="sermonsSubtitle ?? 'Grow in faith with teaching that is rooted in scripture and relevant to your life.'"
            />
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                <SermonCard
                    v-for="(sermon, i) in latestSermons"
                    :key="sermon.id"
                    :sermon="sermon"
                    class="reveal"
                    :class="`reveal-delay-${i + 1}`"
                />
            </div>
            <div class="text-center">
                <AppButton href="/sermons" variant="outline">
                    Browse All Sermons
                    <ArrowRight class="w-4 h-4" />
                </AppButton>
            </div>
        </SectionWrapper>

        <!-- ── Testimonials — hidden until admin adds some via Settings → Homepage ── -->
        <SectionWrapper v-if="testimonials.length > 0 && sectionVisibility.testimonials" bg="white" centered>
            <SectionHeader
                eyebrow="Community Stories"
                title="Lives being changed."
                :subtitle="testimonialsSubtitle ?? 'Hear from people whose lives have been transformed by faith and community.'"
                centered
            />
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div
                    v-for="(t, i) in testimonials"
                    :key="t.name"
                    class="relative bg-neutral-50 border border-neutral-100 rounded-2xl p-7 text-left hover:shadow-elevated hover:-translate-y-0.5 transition-all duration-300 reveal"
                    :class="`reveal-delay-${i + 1}`"
                >
                    <!-- Large quote mark -->
                    <div class="font-display text-5xl text-brand-200 leading-none mb-4 select-none" aria-hidden="true">"</div>
                    <p class="text-neutral-600 leading-relaxed text-sm mb-6">{{ t.text }}</p>
                    <div class="flex items-center gap-3 pt-4 border-t border-neutral-100">
                        <div class="w-9 h-9 rounded-full gradient-brand flex items-center justify-center shrink-0">
                            <span class="text-white text-xs font-bold">{{ t.name[0] }}</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-neutral-900">{{ t.name }}</p>
                            <p class="text-xs text-neutral-400">Church Member</p>
                        </div>
                    </div>
                </div>
            </div>
        </SectionWrapper>

        <!-- ── Announcements ──────────────────────────────────────────────────── -->
        <SectionWrapper v-if="announcements.length > 0 && sectionVisibility.announcements" bg="surface" size="sm">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <p class="text-sm font-semibold tracking-widest uppercase text-brand-500 mb-1">Stay Informed</p>
                    <h2 class="text-2xl font-display text-neutral-900">Latest Announcements</h2>
                </div>
                <AppButton href="/announcements" variant="ghost" size="sm">
                    View All <ArrowRight class="w-3.5 h-3.5" />
                </AppButton>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <AnnouncementCard
                    v-for="(ann, i) in announcements"
                    :key="ann.id"
                    :announcement="ann"
                    class="reveal"
                    :class="`reveal-delay-${i + 1}`"
                />
            </div>
        </SectionWrapper>

        <!-- ── Livestream CTA ─────────────────────────────────────────────────── -->
        <section v-if="props.hasLivestream && sectionVisibility.livestream" class="gradient-dark-mesh relative overflow-hidden">
            <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
                <div class="absolute inset-0" style="background: radial-gradient(ellipse 55% 65% at 85% 50%, rgba(30,90,168,0.22) 0%, transparent 70%);"></div>
                <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/20 to-transparent"></div>
            </div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8 py-24 md:py-32 grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="inline-flex items-center gap-2.5 bg-white/8 border border-white/10 rounded-full px-4 py-2 mb-6">
                        <span class="w-2 h-2 bg-rose-500 rounded-full animate-pulse"></span>
                        <span class="text-white/60 text-sm">Live Every {{ mainServiceDay }}</span>
                    </div>
                    <h2 class="text-4xl md:text-5xl font-display text-white leading-tight mb-5">
                        Can't join us in person?<br />Watch online.
                    </h2>
                    <p class="text-white/40 leading-relaxed mb-8 text-lg">
                        {{ livestreamCta ?? 'Experience our Sunday services from anywhere in the world. Same community, same presence, wherever you are.' }}
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <AppButton href="/live" size="lg" variant="primary">
                            <Play class="w-4 h-4" />
                            Watch Live Stream
                        </AppButton>
                        <AppButton href="/sermons" size="lg" variant="ghost" class="!text-white/60 hover:!text-white hover:!bg-white/10">
                            Browse Sermons
                        </AppButton>
                    </div>
                </div>
                <div class="hidden lg:flex justify-end">
                    <div class="w-72 h-72 rounded-3xl bg-white/5 border border-white/10 flex items-center justify-center backdrop-blur-sm">
                        <div class="w-20 h-20 rounded-full gradient-brand flex items-center justify-center shadow-2xl cursor-pointer hover:scale-105 transition-transform duration-300">
                            <Play class="w-8 h-8 text-white ml-1" />
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Final CTA ──────────────────────────────────────────────────────── -->
        <SectionWrapper bg="white" centered>
            <div class="max-w-2xl mx-auto">
                <div class="flex items-center justify-center gap-3 mb-6">
                    <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-500">New Here?</span>
                </div>
                <h2 class="text-4xl md:text-5xl font-display text-neutral-900 leading-tight mb-5">
                    We would love to meet you.
                </h2>
                <p class="text-lg text-neutral-500 leading-relaxed mb-10">
                    Whether you are searching for faith, community, or just a friendly place to belong —
                    you are welcome here. Come as you are.
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <AppButton href="/about" size="lg" variant="primary">
                        Learn About Us
                        <ArrowRight class="w-4 h-4" />
                    </AppButton>
                    <AppButton href="/contact" size="lg" variant="outline">
                        Get In Touch
                    </AppButton>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
