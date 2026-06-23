<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import EventCard from '@/Components/Dashboard/EventCard.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import type { Event, RsvpStatus } from '@/types'
import { CalendarDays, Plus, Search } from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

interface Dept { id: number; name: string; icon: string | null; color: string | null }

interface Paginated {
    data: (Event & { my_rsvp?: RsvpStatus | null })[]
    current_page: number
    last_page: number
    total: number
    per_page: number
    links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
    events:      Paginated
    departments: Dept[]
    filters:     { filter?: string; search?: string }
    canCreate:   boolean
}>()

const auth = useAuthStore()

// ── Filter tabs ────────────────────────────────────────────────────────────────

const tabs = [
    { id: '',          label: 'All' },
    { id: 'upcoming',  label: 'Upcoming' },
    { id: 'ongoing',   label: 'Live now' },
    { id: 'past',      label: 'Past' },
    { id: 'mine',      label: 'My Events' },
]

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
    router.get('/dashboard/events', {
        filter: activeFilter.value || undefined,
        search: searchQuery.value  || undefined,
    }, { preserveState: true, replace: true })
}
</script>

<template>
    <DashboardLayout
        title="Events"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Events' }]"
    >
        <PageHeader title="Events" description="Church and department events, schedules and activities.">
            <template #actions>
                <AppButton v-if="canCreate" href="/dashboard/events/create" size="sm">
                    <Plus class="w-4 h-4" /> New event
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
                    placeholder="Search events…"
                    class="w-56 pl-9 pr-3 py-2 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 placeholder:text-neutral-400 transition"
                />
            </div>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="events.data.length === 0"
            :icon="CalendarDays"
            title="No events"
            :description="filters.search
                ? `No results for &quot;${filters.search}&quot;.`
                : 'There are no events here yet.'"
        >
            <template #action>
                <AppButton v-if="canCreate && !filters.search" href="/dashboard/events/create" size="sm">
                    <Plus class="w-4 h-4" /> Create event
                </AppButton>
            </template>
        </EmptyState>

        <!-- Feed -->
        <div v-else class="space-y-3">
            <EventCard
                v-for="event in events.data"
                :key="event.id"
                :event="event"
                :my-rsvp="event.my_rsvp"
                :can-edit="auth.can('events.edit') || event.created_by === auth.user?.id"
                :can-delete="auth.can('events.delete')"
                :can-rsvp="auth.can('events.rsvp')"
            />

            <AppPagination
                :links="events.links"
                :current-page="events.current_page"
                :last-page="events.last_page"
                :total="events.total"
                :per-page="events.per_page"
            />
        </div>
    </DashboardLayout>
</template>
