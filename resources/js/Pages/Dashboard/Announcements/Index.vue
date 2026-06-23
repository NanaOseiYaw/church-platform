<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import AnnouncementFeedCard from '@/Components/Dashboard/AnnouncementFeedCard.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import type { Announcement } from '@/types'
import { Megaphone, Plus, Search, Pin, Globe, Building2, FileText, User } from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

interface Dept { id: number; name: string; icon: string | null; color: string | null }

interface Paginated {
    data: Announcement[]
    current_page: number
    last_page: number
    total: number
    per_page: number
    links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
    announcements: Paginated
    departments:   Dept[]
    filters:       { filter?: string; search?: string }
    canCreate:     boolean
    canPin:        boolean
    canPublish:    boolean
}>()

const auth = useAuthStore()

// ── Filter tabs ────────────────────────────────────────────────────────────────

interface Tab { id: string; label: string; icon: unknown }

const tabs = computed<Tab[]>(() => {
    const base: Tab[] = [
        { id: '',           label: 'All',          icon: Megaphone },
        { id: 'church',     label: 'Church-wide',  icon: Globe },
        { id: 'department', label: 'Departments',  icon: Building2 },
        { id: 'pinned',     label: 'Pinned',       icon: Pin },
    ]
    if (props.canCreate) {
        base.push({ id: 'mine',   label: 'My Posts', icon: User })
        base.push({ id: 'drafts', label: 'Drafts',   icon: FileText })
    }
    return base
})

const activeFilter = ref(props.filters.filter ?? '')
const searchQuery  = ref(props.filters.search  ?? '')

let searchTimer: ReturnType<typeof setTimeout> | null = null

function setFilter(id: string) {
    activeFilter.value = id
    navigate()
}

function onSearch() {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(navigate, 280)
}

function navigate() {
    router.get('/dashboard/announcements', {
        filter: activeFilter.value || undefined,
        search: searchQuery.value  || undefined,
    }, { preserveState: true, replace: true })
}
</script>

<template>
    <DashboardLayout
        title="Announcements"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Announcements' }]"
    >
        <PageHeader title="Announcements" description="Church and department updates for your community.">
            <template #actions>
                <AppButton v-if="canCreate" href="/dashboard/announcements/create" size="sm">
                    <Plus class="w-4 h-4" /> New announcement
                </AppButton>
            </template>
        </PageHeader>

        <!-- Filter bar + search -->
        <div class="flex items-center gap-3 mb-5 flex-wrap">
            <!-- Tab pills -->
            <div class="flex items-center gap-1 bg-neutral-100 rounded-lg p-1 flex-wrap">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    @click="setFilter(tab.id)"
                    :class="[
                        'flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-colors whitespace-nowrap',
                        activeFilter === tab.id
                            ? 'bg-white text-neutral-900 shadow-sm'
                            : 'text-neutral-500 hover:text-neutral-700',
                    ]"
                >
                    <component :is="tab.icon" class="w-3.5 h-3.5" />
                    {{ tab.label }}
                </button>
            </div>

            <!-- Search -->
            <div class="relative ml-auto">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    @input="onSearch"
                    type="search"
                    placeholder="Search announcements…"
                    class="w-56 pl-9 pr-3 py-2 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 placeholder:text-neutral-400 transition"
                />
            </div>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="announcements.data.length === 0"
            :icon="Megaphone"
            title="No announcements"
            :description="filters.search
                ? `No results for &quot;${filters.search}&quot;.`
                : 'There are no announcements here yet.'"
        >
            <template #action>
                <AppButton v-if="canCreate && !filters.search" href="/dashboard/announcements/create" size="sm">
                    <Plus class="w-4 h-4" /> Post announcement
                </AppButton>
            </template>
        </EmptyState>

        <!-- Feed -->
        <div v-else class="space-y-3">
            <AnnouncementFeedCard
                v-for="a in announcements.data"
                :key="a.id"
                :announcement="a"
                :can-edit="auth.can('announcements.edit') || a.created_by === auth.user?.id"
                :can-delete="auth.can('announcements.delete')"
                :can-pin="canPin"
                :can-publish="canPublish && (auth.can('announcements.delete') || a.created_by === auth.user?.id)"
            />

            <AppPagination
                :links="announcements.links"
                :current-page="announcements.current_page"
                :last-page="announcements.last_page"
                :total="announcements.total"
                :per-page="announcements.per_page"
            />
        </div>
    </DashboardLayout>
</template>
