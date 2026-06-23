<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppButton from '@/Components/UI/AppButton.vue'
import { Menu, X } from 'lucide-vue-next'
import { useChurch } from '@/composables/useChurch'
import { useTenantStore } from '@/stores/useTenantStore'

const { church } = useChurch()
const tenant     = useTenantStore()
const page       = usePage()
const authUser   = computed(() => (page.props as any).auth?.user ?? null)

const mobileOpen = ref(false)
// Scroll only adds a shadow for elevation — the brand-600 background is always solid
const scrolled   = ref(false)

const navLinks = [
    { label: 'About',         href: '/about' },
    { label: 'Ministries',    href: '/ministries' },
    { label: 'Events',        href: '/events' },
    { label: 'Sermons',       href: '/sermons' },
    { label: 'Series',        href: '/series' },
    { label: 'Gallery',       href: '/gallery' },
    { label: 'Announcements', href: '/announcements' },
    { label: 'Prayer',        href: '/prayer' },
    { label: 'Contact',       href: '/contact' },
]

function handleScroll() {
    scrolled.value = window.scrollY > 12
}

onMounted(() => window.addEventListener('scroll', handleScroll, { passive: true }))
onUnmounted(() => window.removeEventListener('scroll', handleScroll))
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
                <Link
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    class="px-3.5 py-2 text-sm font-medium rounded-lg transition-all duration-150 text-white/75 hover:text-white hover:bg-white/12"
                >
                    {{ link.label }}
                </Link>
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
                    <Link
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="px-3.5 py-2.5 text-sm font-medium text-white/75 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
                        @click="mobileOpen = false"
                    >
                        {{ link.label }}
                    </Link>

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
