<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import AboutSubnav from '@/Components/Public/AboutSubnav.vue'
import { useChurch } from '@/composables/useChurch'

interface Tenet { title: string; body: string; refs?: string }

defineProps<{ tenets: Tenet[] }>()

const { church } = useChurch()
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

            <ol class="mt-14 grid grid-cols-1 lg:grid-cols-2 gap-x-10 gap-y-2">
                <li
                    v-for="(tenet, i) in tenets"
                    :key="tenet.title"
                    class="reveal border-t border-neutral-100 py-7 first:border-t-0 lg:first:border-t lg:[&:nth-child(2)]:border-t-0"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <div class="flex items-baseline gap-4">
                        <span class="text-xs font-semibold tabular-nums text-brand-600 shrink-0 pt-1">
                            {{ String(i + 1).padStart(2, '0') }}
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-neutral-900 tracking-tight">
                                {{ tenet.title }}
                            </h2>
                            <p class="mt-2 text-sm text-neutral-600 leading-relaxed">
                                {{ tenet.body }}
                            </p>
                            <p v-if="tenet.refs" class="mt-3 text-xs text-neutral-400 leading-relaxed">
                                {{ tenet.refs }}
                            </p>
                        </div>
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
