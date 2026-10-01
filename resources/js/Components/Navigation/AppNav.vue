<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppButton from '@/Components/UI/AppButton.vue'
import { Menu, X, ChevronDown } from 'lucide-vue-next'
import { useChurch } from '@/composables/useChurch'
import { useTenantStore } from '@/stores/useTenantStore'

const { church } = useChurch()
const tenant     = useTenantStore()
const page       = usePage()
const authUser   = computed(() => (page.props as any).auth?.user ?? null)

const mobileOpen = ref(false)
// Scroll only adds a shadow for elevation — the brand-600 background is always solid
const scrolled   = ref(false)

/**
 * A `slug` marks a child whose visibility the server decides — currently only
 * the About sub-pages, which stay hidden until an admin fills them in. Children
 * without one are always shown.
 */
interface NavChild { label: string; href: string; slug?: string; description?: string }
interface NavLink  { label: string; href: string; children?: NavChild[] }

// Sub-pages that are not yet filled in are omitted by the server (see
// App\Support\AboutPages), so the dropdown never links to a placeholder page.
const readyAboutPages = computed<string[]>(() => (page.props as any).aboutPages ?? [])

// The About group mirrors the structure used across Church of Pentecost
// national sites: Leadership, History, Beliefs & Tenets, Core Values.
const allNavLinks: NavLink[] = [
    {
        label: 'About',
        href:  '/about',
        children: [
            // 'overview' is the /about page itself. It is never gated — it always has
            // content — so it is listed first, as a sibling of the other sections.
            { slug: 'overview',    label: 'Mission & Vision', href: '/about',             description: 'Who we are and where we are going' },
            { slug: 'beliefs',     label: 'Beliefs & Tenets', href: '/about/beliefs',     description: 'The eleven tenets of the Church' },
            { slug: 'core-values', label: 'Core Values',      href: '/about/core-values', description: 'What shapes how we serve' },
            { slug: 'leadership',  label: 'Leadership',       href: '/about/leadership',  description: 'Meet the leadership of the assembly' },
            { slug: 'history',     label: 'Our History',      href: '/about/history',     description: 'From 1937 in the Gold Coast to today' },
        ],
    },
    { label: 'Ministries', href: '/ministries' },
    {
        // "What's On" rather than "Events & News": it is the phrase the homepage
        // already uses for this material, and it asks the visitor's question
        // rather than naming our two content types.
        label: "What's On",
        href:  '/events',
        children: [
            // As with About, the first child is the group's own destination, so
            // clicking the label and opening the menu lead to the same place
            // rather than the label being a dead end.
            { label: 'Events',        href: '/events',        description: 'Services, meetings and gatherings' },
            { label: 'Announcements', href: '/announcements', description: 'News from the assembly' },
        ],
    },
    {
        label: 'Media',
        href:  '/sermons',
        children: [
            { label: 'Sermons', href: '/sermons', description: 'The full library, most recent first' },
            { label: 'Series',  href: '/series',  description: 'Messages grouped into teaching series' },
            { label: 'Gallery', href: '/gallery', description: 'Photos from the life of the church' },
        ],
    },
    // Prayer and Contact stay one click away on purpose. They are what a visitor
    // goes looking for, and burying them to save two slots would cost more than
    // the crowding does.
    { label: 'Prayer',  href: '/prayer' },
    { label: 'Contact', href: '/contact' },
]

// Drop any sub-page the server has not marked ready. If that leaves a group with
// no children at all, it degrades to a plain link rather than an empty dropdown.
const navLinks = computed<NavLink[]>(() =>
    allNavLinks.map((link) => {
        if (!link.children) return link

        const children = link.children.filter(c => !c.slug || readyAboutPages.value.includes(c.slug))

        return children.length ? { ...link, children } : { label: link.label, href: link.href }
    })
)

// Desktop dropdown. Opens on hover and on focus, so it is reachable by keyboard
// as well as by mouse; Escape closes it and returns focus to the trigger.
const openMenu = ref<string | null>(null)
let closeTimer: ReturnType<typeof setTimeout> | undefined

function openDropdown(label: string) {
    clearTimeout(closeTimer)
    openMenu.value = label
}

// Small delay so moving the pointer from the trigger into the panel
// does not close the menu in the gap between them.
function scheduleClose() {
    clearTimeout(closeTimer)
    closeTimer = setTimeout(() => { openMenu.value = null }, 120)
}

function closeDropdown() {
    clearTimeout(closeTimer)
    openMenu.value = null
}

// Mobile accordion — independent of the desktop dropdown state.
const expanded = ref<string | null>(null)
function toggleExpanded(label: string) {
    expanded.value = expanded.value === label ? null : label
}

function closeMobile() {
    mobileOpen.value = false
    expanded.value = null
}

function handleScroll() {
    scrolled.value = window.scrollY > 12
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') closeDropdown()
}

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true })
    window.addEventListener('keydown', handleKeydown)
})
onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll)
    window.removeEventListener('keydown', handleKeydown)
    clearTimeout(closeTimer)
})
</script>

<template>
    <!--
        Navbar background is always solid brand-600 (set by the church's primary colour).
        Scrolling only adds a drop-shadow for elevation context.
        Text is always white — safe contrast regardless of brand colour.
    -->
    <header
        class="fixed top-0 inset-x-0 z-50 bg-brand-600 transition-shadow duration-300"
        :class="scrolled ? 'shadow-md' : 'shadow-none'"
    >
        <nav class="mx-auto max-w-7xl px-6 lg:px-8 h-16 flex items-center justify-between">

            <!-- Logo -->
            <Link href="/" class="flex items-center gap-2.5 group shrink-0">
                <img
                    v-if="church.logo"
                    :src="church.logo"
                    :alt="church.name"
                    class="w-8 h-8 rounded-lg object-contain bg-white/10 border border-white/20 shrink-0"
                />
                <div v-else class="w-8 h-8 rounded-lg bg-white/20 border border-white/25 flex items-center justify-center shrink-0">
                    <span class="text-white text-xs font-bold tracking-tight">{{ tenant.churchInitials }}</span>
                </div>
                <span class="font-semibold text-sm text-white tracking-tight">{{ church.name }}</span>
            </Link>

            <!-- Desktop nav links -->
            <div class="hidden lg:flex items-center gap-0.5">
                <template v-for="link in navLinks" :key="link.href">

                    <!-- Plain link -->
                    <Link
                        v-if="!link.children"
                        :href="link.href"
                        class="px-3.5 py-2 text-sm font-medium rounded-lg transition-all duration-150 text-white/75 hover:text-white hover:bg-white/12"
                    >
                        {{ link.label }}
                    </Link>

                    <!-- Link with dropdown -->
                    <div
                        v-else
                        class="relative"
                        @mouseenter="openDropdown(link.label)"
                        @mouseleave="scheduleClose"
                    >
                        <Link
                            :href="link.href"
                            class="flex items-center gap-1 px-3.5 py-2 text-sm font-medium rounded-lg transition-all duration-150 text-white/75 hover:text-white hover:bg-white/12"
                            :aria-expanded="openMenu === link.label"
                            aria-haspopup="true"
                            @focus="openDropdown(link.label)"
                        >
                            {{ link.label }}
                            <ChevronDown
                                class="w-3.5 h-3.5 transition-transform duration-200"
                                :class="openMenu === link.label && 'rotate-180'"
                                aria-hidden="true"
                            />
                        </Link>

                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 -translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 -translate-y-1"
                        >
                            <div
                                v-if="openMenu === link.label"
                                class="absolute left-0 top-full pt-2 w-72"
                            >
                                <div class="bg-white rounded-xl shadow-elevated border border-neutral-100 p-2 overflow-hidden">
                                    <Link
                                        v-for="child in link.children"
                                        :key="child.href"
                                        :href="child.href"
                                        class="block px-3 py-2.5 rounded-lg hover:bg-brand-50 transition-colors group/item"
                                        @click="closeDropdown"
                                    >
                                        <span class="block text-sm font-semibold text-neutral-900 group-hover/item:text-brand-700">
                                            {{ child.label }}
                                        </span>
                                        <span v-if="child.description" class="block text-xs text-neutral-500 mt-0.5 leading-snug">
                                            {{ child.description }}
                                        </span>
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </template>
            </div>

            <!-- Desktop CTAs -->
            <div class="hidden lg:flex items-center gap-2 shrink-0">
                <!-- Dashboard / Sign In -->
                <AppButton
                    v-if="authUser"
                    href="/dashboard"
                    variant="ghost"
                    size="sm"
                    class="!text-white/75 hover:!text-white hover:!bg-white/12"
                >
                    Dashboard
                </AppButton>
                <AppButton
                    v-else
                    href="/login"
                    variant="ghost"
                    size="sm"
                    class="!text-white/75 hover:!text-white hover:!bg-white/12"
                >
                    Sign In
                </AppButton>

                <!-- Live indicator -->
                <AppButton
                    href="/live"
                    variant="ghost"
                    size="sm"
                    class="!text-white/75 hover:!text-white hover:!bg-white/12 gap-2"
                >
                    <span class="w-2 h-2 bg-rose-400 rounded-full animate-pulse shrink-0"></span>
                    Live
                </AppButton>

                <!-- Give — inverted: white bg + brand text for max contrast -->
                <AppButton
                    href="/give"
                    size="sm"
                    variant="ghost"
                    class="!bg-white !text-brand-700 hover:!bg-brand-50 font-semibold"
                >
                    Give
                </AppButton>
            </div>

            <!-- Mobile hamburger -->
            <button
                class="lg:hidden p-2 rounded-lg text-white/80 hover:text-white hover:bg-white/12 transition-colors"
                @click="mobileOpen = !mobileOpen"
                :aria-label="mobileOpen ? 'Close menu' : 'Open menu'"
            >
                <X v-if="mobileOpen" class="w-5 h-5" />
                <Menu v-else class="w-5 h-5" />
            </button>
        </nav>

        <!-- Mobile menu — same dark blue, consistent brand -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="mobileOpen"
                class="lg:hidden bg-brand-700 border-t border-white/10"
            >
                <div class="mx-auto max-w-7xl px-6 py-4 flex flex-col gap-0.5">
                    <template v-for="link in navLinks" :key="link.href">

                        <!-- Plain link -->
                        <Link
                            v-if="!link.children"
                            :href="link.href"
                            class="px-3.5 py-2.5 text-sm font-medium text-white/75 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
                            @click="closeMobile"
                        >
                            {{ link.label }}
                        </Link>

                        <!-- Group: tapping the label expands; the label itself still links -->
                        <div v-else>
                            <div class="flex items-center">
                                <Link
                                    :href="link.href"
                                    class="flex-1 px-3.5 py-2.5 text-sm font-medium text-white/75 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
                                    @click="closeMobile"
                                >
                                    {{ link.label }}
                                </Link>
                                <button
                                    type="button"
                                    class="p-2 mr-1 rounded-lg text-white/60 hover:text-white hover:bg-white/10 transition-colors"
                                    :aria-expanded="expanded === link.label"
                                    :aria-label="`${expanded === link.label ? 'Collapse' : 'Expand'} ${link.label} menu`"
                                    @click="toggleExpanded(link.label)"
                                >
                                    <ChevronDown
                                        class="w-4 h-4 transition-transform duration-200"
                                        :class="expanded === link.label && 'rotate-180'"
                                    />
                                </button>
                            </div>

                            <div v-if="expanded === link.label" class="ml-3.5 pl-3 border-l border-white/15 flex flex-col gap-0.5 mt-0.5 mb-1">
                                <Link
                                    v-for="child in link.children"
                                    :key="child.href"
                                    :href="child.href"
                                    class="px-3.5 py-2 text-sm text-white/65 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
                                    @click="closeMobile"
                                >
                                    {{ child.label }}
                                </Link>
                            </div>
                        </div>
                    </template>

                    <!-- Mobile CTAs -->
                    <div class="mt-3 pt-3 border-t border-white/10 space-y-2">
                        <AppButton
                            v-if="authUser"
                            href="/dashboard"
                            variant="ghost"
                            size="sm"
                            class="w-full !text-white/75 hover:!text-white hover:!bg-white/10 !border-white/20"
                        >
                            Dashboard
                        </AppButton>
                        <AppButton
                            v-else
                            href="/login"
                            variant="ghost"
                            size="sm"
                            class="w-full !text-white/75 hover:!text-white hover:!bg-white/10"
                        >
                            Sign In
                        </AppButton>
                        <div class="flex gap-2">
                            <AppButton
                                href="/live"
                                variant="ghost"
                                size="sm"
                                class="flex-1 !text-white/75 hover:!text-white hover:!bg-white/10"
                            >
                                <span class="w-2 h-2 bg-rose-400 rounded-full animate-pulse"></span>
                                Watch Live
                            </AppButton>
                            <AppButton
                                href="/give"
                                size="sm"
                                variant="ghost"
                                class="flex-1 !bg-white !text-brand-700 hover:!bg-brand-50 font-semibold"
                            >
                                Give
                            </AppButton>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </header>
</template>
