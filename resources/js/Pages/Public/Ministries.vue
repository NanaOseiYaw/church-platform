<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import type { Ministry } from '@/types'
import { useChurch } from '@/composables/useChurch'
import { Music, Zap, Heart, Globe, Users, Star, ArrowRight } from 'lucide-vue-next'

defineProps<{ ministries: Ministry[] }>()

const { church } = useChurch()

const iconMap: Record<string, any> = { music: Music, zap: Zap, heart: Heart, globe: Globe, users: Users, star: Star }

const colorMap: Record<string, string> = {
    indigo:  'border-brand-100 bg-brand-50 text-brand-600',
    violet:  'border-brand-100 bg-brand-50 text-brand-600',
    rose:    'border-rose-100 bg-rose-50 text-rose-600',
    emerald: 'border-emerald-100 bg-emerald-50 text-emerald-600',
    blue:    'border-blue-100 bg-blue-50 text-blue-600',
    amber:   'border-amber-100 bg-amber-50 text-amber-600',
}
</script>

<template>
    <PublicLayout title="Ministries" :description="`Explore our ministries and find where you belong at ${church.name}.`">

        <PageHero
            eyebrow="Community"
            title="Our Ministries"
            subtitle="Every person has a place here. Find the ministry that is made for you."
        />

        <SectionWrapper bg="white">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div
                    v-for="(ministry, i) in ministries"
                    :key="ministry.id"
                    class="group bg-white border border-neutral-100 rounded-2xl p-7 hover:shadow-elevated hover:-translate-y-1 transition-all duration-300 reveal"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                >
                    <!-- Icon -->
                    <div
                        class="w-11 h-11 rounded-xl border flex items-center justify-center mb-5"
                        :class="colorMap[ministry.color] ?? 'border-neutral-100 bg-neutral-50 text-neutral-500'"
                    >
                        <component :is="iconMap[ministry.icon] ?? Star" class="w-5 h-5" />
                    </div>

                    <h3 class="font-semibold text-neutral-900 text-lg mb-2 group-hover:text-brand-600 transition-colors duration-200">
                        {{ ministry.name }}
                    </h3>
                    <p class="text-sm text-neutral-500 leading-relaxed mb-4">{{ ministry.description }}</p>

                    <div class="flex items-center justify-between pt-4 border-t border-neutral-50">
                        <p class="text-xs text-neutral-400">Led by <span class="text-neutral-600 font-medium">{{ ministry.leader }}</span></p>
                        <ArrowRight class="w-4 h-4 text-neutral-300 group-hover:text-brand-500 group-hover:translate-x-0.5 transition-all duration-200" />
                    </div>
                </div>
            </div>
        </SectionWrapper>

        <!-- CTA -->
        <SectionWrapper bg="surface" centered size="sm">
            <div class="flex items-center justify-center gap-3 mb-5">
                <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-500">Get Connected</span>
            </div>
            <h2 class="text-3xl font-display text-neutral-900 mb-4">Not sure where to start?</h2>
            <p class="text-neutral-500 mb-7 max-w-sm mx-auto">Reach out to us and we will help you find the right fit for your season of life.</p>
            <AppButton href="/contact" variant="primary">
                Get Connected <ArrowRight class="w-4 h-4" />
            </AppButton>
        </SectionWrapper>
    </PublicLayout>
</template>
