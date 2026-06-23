<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import {
    LayoutDashboard, Building2, Palette, Share2,
    Globe, Clock, Video, Heart, LayoutGrid,
    Mail, UserPlus, Layers, Bell,
    KeyRound, HardDrive, Lock, TrendingUp,
    Plug, ScrollText, SlidersHorizontal, Users,
} from 'lucide-vue-next'

// Each settings page passes its own key so the sidebar knows which item is active.
const props = defineProps<{ section: string }>()

interface NavItem {
    key: string
    label: string
    href: string
    icon: any
}
interface NavGroup {
    label: string
    items: NavItem[]
}

const groups: NavGroup[] = [
    {
        label: 'Administration',
        items: [
            { key: 'overview', label: 'Overview', href: '/dashboard/settings/overview', icon: LayoutDashboard },
        ],
    },
    {
        label: 'Church Identity',
        items: [
            { key: 'profile',  label: 'Church Profile', href: '/dashboard/settings/profile',  icon: Building2 },
            { key: 'branding', label: 'Branding',        href: '/dashboard/settings/branding', icon: Palette },
            { key: 'social',   label: 'Social Media',    href: '/dashboard/settings/social',   icon: Share2 },
        ],
    },
    {
        label: 'Church Life',
        items: [
            { key: 'website',       label: 'Public Website', href: '/dashboard/settings/website',       icon: Globe },
            { key: 'homepage',      label: 'Homepage',       href: '/dashboard/settings/homepage',      icon: LayoutGrid },
            { key: 'about-content', label: 'About Page',     href: '/dashboard/settings/about-content', icon: Users },
            { key: 'services',      label: 'Service Times',  href: '/dashboard/settings/services',      icon: Clock },
            { key: 'livestream',    label: 'Livestream',     href: '/dashboard/settings/livestream',    icon: Video },
            { key: 'donations',     label: 'Donations',      href: '/dashboard/settings/donations',     icon: Heart },
        ],
    },
    {
        label: 'Operations',
        items: [
            { key: 'communication', label: 'Communication', href: '/dashboard/settings/communication', icon: Mail },
            { key: 'membership',    label: 'Membership',    href: '/dashboard/settings/membership',    icon: UserPlus },
            { key: 'depts',         label: 'Departments',   href: '/dashboard/settings/depts',         icon: Layers },
            { key: 'notifications', label: 'Notifications', href: '/dashboard/settings/notifications', icon: Bell },
        ],
    },
    {
        label: 'Platform',
        items: [
            { key: 'roles',        label: 'Roles & Permissions', href: '/dashboard/settings/roles',        icon: KeyRound },
            { key: 'media',        label: 'Media Library',       href: '/dashboard/settings/media',        icon: HardDrive },
            { key: 'security',     label: 'Security',            href: '/dashboard/settings/security',     icon: Lock },
            { key: 'seo',          label: 'SEO & Analytics',     href: '/dashboard/settings/seo',          icon: TrendingUp },
            { key: 'integrations', label: 'Integrations',        href: '/dashboard/settings/integrations', icon: Plug },
            { key: 'audit',        label: 'Audit Logs',          href: '/dashboard/settings/audit',        icon: ScrollText },
            { key: 'advanced',     label: 'Advanced',            href: '/dashboard/settings/advanced',     icon: SlidersHorizontal },
        ],
    },
]

const flatItems   = computed(() => groups.flatMap(g => g.items))
const current     = computed(() => flatItems.value.find(i => i.key === props.section))
const breadcrumbs = computed(() => [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Settings',  href: '/dashboard/settings' },
    ...(current.value ? [{ label: current.value.label }] : []),
])
</script>

<template>
    <DashboardLayout
        :title="`Administration — ${current?.label ?? 'Settings'}`"
        :breadcrumbs="breadcrumbs"
    >
        <!-- ── Settings tab navigation — shows on all screen sizes ─────────────── -->
        <!-- Underline-style tabs with horizontal scroll for many items across groups -->
        <div class="overflow-x-auto scrollbar-none -mx-4 lg:-mx-6 px-4 lg:px-6 mb-6">
            <div class="flex border-b border-neutral-100 w-max min-w-full">
                <template v-for="(group, gi) in groups" :key="group.label">
                    <!-- Group separator (except before first group) -->
                    <div
                        v-if="gi > 0"
                        class="w-px bg-neutral-100 my-2 mx-1 shrink-0"
                    />
                    <Link
                        v-for="item in group.items"
                        :key="item.key"
                        :href="item.href"
                        :class="[
                            'flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition-all duration-150 shrink-0',
                            item.key === section
                                ? 'border-brand-600 text-brand-700'
                                : 'border-transparent text-neutral-500 hover:text-neutral-800 hover:border-neutral-200',
                        ]"
                    >
                        <component :is="item.icon" class="w-3.5 h-3.5 shrink-0" />
                        <span class="hidden sm:inline">{{ item.label }}</span>
                        <span class="sm:hidden">{{ item.label.split(' ')[0] }}</span>
                    </Link>
                </template>
            </div>
        </div>

        <!-- ── Page content ─────────────────────────────────────────────────────── -->
        <div>
            <slot />
        </div>
    </DashboardLayout>
</template>
