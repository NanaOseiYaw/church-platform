<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import AboutSubnav from '@/Components/Public/AboutSubnav.vue'
import { useChurch } from '@/composables/useChurch'

import {
    Megaphone, Footprints, Flame, Award, Compass, Sparkles,
    BookMarked, Scale, HandCoins, HeartHandshake, Church, Star,
} from 'lucide-vue-next'

interface CoreValue { title: string; body: string; refs?: string; icon?: string; image?: string }

defineProps<{ values: CoreValue[] }>()

const { church } = useChurch()

// Imported explicitly rather than resolved dynamically, so Vite can tree-shake
// the icon set instead of bundling all ~1,600 Lucide icons.
const icons: Record<string, any> = {
    Megaphone, Footprints, Flame, Award, Compass, Sparkles,
    BookMarked, Scale, HandCoins, HeartHandshake, Church, Star,
}
</script>

<template>
    <PublicLayout
        title="Core Values"
        :description="`The core values of The Church of Pentecost — what shapes the way ${church.name} worships, serves and grows.`"
    >
        <PageHero
            title="Our Core Values"
            subtitle="The convictions that shape how we worship, how we serve, and how we treat one another."
        />

        <AboutSubnav />

        <SectionWrapper bg="white">
            <div class="max-w-3xl">
                <p class="text-base text-neutral-600 leading-relaxed">
                    These values are held in common across The Church of Pentecost worldwide. They are less a
                    list of ideals than a description of how the Church actually works — in its meetings, its
                    leadership, its giving and its care for the community around it.
                </p>
            </div>

            <div class="mt-14 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <article
                    v-for="(value, i) in values"
                    :key="value.title"
                    class="group bg-white border border-neutral-100 rounded-2xl p-7 hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 reveal"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <div class="flex items-center gap-3 mb-5">
                        <!--
                            The illustration replaces the icon in the same tile rather than
                            taking a banner across the card, which is deliberately unlike the
                            Beliefs page: these twelve entries are text-forward, and a banner
                            on each would double the length of the page and make the two
                            pages look like the same page twice.
                        -->
                        <div
                            v-if="value.image"
                            class="w-20 h-20 rounded-xl bg-brand-50/60 border border-brand-100 overflow-hidden shrink-0"
                        >
                            <img
                                :src="value.image"
                                :alt="''"
                                class="w-full h-full object-cover"
                                loading="lazy"
                                decoding="async"
                                aria-hidden="true"
                            />
                        </div>

                        <!--
                            Fallback until the illustration for this value has been added.
                            Deliberately the same 80px box as the image tile: the twelve are
                            added a few at a time, and a smaller icon tile pushed the heading
                            of every card without artwork out of line with the ones beside it,
                            so a half-finished set looked broken rather than unfinished.
                        -->
                        <div
                            v-else
                            class="w-20 h-20 rounded-xl bg-brand-50 border border-brand-100 flex items-center justify-center shrink-0 group-hover:bg-brand-100 transition-colors"
                        >
                            <component
                                :is="icons[value.icon ?? ''] ?? Star"
                                class="w-8 h-8 text-brand-600"
                                aria-hidden="true"
                            />
                        </div>
                        <span class="text-[10px] font-semibold tabular-nums text-neutral-300">
                            {{ String(i + 1).padStart(2, '0') }}
                        </span>
                    </div>

                    <h2 class="font-semibold text-neutral-900 text-lg tracking-tight mb-2 group-hover:text-brand-700 transition-colors">
                        {{ value.title }}
                    </h2>
                    <p class="text-sm text-neutral-600 leading-relaxed">
                        {{ value.body }}
                    </p>
                    <p v-if="value.refs" class="mt-3 text-xs text-neutral-400">
                        {{ value.refs }}
                    </p>
                </article>
            </div>
        </SectionWrapper>

        <SectionWrapper bg="brand" size="sm">
            <div class="max-w-2xl">
                <h2 class="text-2xl md:text-3xl font-display text-white">Find where you belong</h2>
                <p class="mt-3 text-white/70 leading-relaxed">
                    Every one of these values is lived out by ordinary members serving in ordinary ways.
                    There is a place here for you too.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/ministries" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-white text-brand-700 text-sm font-semibold hover:bg-brand-50 transition-colors">
                        Explore our ministries
                    </a>
                    <a href="/about/beliefs" class="inline-flex items-center px-5 py-2.5 rounded-lg border border-white/25 text-white text-sm font-semibold hover:bg-white/10 transition-colors">
                        What we believe
                    </a>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
