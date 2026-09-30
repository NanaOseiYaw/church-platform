<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

/**
 * Secondary navigation shared by the four About sub-pages, so a visitor can
 * move between them without going back up to the navbar dropdown.
 * Mirrors the structure used across Church of Pentecost national sites.
 */
const allLinks = [
    { slug: 'overview',    label: 'Overview',         href: '/about' },
    { slug: 'leadership',  label: 'Leadership',       href: '/about/leadership' },
    { slug: 'history',     label: 'Our History',      href: '/about/history' },
    { slug: 'beliefs',     label: 'Beliefs & Tenets', href: '/about/beliefs' },
    { slug: 'core-values', label: 'Core Values',      href: '/about/core-values' },
]

const page    = usePage()
const current = computed(() => page.url.split('?')[0].replace(/\/$/, '') || '/about')

// Same gate as the navbar dropdown: sub-pages still holding template content are
// omitted by the server, so this tab bar never points at a placeholder page.
// The current page always stays visible, so an admin previewing an unfinished
// page does not lose the tab they are standing on.
const ready = computed<string[]>(() => (page.props as any).aboutPages ?? [])
const links = computed(() =>
    allLinks.filter(l => l.slug === 'overview' || ready.value.includes(l.slug) || current.value === l.href)
)
</script>

<template>
    <nav aria-label="About sections" class="border-b border-neutral-100 bg-white sticky top-16 z-30">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <ul class="flex items-center gap-1 overflow-x-auto scrollbar-none -mb-px">
                <li v-for="link in links" :key="link.href" class="shrink-0">
                    <Link
                        :href="link.href"
                        class="inline-flex items-center px-4 py-4 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
                        :class="current === link.href
                            ? 'border-brand-600 text-brand-700'
                            : 'border-transparent text-neutral-500 hover:text-neutral-900 hover:border-neutral-200'"
                        :aria-current="current === link.href ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </li>
            </ul>
        </div>
    </nav>
</template>
