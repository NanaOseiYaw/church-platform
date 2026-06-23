<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import AppBadge from '@/Components/UI/AppBadge.vue'
import type { Announcement } from '@/types'

withDefaults(defineProps<{
    announcement: Announcement
    href?: string
}>(), {
    href: '/announcements',
})

// announcement.date is pre-formatted by PublicAnnouncementResource — no new Date() needed.

const categoryColors: Record<string, 'brand' | 'emerald' | 'amber' | 'rose' | 'blue'> = {
    Community: 'brand',
    Outreach:  'emerald',
    Church:    'blue',
    Service:   'amber',
}
</script>

<template>
    <Link :href="href" class="group block bg-white border border-neutral-100 rounded-2xl p-5 card-hover">
        <div class="flex items-start justify-between gap-3 mb-3">
            <AppBadge :color="categoryColors[announcement.category ?? ''] ?? 'neutral'">
                {{ announcement.category }}
            </AppBadge>
            <span class="text-xs text-neutral-400 shrink-0">{{ announcement.date }}</span>
        </div>
        <h3 class="font-semibold text-neutral-900 text-sm leading-snug mb-1.5 group-hover:text-brand-600 transition-colors">
            {{ announcement.title }}
        </h3>
        <p v-if="announcement.excerpt" class="text-sm text-neutral-500 leading-relaxed line-clamp-2">
            {{ announcement.excerpt }}
        </p>
    </Link>
</template>
