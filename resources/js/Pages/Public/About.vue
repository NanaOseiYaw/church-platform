<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import SectionHeader from '@/Components/UI/SectionHeader.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AboutSubnav from '@/Components/Public/AboutSubnav.vue'
import { useChurch } from '@/composables/useChurch'
import type { TeamMember, ChurchValue } from '@/types'
import { ArrowRight, User } from 'lucide-vue-next'
import { computed } from 'vue'

interface Vision2028 {
    title:      string
    period:     string
    theme:      string
    slogans:    string[]
    aspiration: string
    context:    string
    approaches: { number: string; title: string; body: string }[]
}

const props = defineProps<{
    mission:              string | null
    vision:               string | null
    description:          string | null
    foundedYear:          number | null
    team:                 TeamMember[]
    values:               ChurchValue[]
    heroTitle:            string | null
    heroEyebrow:          string | null
    heroSubtitleOverride: string | null
    leadershipSubtitle:   string | null
    copMission:           string
    vision2028:           Vision2028
}>()

const { church } = useChurch()

const heroSubtitle = computed(() => {
    if (props.heroSubtitleOverride) return props.heroSubtitleOverride
    const year = props.foundedYear ? `Since ${props.foundedYear}, a` : 'A'
    return `${year} community united in faith, growing and serving together.`
})
</script>

<template>
    <PublicLayout
        title="About Us"
        :description="`Learn about ${church.name} — our mission, values, and the people who lead us.`"
    >

        <PageHero
            :eyebrow="heroEyebrow ?? 'About Us'"
            :title="heroTitle ?? 'Mission & Vision'"
            :subtitle="heroSubtitle"
        />

        <AboutSubnav />

        <!-- Mission + Values -->
        <SectionWrapper bg="white">
            <div :class="values.length > 0 ? 'grid lg:grid-cols-2 gap-16 items-start' : 'max-w-2xl'">
                <div class="reveal">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="h-px w-8 bg-brand-500 shrink-0"></div>
                        <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-500">Our Mission</span>
                    </div>

                    <!-- Mission statement: DB-driven, fallback to generic -->
                    <h2 class="text-3xl md:text-4xl font-serif font-normal text-neutral-900 leading-tight mb-6 whitespace-pre-line">
                        {{ mission ?? 'Glorifying God and\nserving our community.' }}
                    </h2>

                    <!-- Description / vision: DB-driven, fallback to generic -->
                    <p class="text-neutral-500 leading-relaxed text-lg mb-8">
                        {{ description ?? 'We exist to know God and make Him known — through worship, community, and service to the world around us.' }}
                    </p>

                    <p v-if="vision" class="text-neutral-500 leading-relaxed mb-8">
                        {{ vision }}
                    </p>

                    <AppButton href="/contact" variant="primary">
                        Connect with Us <ArrowRight class="w-4 h-4" />
                    </AppButton>
                </div>

                <div v-if="values.length > 0" class="grid grid-cols-2 gap-4 reveal reveal-delay-2">
                    <div
                        v-for="value in values"
                        :key="value.title"
                        class="group rounded-2xl border border-neutral-100 bg-neutral-50 p-6 hover:border-brand-100 hover:bg-brand-50/30 hover:-translate-y-1 transition-all duration-300"
                    >
                        <h3 class="font-semibold text-neutral-900 mb-2 group-hover:text-brand-700 transition-colors">{{ value.title }}</h3>
                        <p class="text-sm text-neutral-500 leading-relaxed">{{ value.description }}</p>
                    </div>
                </div>
            </div>
        </SectionWrapper>

        <!--
            Vision 2028 — the denomination's current five-year agenda, shared by
            every assembly worldwide. Content comes from config/cop.php rather than
            this church's record, because no local assembly authors it.
        -->
        <SectionWrapper bg="dark">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-3 mb-6 reveal">
                    <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-400">
                        {{ vision2028.title }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white/10 text-[11px] font-medium text-white/70 tabular-nums">
                        {{ vision2028.period }}
                    </span>
                </div>

                <h2 class="text-3xl md:text-4xl font-serif font-normal text-white leading-tight reveal reveal-delay-1">
                    {{ vision2028.theme }}
                </h2>

                <p class="mt-6 text-base text-white/70 leading-relaxed reveal reveal-delay-2">
                    {{ vision2028.aspiration }}
                </p>
                <p class="mt-4 text-sm text-white/50 leading-relaxed reveal reveal-delay-2">
                    {{ vision2028.context }}
                </p>

                <!-- Slogans -->
                <ul class="mt-8 flex flex-wrap gap-2.5 reveal reveal-delay-3">
                    <li
                        v-for="slogan in vision2028.slogans"
                        :key="slogan"
                        class="inline-flex items-center rounded-lg border border-brand-400/30 bg-brand-500/10 px-3.5 py-2 text-sm text-brand-200"
                    >
                        {{ slogan }}
                    </li>
                </ul>
            </div>

            <!-- The four strategic approaches -->
            <ol class="mt-14 grid grid-cols-1 md:grid-cols-2 gap-x-10 gap-y-8">
                <li
                    v-for="(approach, i) in vision2028.approaches"
                    :key="approach.number"
                    class="reveal border-t border-white/10 pt-6"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <span class="text-xs font-semibold tabular-nums text-brand-400">{{ approach.number }}</span>
                    <h3 class="mt-2 text-lg font-semibold text-white tracking-tight">{{ approach.title }}</h3>
                    <p class="mt-2 text-sm text-white/60 leading-relaxed">{{ approach.body }}</p>
                </li>
            </ol>

            <!-- The denomination's own mission statement -->
            <figure class="mt-16 max-w-2xl border-l-2 border-brand-400/70 pl-5 reveal">
                <blockquote class="font-serif text-lg md:text-xl italic text-white/90 leading-relaxed">
                    &ldquo;{{ copMission }}&rdquo;
                </blockquote>
                <figcaption class="mt-3 text-xs font-semibold tracking-[0.2em] uppercase text-brand-300">
                    Mission of The Church of Pentecost
                </figcaption>
            </figure>
        </SectionWrapper>

        <!-- Leadership team — hidden when empty so churches without team data don't see a blank section -->
        <SectionWrapper v-if="team.length > 0" bg="surface">
            <SectionHeader
                eyebrow="Leadership"
                title="Meet our team."
                :subtitle="leadershipSubtitle ?? 'Meet the leaders who serve our community.'"
            />
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="(member, i) in team"
                    :key="member.name"
                    class="group bg-white border border-neutral-100 rounded-2xl overflow-hidden hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 reveal"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <!-- Photo -->
                    <div class="relative aspect-[4/3] bg-gradient-to-br from-brand-900 to-neutral-900 overflow-hidden">
                        <img v-if="member.image" :src="member.image" :alt="member.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" />
                        <div v-else class="absolute inset-0 flex items-end p-5">
                            <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(30,90,168,0.3), rgba(15,15,20,0.9));"></div>
                            <User class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-12 h-12 text-white/20" />
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
                    </div>
                    <!-- Info -->
                    <div class="p-5">
                        <h3 class="font-semibold text-neutral-900 mb-0.5">{{ member.name }}</h3>
                        <p class="text-sm text-brand-600 font-medium mb-3">{{ member.role }}</p>
                        <p class="text-sm text-neutral-500 leading-relaxed">{{ member.bio }}</p>
                    </div>
                </div>
            </div>
        </SectionWrapper>

        <!-- CTA -->
        <section class="gradient-dark-mesh relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/20 to-transparent" aria-hidden="true"></div>
            <div class="mx-auto max-w-7xl px-6 lg:px-8 py-24 text-center">
                <h2 class="text-4xl md:text-5xl font-serif font-normal text-white leading-tight mb-5 max-w-xl mx-auto">
                    Ready to become part of the family?
                </h2>
                <p class="text-white/45 text-lg mb-8">Visit us this Sunday and experience the community for yourself.</p>
                <AppButton href="/contact" size="lg" variant="primary">
                    Plan Your Visit <ArrowRight class="w-4 h-4" />
                </AppButton>
            </div>
        </section>
    </PublicLayout>
</template>
