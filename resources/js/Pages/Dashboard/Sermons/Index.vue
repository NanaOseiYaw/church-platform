<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    Mic2, Search, Play, Headphones, Clock, Star,
    ChevronRight, Youtube, Trash2, Plus, Layers,
} from 'lucide-vue-next'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import ChannelConnectionCard from '@/Components/Sermons/ChannelConnectionCard.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import type { DashboardSermon, ChannelConnection } from '@/types'
import axios from 'axios'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    sermons: {
        data:         DashboardSermon[]
        links:        any
        meta:         any
        current_page: number
        last_page:    number
        total:        number
    }
    series:      string[]
    connections: ChannelConnection[]
    filters:     { search?: string; series?: string; provider?: string; visibility?: string }
    canManage:   boolean
    canEdit:     boolean
    canDelete:   boolean
    canManageChannels: boolean
}>()

// ── Filters ────────────────────────────────────────────────────────────────────

const search       = ref(props.filters.search     ?? '')
const seriesFilter = ref(props.filters.series     ?? '')
const visibility   = ref(props.filters.visibility ?? '')

function applyFilters() {
    router.get('/dashboard/sermons', {
        search:     search.value      || undefined,
        series:     seriesFilter.value || undefined,
        visibility: visibility.value  || undefined,
    }, { preserveState: true, replace: true })
}

let debounce: ReturnType<typeof setTimeout>
watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(applyFilters, 320)
})
watch([seriesFilter, visibility], applyFilters)

// ── Helpers ────────────────────────────────────────────────────────────────────

function hasMedia(s: DashboardSermon): boolean {
    return !!(s.embed_url || s.video_url || s.audio_url)
}

// ── Actions ────────────────────────────────────────────────────────────────────

const notify  = useNotificationStore()
const loading = ref<Set<number>>(new Set())

async function toggleFeature(sermon: DashboardSermon) {
    loading.value.add(sermon.id)
    try {
        const { data } = await axios.patch(`/dashboard/sermons/${sermon.id}/feature`)
        sermon.is_featured = data.is_featured
        notify.success(data.message)
    } catch {
        notify.error('Could not update sermon.')
    } finally {
        loading.value.delete(sermon.id)
    }
}

async function cycleVisibility(sermon: DashboardSermon) {
    loading.value.add(sermon.id)
    try {
        const { data } = await axios.patch(`/dashboard/sermons/${sermon.id}/visibility`)
        sermon.visibility = data.visibility
    } catch {
        notify.error('Could not update visibility.')
    } finally {
        loading.value.delete(sermon.id)
    }
}

function confirmDelete(sermon: DashboardSermon) {
    if (!confirm(`Remove "${sermon.title}"?`)) return
    router.delete(`/dashboard/sermons/${sermon.id}`, {
        preserveScroll: true,
        onSuccess: () => notify.success('Sermon removed.'),
    })
}

// ── Visibility badge helpers ───────────────────────────────────────────────────

const visBadge: Record<string, { label: string; cls: string }> = {
    public:       { label: 'Public',       cls: 'bg-green-50 text-green-700' },
    members_only: { label: 'Members only', cls: 'bg-blue-50 text-blue-700' },
    unlisted:     { label: 'Unlisted',     cls: 'bg-neutral-100 text-neutral-500' },
}

function vBadge(s: DashboardSermon) {
    return visBadge[s.visibility ?? 'public'] ?? visBadge.public
}
</script>

<template>
    <DashboardLayout
        title="Sermons"
        :breadcrumbs="[{ label: 'Sermons' }]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">Sermon Archive</h1>
                <p class="text-sm text-neutral-500 mt-0.5">
                    {{ sermons.total }} sermon{{ sermons.total !== 1 ? 's' : '' }}
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <AppButton
                    v-if="canManage"
                    href="/dashboard/sermons/create"
                    variant="outline"
                    size="sm"
                >
                    <Plus class="w-4 h-4" />
                    Add Sermon
                </AppButton>
                <!--
                    The series page existed with no way to reach it, so the
                    Series dropdown on the sermon form could never be filled.
                    Gated on canEdit (sermons.edit) because that is what the
                    series page itself checks; canManage is sermons.upload and
                    would show the button to people the page then turns away.
                -->
                <AppButton
                    v-if="canEdit"
                    href="/dashboard/sermons/series"
                    variant="outline"
                    size="sm"
                >
                    <Layers class="w-4 h-4" />
                    Series
                </AppButton>
                <AppButton
                    v-if="canManageChannels"
                    href="/dashboard/sermons/channel"
                    variant="outline"
                    size="sm"
                >
                    <Youtube class="w-4 h-4 text-red-500" />
                    YouTube Channel
                </AppButton>
            </div>
        </div>

        <!-- Connected channels banner -->
        <div v-if="connections.length && canManageChannels" class="mb-5 space-y-2">
            <ChannelConnectionCard
                v-for="c in connections"
                :key="c.id"
                :connection="c"
                :can-manage="canManageChannels"
            />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <div class="relative flex-1 min-w-48 max-w-72">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search sermons…"
                    class="w-full h-9 pl-9 pr-3 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                />
            </div>

            <select
                v-if="series.length"
                v-model="seriesFilter"
                class="h-9 pl-3 pr-8 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
                <option value="">All series</option>
                <option v-for="s in series" :key="s" :value="s">{{ s }}</option>
            </select>

            <select
                v-if="canEdit"
                v-model="visibility"
                class="h-9 pl-3 pr-8 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
            >
                <option value="">All visibility</option>
                <option value="public">Public</option>
                <option value="members_only">Members only</option>
                <option value="unlisted">Unlisted</option>
                <option value="featured">Featured ★</option>
            </select>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="sermons.data.length === 0"
            :icon="Mic2"
            title="No sermons found"
            :description="(filters.search || filters.series || filters.visibility)
                ? 'Try adjusting your search or filter.'
                : canManageChannels
                    ? 'Connect your YouTube channel to sync sermons automatically.'
                    : 'No sermons have been uploaded yet.'"
        />

        <!-- Sermon grid -->
        <div
            v-else
            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6"
        >
            <div
                v-for="sermon in sermons.data"
                :key="sermon.id"
                class="group bg-white border border-neutral-100 rounded-2xl overflow-hidden hover:border-neutral-200 hover:shadow-sm transition-all"
            >
                <a :href="`/dashboard/sermons/${sermon.id}`" class="block">
                    <!-- Thumbnail -->
                    <div class="relative h-36 bg-gradient-to-br from-brand-50 to-brand-100 overflow-hidden">
                        <img
                            v-if="sermon.thumbnail"
                            :src="sermon.thumbnail"
                            :alt="sermon.title"
                            class="w-full h-full object-cover"
                        />
                        <div v-else class="w-full h-full flex items-center justify-center">
                            <div class="w-12 h-12 bg-white/40 rounded-2xl flex items-center justify-center">
                                <Mic2 class="w-6 h-6 text-brand-500" />
                            </div>
                        </div>

                        <!-- YouTube badge -->
                        <div v-if="sermon.provider === 'youtube'" class="absolute top-2 left-2">
                            <span class="inline-flex items-center gap-0.5 bg-red-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">
                                <Youtube class="w-2.5 h-2.5" /> YT
                            </span>
                        </div>

                        <!-- Featured star -->
                        <div v-if="sermon.is_featured" class="absolute top-2 right-2">
                            <span class="w-6 h-6 bg-amber-400 rounded-full flex items-center justify-center shadow-sm">
                                <Star class="w-3 h-3 text-white fill-white" />
                            </span>
                        </div>

                        <!-- Media indicator -->
                        <div v-if="hasMedia(sermon)" class="absolute bottom-2 right-2 flex items-center gap-1">
                            <span v-if="sermon.embed_url || sermon.video_url" class="w-6 h-6 bg-black/60 backdrop-blur-sm rounded-full flex items-center justify-center">
                                <Play class="w-3 h-3 text-white fill-white" />
                            </span>
                            <span v-else-if="sermon.audio_url" class="w-6 h-6 bg-black/60 backdrop-blur-sm rounded-full flex items-center justify-center">
                                <Headphones class="w-3 h-3 text-white" />
                            </span>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="p-4">
                        <div v-if="sermon.series" class="text-[11px] font-semibold uppercase tracking-wider text-brand-600 mb-1">
                            {{ sermon.series }}
                        </div>
                        <h3 class="text-sm font-semibold text-neutral-900 line-clamp-2 leading-snug mb-1 group-hover:text-brand-700 transition-colors">
                            {{ sermon.title }}
                        </h3>
                        <p class="text-xs text-neutral-500 mb-2">{{ sermon.speaker }}</p>

                        <div class="flex items-center justify-between text-xs text-neutral-400">
                            <div class="flex items-center gap-2">
                                <span v-if="sermon.preached_at_formatted">{{ sermon.preached_at_formatted }}</span>
                                <span v-if="sermon.duration" class="flex items-center gap-0.5">
                                    <Clock class="w-3 h-3" />{{ sermon.duration }}
                                </span>
                            </div>
                            <ChevronRight class="w-4 h-4 text-neutral-300 group-hover:text-brand-500 transition-all shrink-0" />
                        </div>
                    </div>
                </a>

                <!-- Admin action row -->
                <div
                    v-if="canEdit"
                    class="px-4 pb-3 flex items-center justify-between border-t border-neutral-50 pt-2.5"
                >
                    <!-- Visibility badge — click to cycle -->
                    <button
                        type="button"
                        :class="['text-[10px] font-semibold px-2 py-0.5 rounded-full transition-colors cursor-pointer', vBadge(sermon).cls]"
                        :disabled="loading.has(sermon.id)"
                        :title="`Click to cycle visibility`"
                        @click.stop="cycleVisibility(sermon)"
                    >
                        {{ vBadge(sermon).label }}
                    </button>

                    <div class="flex items-center gap-1">
                        <!-- Feature toggle -->
                        <button
                            type="button"
                            :title="sermon.is_featured ? 'Unfeature' : 'Feature on homepage'"
                            :class="[
                                'p-1.5 rounded-lg transition-colors',
                                sermon.is_featured
                                    ? 'text-amber-500 bg-amber-50'
                                    : 'text-neutral-300 hover:text-amber-500 hover:bg-amber-50',
                            ]"
                            :disabled="loading.has(sermon.id)"
                            @click.stop="toggleFeature(sermon)"
                        >
                            <Star class="w-3.5 h-3.5" :class="sermon.is_featured ? 'fill-current' : ''" />
                        </button>
                        <!-- Delete -->
                        <button
                            v-if="canDelete"
                            type="button"
                            title="Remove sermon"
                            class="p-1.5 rounded-lg text-neutral-300 hover:text-rose-500 hover:bg-rose-50 transition-colors"
                            @click.stop="confirmDelete(sermon)"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <AppPagination
            v-if="sermons.last_page > 1"
            :links="sermons.links"
            :current-page="sermons.current_page"
            :last-page="sermons.last_page"
        />
    </DashboardLayout>
</template>
