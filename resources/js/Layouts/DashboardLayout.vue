<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import { onClickOutside } from '@vueuse/core'
import { useRealtimeNotifications } from '@/composables/useRealtimeNotifications'
import { disconnectEcho } from '@/Services/realtime/echo'
import { useAuthStore } from '@/stores/useAuthStore'
import { useTenantStore } from '@/stores/useTenantStore'
import { useFlash } from '@/composables/useFlash'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { useBrandColor } from '@/composables/useBrandColor'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import ToastContainer from '@/Components/Dashboard/ToastContainer.vue'
import NotificationDropdown from '@/Components/Dashboard/NotificationDropdown.vue'
import GlobalSearchModal from '@/Components/Search/GlobalSearchModal.vue'
import { useSearchStore } from '@/stores/useSearchStore'
import {
    LayoutDashboard, CalendarDays, Megaphone, CheckSquare, Mic2,
    Users, Building2, BarChart3, Settings, LogOut,
    Menu, ChevronRight, ExternalLink, Upload, ChevronDown,
    CalendarCheck2, Search, Command, UserCog, ClipboardList, ShieldCheck,
    Send, FileText, Images, Heart,
} from 'lucide-vue-next'

withDefaults(defineProps<{
    title?: string
    breadcrumbs?: { label: string; href?: string }[]
}>(), {})

const auth   = useAuthStore()
const tenant = useTenantStore()
const toast  = useNotificationStore()
const search = useSearchStore()
const { flash } = useFlash()
const page = usePage()

// Apply the church's saved primary_color to the CSS brand-* palette at runtime.
// This overrides the static indigo defaults baked into app.css so every
// bg-brand-*, text-brand-*, ring-brand-* class reflects the live DB value.
useBrandColor()

// Flash → toast bridge
watch(() => flash.value.success, v => { if (v) toast.success(v) }, { immediate: true })
watch(() => flash.value.error,   v => { if (v) toast.error(v) },   { immediate: true })

// ── Real-time: mount notification listener once for the whole dashboard ───────
useRealtimeNotifications()

// ── Sidebar state ────────────────────────────────────────────────────────────
const sidebarOpen     = ref(false)
const userMenuOpen    = ref(false)
const userMenuRef     = ref<HTMLElement | null>(null)

onClickOutside(userMenuRef, () => { userMenuOpen.value = false })

// ── Navigation definition ────────────────────────────────────────────────────
interface NavItem {
    label: string
    href: string
    icon: any
    perm?: string
    adminOnly?: boolean
    coordinatorOk?: boolean
    exact?: boolean          // when true: only highlight on exact URL match
    badge?: () => number | null
}

interface NavGroup {
    label: string
    items: NavItem[]
}

const navGroups = computed<NavGroup[]>(() => {
    const all: NavGroup[] = [
        {
            label: 'Overview',
            items: [
                { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
            ],
        },
        {
            label: 'Ministry',
            items: [
                { label: 'Events',        href: '/dashboard/events',        icon: CalendarDays, perm: 'events.view' },
                { label: 'Announcements', href: '/dashboard/announcements', icon: Megaphone,    perm: 'announcements.view',
                  badge: () => auth.unreadAnnouncements > 0 ? auth.unreadAnnouncements : null },
                { label: 'Sermons',       href: '/dashboard/sermons',       icon: Mic2,         perm: 'sermons.view' },
                { label: 'Prayer',        href: '/dashboard/prayer-requests', icon: Heart,      adminOnly: true, coordinatorOk: true },
            ],
        },
        {
            label: 'Work',
            items: [
                { label: 'Tasks',      href: '/dashboard/tasks',      icon: CheckSquare,   perm: 'tasks.view_own',
                  badge: () => auth.overdueTasksCount > 0 ? auth.overdueTasksCount : null },
                { label: 'Scheduling', href: '/dashboard/scheduling',             icon: ClipboardList, perm: 'scheduling.manage' },
                { label: 'My Schedule',href: '/dashboard/scheduling/my-schedule', icon: CalendarDays,  perm: 'scheduling.view', exact: true },
                { label: 'Attendance', href: '/dashboard/attendance', icon: CalendarCheck2, perm: 'attendance.view' },
                { label: 'Media',      href: '/dashboard/media',      icon: Upload,        perm: 'media.view' },
                { label: 'Gallery',    href: '/dashboard/gallery',    icon: Images,        perm: 'media.view' },
            ],
        },
        {
            label: 'People',
            items: [
                { label: 'Members',       href: '/dashboard/members',       icon: Users,        perm: 'members.view' },
                { label: 'Departments',   href: '/dashboard/departments',   icon: Building2,    perm: 'departments.view' },
            ],
        },
        {
            label: 'Communication',
            items: [
                { label: 'Broadcasts', href: '/dashboard/communication/broadcasts', icon: Send,     perm: 'communication.view' },
                { label: 'Templates',  href: '/dashboard/communication/templates',  icon: FileText, perm: 'communication.view' },
                { label: 'Audiences',  href: '/dashboard/communication/audiences',  icon: Users,    perm: 'communication.view' },
            ],
        },
        {
            label: 'Administration',
            items: [
                { label: 'Reports',       href: '/dashboard/reports',          icon: BarChart3, perm: 'reports.view' },
                // href stays at /dashboard/settings so startsWith() highlights
                // the item across all section sub-paths (/branding, /contact, etc.)
                // The controller redirects /dashboard/settings → /dashboard/settings/branding.
                { label: 'Settings',      href: '/dashboard/settings', icon: Settings, adminOnly: true },
                { label: 'Audit Log',     href: '/dashboard/audit',    icon: ShieldCheck, perm: 'audit.view' },
            ],
        },
    ]

    return all.map(group => ({
        ...group,
        items: group.items.filter(item => {
            if (item.adminOnly) return auth.isAdmin || (item.coordinatorOk === true && auth.isCoordinator)
            if (item.perm)      return auth.can(item.perm)
            return true
        }),
    })).filter(g => g.items.length > 0)
})

function isActive(item: NavItem) {
    const url = page.url.split('?')[0]
    if (item.exact || item.href === '/dashboard') return url === item.href
    return url.startsWith(item.href)
}

function logout() {
    disconnectEcho()    // close WebSocket before the session is destroyed
    router.post('/logout')
    sidebarOpen.value = false
}

function closeSidebar() { sidebarOpen.value = false }
</script>

<template>
    <Head>
        <title v-if="title">{{ title }}</title>
    </Head>

    <div class="h-screen flex flex-col overflow-hidden">

    <!-- ── Impersonation banner (super admin only) ──────────────────────────── -->
    <div
        v-if="page.props.impersonating"
        class="bg-amber-500 text-white text-sm flex items-center justify-center gap-4 py-2 px-4 shrink-0 z-50"
    >
        <span>⚠ Viewing as <strong>{{ page.props.impersonating }}</strong></span>
        <Link
            :href="route('super-admin.impersonate.stop')"
            method="delete"
            as="button"
            class="underline font-semibold hover:no-underline transition-all"
        >
            Stop Impersonating
        </Link>
    </div>

    <!-- ── Root shell ──────────────────────────────────────────────────────── -->
    <div class="flex flex-1 overflow-hidden bg-neutral-50">

        <!-- ── Mobile backdrop ──────────────────────────────────────────────── -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-30 bg-black/20 backdrop-blur-sm lg:hidden"
                @click="closeSidebar"
            />
        </Transition>

        <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
        <aside
            :class="[
                'fixed lg:relative inset-y-0 left-0 z-40 w-60 flex flex-col bg-white border-r border-neutral-100 transition-transform duration-300 lg:translate-x-0',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Logo / Church identity -->
            <div class="h-14 flex items-center gap-2.5 px-4 border-b border-neutral-100 shrink-0">
                <Link href="/" class="flex items-center gap-2.5 min-w-0">
                    <div class="w-7 h-7 rounded-lg gradient-brand flex items-center justify-center shrink-0">
                        <span class="text-white text-[11px] font-bold">{{ tenant.churchInitials }}</span>
                    </div>
                    <span class="text-sm font-semibold text-neutral-900 truncate">{{ tenant.churchName }}</span>
                </Link>
            </div>

            <!-- Nav groups -->
            <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-4">
                <div v-for="group in navGroups" :key="group.label">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 px-2.5 mb-1.5">
                        {{ group.label }}
                    </p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            :class="[
                                'flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-medium transition-all duration-100 group',
                                isActive(item)
                                    ? 'bg-brand-50 text-brand-700'
                                    : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900',
                            ]"
                            @click="closeSidebar"
                        >
                            <component
                                :is="item.icon"
                                :class="['w-4 h-4 shrink-0 transition-colors', isActive(item) ? 'text-brand-600' : 'text-neutral-400 group-hover:text-neutral-600']"
                            />
                            <span class="flex-1 truncate">{{ item.label }}</span>
                            <span
                                v-if="item.badge && item.badge() !== null"
                                class="ml-auto min-w-[18px] h-[18px] bg-brand-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1 shrink-0"
                            >
                                {{ (item.badge() ?? 0) > 9 ? '9+' : item.badge() }}
                            </span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- User section -->
            <div class="border-t border-neutral-100 p-2 shrink-0">
                <!-- User row + dropdown trigger -->
                <div ref="userMenuRef" class="relative">
                    <button
                        class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg hover:bg-neutral-50 transition-colors text-left"
                        @click="userMenuOpen = !userMenuOpen"
                    >
                        <AppAvatar :name="auth.user?.name" :src="auth.user?.avatar" size="sm" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-neutral-900 truncate">{{ auth.user?.name }}</p>
                            <p class="text-[10px] text-neutral-400 truncate capitalize">{{ auth.user?.roles[0] ?? 'member' }}</p>
                        </div>
                        <ChevronDown :class="['w-3.5 h-3.5 text-neutral-400 transition-transform duration-150', userMenuOpen && 'rotate-180']" />
                    </button>

                    <!-- User menu dropdown -->
                    <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="userMenuOpen"
                            class="absolute bottom-full left-0 right-0 mb-1 bg-white border border-neutral-100 rounded-xl shadow-xl overflow-hidden z-10"
                        >
                            <div class="px-3 py-2.5 border-b border-neutral-50">
                                <p class="text-xs font-medium text-neutral-900 truncate">{{ auth.user?.email }}</p>
                            </div>
                            <div class="p-1.5 space-y-0.5">
                                <!-- Profile page: accessible by every user -->
                                <Link href="/dashboard/profile" class="flex items-center gap-2 px-2.5 py-2 rounded-lg text-sm text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 transition-colors" @click="userMenuOpen = false">
                                    <UserCog class="w-3.5 h-3.5" />Profile Settings
                                </Link>
                                <!-- Church settings: admins only -->
                                <Link v-if="auth.isAdmin" href="/dashboard/settings/branding" class="flex items-center gap-2 px-2.5 py-2 rounded-lg text-sm text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 transition-colors" @click="userMenuOpen = false">
                                    <Settings class="w-3.5 h-3.5" />Administration Center
                                </Link>
                                <Link href="/" target="_blank" class="flex items-center gap-2 px-2.5 py-2 rounded-lg text-sm text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 transition-colors" @click="userMenuOpen = false">
                                    <ExternalLink class="w-3.5 h-3.5" />View Public Site
                                </Link>
                            </div>
                            <div class="p-1.5 border-t border-neutral-50">
                                <button class="w-full flex items-center gap-2 px-2.5 py-2 rounded-lg text-sm text-rose-600 hover:bg-rose-50 transition-colors" @click="logout">
                                    <LogOut class="w-3.5 h-3.5" />Sign out
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </aside>

        <!-- ── Main area ─────────────────────────────────────────────────────── -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            <!-- Top bar -->
            <header class="h-14 bg-white border-b border-neutral-100 flex items-center gap-3 px-4 lg:px-6 shrink-0">
                <!-- Mobile sidebar toggle -->
                <button
                    class="lg:hidden p-1.5 rounded-lg hover:bg-neutral-100 text-neutral-500"
                    @click="sidebarOpen = true"
                    aria-label="Open menu"
                >
                    <Menu class="w-5 h-5" />
                </button>

                <!-- Breadcrumbs or page title -->
                <div class="flex-1 min-w-0">
                    <nav v-if="breadcrumbs?.length" class="flex items-center gap-1 text-sm">
                        <template v-for="(crumb, i) in breadcrumbs" :key="i">
                            <Link v-if="crumb.href" :href="crumb.href" class="text-neutral-400 hover:text-neutral-700 transition-colors truncate max-w-[120px]">
                                {{ crumb.label }}
                            </Link>
                            <span v-else :class="['truncate', i === breadcrumbs.length - 1 ? 'text-neutral-700 font-medium' : 'text-neutral-400']">
                                {{ crumb.label }}
                            </span>
                            <ChevronRight v-if="i < breadcrumbs.length - 1" class="w-3.5 h-3.5 text-neutral-300 shrink-0" />
                        </template>
                    </nav>
                    <p v-else-if="title" class="text-sm font-medium text-neutral-700 truncate">{{ title }}</p>
                </div>

                <!-- Right cluster -->
                <div class="flex items-center gap-1.5 shrink-0">
                    <!-- Global search trigger -->
                    <button
                        type="button"
                        class="hidden sm:flex items-center gap-2 h-8 pl-3 pr-2 rounded-lg border border-neutral-200 bg-neutral-50 hover:bg-white hover:border-neutral-300 transition-all text-neutral-400 hover:text-neutral-600 text-xs"
                        @click="search.open()"
                        aria-label="Search (Ctrl+K)"
                    >
                        <Search class="w-3.5 h-3.5 shrink-0" />
                        <span class="hidden md:block">Search…</span>
                        <kbd class="hidden md:inline-flex items-center gap-0.5 ml-1 px-1.5 py-0.5 bg-white border border-neutral-200 rounded text-[10px] font-medium text-neutral-400">
                            <Command class="w-2.5 h-2.5" />K
                        </kbd>
                    </button>

                    <!-- Mobile search icon button -->
                    <button
                        type="button"
                        class="sm:hidden p-1.5 rounded-lg hover:bg-neutral-100 text-neutral-500 transition-colors"
                        @click="search.open()"
                        aria-label="Search"
                    >
                        <Search class="w-5 h-5" />
                    </button>

                    <!-- Notifications -->
                    <NotificationDropdown />

                    <!-- Avatar chip -->
                    <button
                        class="flex items-center gap-2 pl-1 pr-2.5 py-1 rounded-lg hover:bg-neutral-100 transition-colors"
                        @click="userMenuOpen = !userMenuOpen"
                    >
                        <AppAvatar :name="auth.user?.name" :src="auth.user?.avatar" size="xs" />
                        <span class="hidden sm:block text-xs font-medium text-neutral-700 truncate max-w-[100px]">
                            {{ auth.user?.name?.split(' ')[0] }}
                        </span>
                    </button>
                </div>
            </header>

            <!-- Scrollable page content -->
            <main class="flex-1 overflow-y-auto p-4 pb-20 lg:p-6 lg:pb-6">
                <slot />
            </main>
        </div>
    </div>

    </div>

    <!-- Global toast notifications -->
    <ToastContainer />

    <!-- ── Mobile bottom tab bar ─────────────────────────────────────────────── -->
    <nav aria-label="Bottom navigation" class="fixed bottom-0 inset-x-0 z-20 lg:hidden bg-white border-t border-neutral-100 flex items-center justify-around px-1 pb-safe">
        <!-- Home -->
        <Link
            href="/dashboard"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0"
            :class="page.url.split('?')[0] === '/dashboard' ? 'text-brand-600' : 'text-neutral-400'"
        >
            <LayoutDashboard class="w-5 h-5 shrink-0" />
            <span class="text-[10px] font-medium leading-none">Home</span>
        </Link>

        <!-- Events -->
        <Link
            v-if="auth.can('events.view')"
            href="/dashboard/events"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0"
            :class="page.url.startsWith('/dashboard/events') ? 'text-brand-600' : 'text-neutral-400'"
        >
            <CalendarDays class="w-5 h-5 shrink-0" />
            <span class="text-[10px] font-medium leading-none">Events</span>
        </Link>

        <!-- Tasks -->
        <Link
            v-if="auth.can('tasks.view_own')"
            href="/dashboard/tasks"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0"
            :class="page.url.startsWith('/dashboard/tasks') ? 'text-brand-600' : 'text-neutral-400'"
        >
            <div class="relative">
                <CheckSquare class="w-5 h-5 shrink-0" />
                <span
                    v-if="auth.overdueTasksCount > 0"
                    class="absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                >
                    {{ auth.overdueTasksCount > 9 ? '9+' : auth.overdueTasksCount }}
                </span>
            </div>
            <span class="text-[10px] font-medium leading-none">Tasks</span>
        </Link>

        <!-- Members -->
        <Link
            v-if="auth.can('members.view')"
            href="/dashboard/members"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0"
            :class="page.url.startsWith('/dashboard/members') ? 'text-brand-600' : 'text-neutral-400'"
        >
            <Users class="w-5 h-5 shrink-0" />
            <span class="text-[10px] font-medium leading-none">People</span>
        </Link>

        <!-- More (opens full sidebar) -->
        <button
            type="button"
            class="flex flex-col items-center justify-center gap-0.5 flex-1 py-2 min-w-0 text-neutral-400"
            @click="sidebarOpen = true"
        >
            <div class="relative">
                <Menu class="w-5 h-5 shrink-0" />
                <!-- Unread announcements aren't a bottom tab — surface the count here -->
                <span
                    v-if="auth.unreadAnnouncements > 0"
                    class="absolute -top-1 -right-1 min-w-[16px] h-4 bg-brand-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-0.5"
                    aria-label="Unread announcements"
                >
                    {{ auth.unreadAnnouncements > 9 ? '9+' : auth.unreadAnnouncements }}
                </span>
            </div>
            <span class="text-[10px] font-medium leading-none">More</span>
        </button>
    </nav>

    <!-- Global search modal (mounted once, open from anywhere via useSearchStore) -->
    <GlobalSearchModal />
</template>
