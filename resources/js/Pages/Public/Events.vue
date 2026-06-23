<script setup lang="ts">
import { ref, computed } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import EventCard from '@/Components/Cards/EventCard.vue'
import type { Event } from '@/types'
import { useChurch } from '@/composables/useChurch'
import { CalendarDays } from 'lucide-vue-next'

const props = defineProps<{
    events: Event[]
    categories: string[]
}>()

const { church } = useChurch()

const activeCategory = ref('All')

const filtered = computed(() =>
    activeCategory.value === 'All'
        ? props.events
        : props.events.filter(e => e.category === activeCategory.value)
)
</script>

<template>
    <PublicLayout title="Events" :description="`Upcoming events and gatherings at ${church.name}.`">

        <PageHero
            eyebrow="Calendar"
            title="Upcoming Events"
            subtitle="Something is always happening here. Join us for worship, community, growth, and service."
        />

        <SectionWrapper bg="white">
            <!-- Category filter -->
            <div class="flex flex-wrap gap-2 mb-10">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    class="px-4 py-2 text-sm font-medium rounded-full border transition-all duration-150"
                    :class="activeCategory === cat
                        ? 'bg-neutral-900 text-white border-neutral-900'
                        : 'bg-white text-neutral-600 border-neutral-200 hover:border-neutral-300 hover:bg-neutral-50'"
                    @click="activeCategory = cat"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- Grid -->
            <div v-if="filtered.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <EventCard
                    v-for="(event, i) in filtered"
                    :key="event.id"
                    :event="event"
                    class="reveal"
                    :class="`reveal-delay-${Math.min(i + 1, 4)}`"
                />
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-24">
                <div class="w-16 h-16 rounded-2xl border border-neutral-100 flex items-center justify-center mx-auto mb-4">
                    <CalendarDays class="w-7 h-7 text-neutral-300" />
                </div>
                <p class="font-semibold text-neutral-500 mb-1">No events in this category</p>
                <p class="text-sm text-neutral-400">Check back soon for upcoming gatherings.</p>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
