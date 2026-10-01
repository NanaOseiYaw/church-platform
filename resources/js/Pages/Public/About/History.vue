<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import AboutSubnav from '@/Components/Public/AboutSubnav.vue'
import { useChurch } from '@/composables/useChurch'

interface Milestone { year: string; title: string; body: string }

defineProps<{
    milestones: Milestone[]
    isTemplate: boolean
    intro: string | null
    localFounded: number | null
    global: {
        founded: number
        founder: string
        founderLifespan: string
        countries: number
        membership: string
    }
}>()

const { church } = useChurch()
</script>

<template>
    <PublicLayout
        title="Our History"
        :description="`The story of The Church of Pentecost, from its founding in 1937, through to ${church.name} today.`"
    >
        <PageHero
            eyebrow="About Us"
            title="Our History"
            subtitle="A church that began with one missionary in the Gold Coast, and now serves in more than 200 nations."
        />

        <AboutSubnav />

        <!-- ── The global story: identical for every assembly ─────────────────── -->
        <SectionWrapper bg="white">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                <div class="lg:col-span-5">
                    <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-600">Where it began</span>
                    <h2 class="mt-4 text-3xl md:text-4xl font-display text-neutral-900 leading-tight">
                        One missionary, sent in {{ global.founded }}
                    </h2>
                </div>

                <div class="lg:col-span-7 space-y-5 text-base text-neutral-600 leading-relaxed">
                    <p>
                        The Church of Pentecost was founded by <strong class="text-neutral-900 font-semibold">{{ global.founder }}</strong>
                        ({{ global.founderLifespan }}), an Irish missionary sent by the Apostolic Church, Bradford,
                        to the Gold Coast — now Ghana — in {{ global.founded }}.
                    </p>
                    <p>
                        What began as a single missionary work grew into one of the largest Pentecostal
                        denominations in the world. Today the Church ministers the word of God in over
                        {{ global.countries }} nations, with a membership of more than {{ global.membership }}
                        believers — held together by a common confession, a common order and a common mission.
                    </p>
                    <p>
                        {{ church.name }} stands within that story: a local assembly of a global church,
                        serving the city of Amsterdam.
                    </p>
                </div>
            </div>

            <div class="mt-14 grid grid-cols-2 lg:grid-cols-4 gap-px bg-neutral-100 border border-neutral-100 rounded-2xl overflow-hidden">
                <div class="bg-white p-6">
                    <p class="text-3xl font-display text-brand-700 tabular-nums">{{ global.founded }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Founded in the Gold Coast</p>
                </div>
                <div class="bg-white p-6">
                    <p class="text-3xl font-display text-brand-700 tabular-nums">{{ global.countries }}+</p>
                    <p class="mt-1 text-xs text-neutral-500">Nations served</p>
                </div>
                <div class="bg-white p-6">
                    <p class="text-3xl font-display text-brand-700 tabular-nums">{{ global.membership }}</p>
                    <p class="mt-1 text-xs text-neutral-500">Members worldwide</p>
                </div>
                <div class="bg-white p-6">
                    <p class="text-3xl font-display text-brand-700 tabular-nums">{{ localFounded ?? '—' }}</p>
                    <p class="mt-1 text-xs text-neutral-500">This assembly established</p>
                </div>
            </div>
        </SectionWrapper>

        <!-- ── The local story ────────────────────────────────────────────────── -->
        <SectionWrapper bg="surface">
            <div class="max-w-3xl">
                <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-600">Here in Amsterdam</span>
                <h2 class="mt-4 text-3xl md:text-4xl font-display text-neutral-900 leading-tight">
                    Our story in this city
                </h2>
                <p v-if="intro" class="mt-5 text-base text-neutral-600 leading-relaxed">{{ intro }}</p>
            </div>

            <!--
                Shown only while the local timeline has not been filled in under
                Settings → About Page. Real dates and names are never invented here.
            -->
            <div
                v-if="isTemplate"
                class="mt-8 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
            >
                <span class="mt-0.5 text-amber-600" aria-hidden="true">●</span>
                <p class="text-sm text-amber-900 leading-relaxed">
                    <strong class="font-semibold">Placeholder timeline.</strong>
                    These milestones are a template. Replace them with the real history of the assembly
                    under <span class="font-medium">Settings → About Page</span>.
                </p>
            </div>

            <ol class="mt-12 relative">
                <!-- Timeline rail -->
                <div class="absolute left-[7px] top-2 bottom-2 w-px bg-neutral-200" aria-hidden="true"></div>

                <li
                    v-for="(m, i) in milestones"
                    :key="`${m.year}-${m.title}`"
                    class="relative pl-10 pb-10 last:pb-0 reveal"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <span
                        class="absolute left-0 top-1.5 w-[15px] h-[15px] rounded-full border-2 border-white bg-brand-600 shadow-sm"
                        aria-hidden="true"
                    ></span>

                    <p class="text-xs font-semibold tracking-[0.15em] uppercase text-brand-600 tabular-nums">
                        {{ m.year }}
                    </p>
                    <h3 class="mt-1.5 text-lg font-semibold text-neutral-900 tracking-tight">{{ m.title }}</h3>
                    <p class="mt-2 text-sm text-neutral-600 leading-relaxed max-w-2xl">{{ m.body }}</p>
                </li>
            </ol>
        </SectionWrapper>
    </PublicLayout>
</template>
