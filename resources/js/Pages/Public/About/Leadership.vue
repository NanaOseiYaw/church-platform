<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import AboutSubnav from '@/Components/Public/AboutSubnav.vue'
import { useChurch } from '@/composables/useChurch'

interface Leader { name: string; role: string; bio?: string; photo?: string | null }

defineProps<{
    leaders: Leader[]
    isTemplate: boolean
    intro: string | null
}>()

const { church } = useChurch()

function initials(name: string): string {
    return name.split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>

<template>
    <PublicLayout
        title="Leadership"
        :description="`Meet the leadership of ${church.name}.`"
    >
        <PageHero
            title="Our Leadership"
            subtitle="The men and women who shepherd this assembly and serve its ministries."
        />

        <AboutSubnav />

        <SectionWrapper bg="white">
            <div class="max-w-3xl">
                <p v-if="intro" class="text-base text-neutral-600 leading-relaxed">{{ intro }}</p>
                <p v-else class="text-base text-neutral-600 leading-relaxed">
                    Leadership in The Church of Pentecost rests on an apostolic foundation. Appointments are
                    made on the basis of character, calling and the leading of the Holy Spirit — and leaders
                    grow from within the congregation they go on to serve.
                </p>
            </div>

            <!--
                Shown only while real leaders have not been added under
                Settings → About Page. Real names are never invented here.
            -->
            <div
                v-if="isTemplate"
                class="mt-8 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4"
            >
                <span class="mt-0.5 text-amber-600" aria-hidden="true">●</span>
                <p class="text-sm text-amber-900 leading-relaxed">
                    <strong class="font-semibold">Placeholder profiles.</strong>
                    These are role templates, not real people. Add the assembly's actual leadership —
                    names, roles, photos and short introductions — under
                    <span class="font-medium">Settings → About Page</span>.
                </p>
            </div>

            <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <article
                    v-for="(leader, i) in leaders"
                    :key="`${leader.name}-${leader.role}`"
                    class="group bg-white border border-neutral-100 rounded-2xl p-7 hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 reveal"
                    :class="[`reveal-delay-${Math.min(i + 1, 4)}`, isTemplate && 'opacity-80']"
                >
                    <!-- Photo, or initials fallback -->
                    <img
                        v-if="leader.photo"
                        :src="leader.photo"
                        :alt="`Portrait of ${leader.name}`"
                        class="w-16 h-16 rounded-full object-cover border border-neutral-100 mb-5"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="w-16 h-16 rounded-full bg-brand-50 border border-brand-100 flex items-center justify-center mb-5"
                        aria-hidden="true"
                    >
                        <span class="text-sm font-semibold text-brand-600 tracking-wide">{{ initials(leader.name) }}</span>
                    </div>

                    <h2 class="font-semibold text-neutral-900 text-lg tracking-tight group-hover:text-brand-700 transition-colors">
                        {{ leader.name }}
                    </h2>
                    <p class="mt-0.5 text-sm font-medium text-brand-600">{{ leader.role }}</p>
                    <p v-if="leader.bio" class="mt-3 text-sm text-neutral-600 leading-relaxed">{{ leader.bio }}</p>
                </article>
            </div>
        </SectionWrapper>

        <SectionWrapper bg="surface" size="sm">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-display text-neutral-900">Get in touch with the leadership</h2>
                <p class="mt-3 text-sm text-neutral-600 leading-relaxed">
                    Whether you are new, looking for prayer, or want to serve — the leadership of
                    {{ church.name }} would be glad to hear from you.
                </p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="/contact" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-brand-600 text-white text-sm font-semibold hover:bg-brand-700 transition-colors">
                        Contact us
                    </a>
                    <a href="/prayer" class="inline-flex items-center px-5 py-2.5 rounded-lg border border-neutral-200 text-neutral-700 text-sm font-semibold hover:bg-white transition-colors">
                        Request prayer
                    </a>
                </div>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
