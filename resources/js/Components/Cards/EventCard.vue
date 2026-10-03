<script setup lang="ts">
import { computed } from 'vue'
import FlyerImage from '@/Components/UI/FlyerImage.vue'
import { Link } from '@inertiajs/vue3'
import type { Event } from '@/types'
import { CalendarDays, Clock, MapPin } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
    event: Event
    href?: string
}>(), {
    href: undefined,
})

// Derive href: deep-link to the event detail page if not overridden.
const resolvedHref = computed(() => props.href ?? `/events/${props.event.id}`)

// event.date and event.time are pre-formatted by PublicEventResource — no new Date() needed.
</script>

<template>
    <Link :href="resolvedHref" class="group relative block bg-white border border-neutral-100 rounded-2xl overflow-hidden hover:shadow-elevated hover:-translate-y-1 transition-all duration-300">
        <!-- Image / cinematic header -->
        <div class="relative aspect-[16/10] overflow-hidden">
            <!--
                Whole image, never cropped, so a flyer's own text is not cut off.
                Positioned by a wrapper: putting `absolute` on FlyerImage itself
                clashed with its own `relative`, the component fell back into the
                flow at the flyer's full height, and this frame clipped it — the
                exact crop it exists to prevent.
            -->
            <div v-if="event.image" class="absolute inset-0">
                <FlyerImage
                    :src="event.image"
                    :alt="`Image for ${event.title}`"
                    class="w-full h-full"
                />
            </div>
            <!-- No-image: dark styled panel -->
            <div v-else class="absolute inset-0 bg-gradient-to-br from-brand-900 via-neutral-900 to-neutral-950">
                <!-- Subtle grid pattern -->
                <div class="absolute inset-0 opacity-10"
                    style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 32px 32px;">
                </div>
                <CalendarDays class="absolute bottom-5 right-5 w-12 h-12 text-white/10" />
            </div>

            <!-- Gradient overlay — always present -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>

            <!-- Overlaid metadata at bottom of image -->
            <div class="absolute bottom-0 left-0 right-0 p-4 flex items-end justify-between">
                <span class="text-[10px] font-bold uppercase tracking-[0.15em] text-white/60">
                    {{ event.category }}
                </span>
                <span class="text-xs font-medium text-white/80 bg-black/30 backdrop-blur-sm px-2.5 py-1 rounded-lg">
                    {{ event.date }}
                </span>
            </div>
        </div>

        <!-- Content -->
        <div class="p-5">
            <h3 class="font-semibold text-neutral-900 text-base leading-snug mb-3 group-hover:text-brand-600 transition-colors duration-200">
                {{ event.title }}
            </h3>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-neutral-400">
                <div class="flex items-center gap-1.5">
                    <Clock class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ event.time }}</span>
                </div>
                <div class="flex items-center gap-1.5 min-w-0">
                    <MapPin class="w-3.5 h-3.5 shrink-0" />
                    <span class="truncate">{{ event.location }}</span>
                </div>
            </div>
        </div>
    </Link>
</template>
