<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import AboutSubnav from '@/Components/Public/AboutSubnav.vue'
import { useChurch } from '@/composables/useChurch'

import {
    BookOpen, Sparkles, HeartCrack, Cross, Sunrise, Droplets,
    Flame, HeartPulse, HandCoins, CloudLightning, Users,
} from 'lucide-vue-next'

interface Tenet { title: string; body: string; refs?: string; icon?: string; image?: string }

defineProps<{ tenets: Tenet[] }>()

const { church } = useChurch()

// Imported explicitly rather than resolved dynamically, so Vite can tree-shake
// the icon set instead of bundling all ~1,600 Lucide icons.
const icons: Record<string, any> = {
    BookOpen, Sparkles, HeartCrack, Cross, Sunrise, Droplets,
    Flame, HeartPulse, HandCoins, CloudLightning, Users,
}
</script>

<template>
    <PublicLayout
        title="Beliefs, Tenets & Principles"
        :description="`The eleven tenets of The Church of Pentecost — the statement of faith held by ${church.name} and every assembly worldwide.`"
    >
        <PageHero
            eyebrow="About Us"
            title="What We Believe"
            subtitle="The eleven tenets of The Church of Pentecost — held in common by every assembly of the Church worldwide."
        />

        <AboutSubnav />

        <SectionWrapper bg="white">
            <div class="max-w-3xl">
                <p class="text-base text-neutral-600 leading-relaxed">
                    These tenets are the doctrinal foundation of The Church of Pentecost. They are not
                    written locally — they are the shared confession of the Church in every nation where
                    it serves, and they shape our preaching, our worship and our life together.
                </p>
            </div>

            <!--
                Cards rather than a list, because an illustration needs its own
                band to breathe. A tenet with no image yet falls back to the icon
                treatment, so the artwork can land one file at a time without the
                page looking half-finished.
            -->
            <ol class="mt-14 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <li
                    v-for="(tenet, i) in tenets"
                    :key="tenet.title"
                    class="reveal group flex flex-col bg-white border border-neutral-100 rounded-2xl overflow-hidden hover:shadow-elevated hover:-translate-y-1 transition-all duration-300"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <!-- Illustration -->
                    <div
                        v-if="tenet.image"
                        class="relative aspect-[3/2] bg-brand-50/40 overflow-hidden"
                    >
                        <img
                            :src="tenet.image"
                            :alt="''"
                            class="w-full h-full object-cover"
                            loading="lazy"
                            decoding="async"
                            aria-hidden="true"
                        />
                        <span class="absolute top-3 left-3 inline-flex items-center justify-center min-w-[1.75rem] h-7 px-2 rounded-lg bg-white/90 backdrop-blur-sm text-[11px] font-semibold tabular-nums text-brand-700 shadow-sm">
                            {{ String(i + 1).padStart(2, '0') }}
                        </span>
                    </div>

                    <!-- Fallback when the illustration has not been added yet -->
                    <div
                        v-else
                        class="relative aspect-[3/2] bg-brand-50/60 border-b border-brand-100/60 flex items-center justify-center"
                    >
                        <component
                            :is="icons[tenet.icon ?? ''] ?? BookOpen"
                            class="w-10 h-10 text-brand-400"
                            aria-hidden="true"
                        />
                        <span class="absolute top-3 left-3 inline-flex items-center justify-center min-w-[1.75rem] h-7 px-2 rounded-lg bg-white/90 text-[11px] font-semibold tabular-nums text-brand-700 shadow-sm">
                            {{ String(i + 1).padStart(2, '0') }}
                        </span>
                    </div>

                    <div class="flex flex-col flex-1 p-6">
                        <h2 class="text-lg font-semibold text-neutral-900 tracking-tight group-hover:text-brand-700 transition-colors">
                            {{ tenet.title }}
                        </h2>
                        <p class="mt-2 text-sm text-neutral-600 leading-relaxed flex-1">
                            {{ tenet.body }}
                        </p>
                        <p v-if="tenet.refs" class="mt-4 pt-3 border-t border-neutral-50 text-xs text-neutral-400 leading-relaxed">
                            {{ tenet.refs }}
                        </p>
                    </div>
                </li>
            </ol>
        </SectionWrapper>

        <SectionWrapper bg="surface" size="sm">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-serif text-neutral-900">Want to talk it through?</h2>
                <p class="mt-3 text-sm text-neutral-600 leading-relaxed">
                    If anything here raises a question, we would rather have the conversation than leave you
                    guessing. Come and visit, or get in touch — someone from the leadership will be glad to meet you.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/contact" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition-colors">
                        Contact us
                    </a>
                    <a href="/about/core-values" class="inline-flex items-center px-5 py-2.5 rounded-lg border border-neutral-200 text-neutral-700 text-sm font-semibold hover:bg-white transition-colors">
                        Our core values
                    </a>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
