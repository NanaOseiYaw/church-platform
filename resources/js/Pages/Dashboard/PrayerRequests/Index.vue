<script setup lang="ts">
import { ref } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import { Heart, CheckCircle2, Trash2, Lock, EyeOff, ChevronDown } from 'lucide-vue-next'

interface PrayerRequestItem {
    id: number
    display_name: string
    email: string | null
    request: string
    is_anonymous: boolean
    is_private: boolean
    is_answered: boolean
    answered_at: string | null
    admin_notes: string | null
    created_at: string
}

interface PaginatedRequests {
    data: PrayerRequestItem[]
    current_page: number
    last_page: number
    total: number
    per_page: number
    links: { url: string | null; label: string; active: boolean }[]
}

interface Stats {
    total: number
    unanswered: number
    answered: number
}

const props = defineProps<{
    requests: PaginatedRequests
    stats: Stats
    filters: { filter?: string }
}>()

const expanded = ref<number | null>(null)
const notesInput = ref<Record<number, string>>({})

function toggle(id: number) {
    expanded.value = expanded.value === id ? null : id
}

function markAnswered(item: PrayerRequestItem) {
    router.patch(`/dashboard/prayer-requests/${item.id}/answer`, {
        admin_notes: notesInput.value[item.id] ?? item.admin_notes ?? '',
    }, { preserveScroll: true })
}

function deleteRequest(item: PrayerRequestItem) {
    if (! confirm('Delete this prayer request?')) return
    router.delete(`/dashboard/prayer-requests/${item.id}`, { preserveScroll: true })
}

const FILTER_TABS = [
    { value: '',           label: 'All' },
    { value: 'unanswered', label: 'Unanswered' },
    { value: 'answered',   label: 'Answered' },
    { value: 'private',    label: 'Private' },
]
</script>

<template>
    <DashboardLayout
        title="Prayer Requests"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Prayer Requests' }]"
    >
        <PageHeader
            title="Prayer Requests"
            description="Submitted prayer needs from your church community."
        />

        <!-- Stats strip -->
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1.5 mb-5 text-sm text-neutral-500">
            <span><span class="font-semibold text-neutral-800">{{ stats.total }}</span> total</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-amber-700">{{ stats.unanswered }}</span> unanswered</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-emerald-700">{{ stats.answered }}</span> answered</span>
        </div>

        <!-- Filter tabs -->
        <div class="flex gap-1 border-b border-neutral-100 mb-5">
            <Link
                v-for="tab in FILTER_TABS"
                :key="tab.value"
                :href="`/dashboard/prayer-requests${tab.value ? '?filter=' + tab.value : ''}`"
                class="px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 -mb-px transition-colors"
                :class="(filters.filter ?? '') === tab.value
                    ? 'border-brand-600 text-brand-600'
                    : 'border-transparent text-neutral-500 hover:text-neutral-800'"
            >
                {{ tab.label }}
            </Link>
        </div>

        <!-- Empty -->
        <div v-if="requests.data.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-12 h-12 bg-neutral-100 rounded-xl flex items-center justify-center mb-3">
                <Heart class="w-6 h-6 text-neutral-300" />
            </div>
            <p class="text-sm font-medium text-neutral-600">No requests yet</p>
            <p class="text-xs text-neutral-400 mt-0.5">Prayer requests submitted from your website will appear here.</p>
        </div>

        <!-- List -->
        <div v-else class="space-y-3">
            <div
                v-for="item in requests.data"
                :key="item.id"
                class="bg-white border border-neutral-100 rounded-xl overflow-hidden"
            >
                <!-- Header row -->
                <div
                    class="flex items-start gap-3 px-5 py-4 cursor-pointer hover:bg-neutral-50/50 transition-colors"
                    @click="toggle(item.id)"
                >
                    <!-- Status icon -->
                    <div
                        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                        :class="item.is_answered ? 'bg-emerald-50' : 'bg-amber-50'"
                    >
                        <CheckCircle2 v-if="item.is_answered" class="w-4 h-4 text-emerald-600" />
                        <Heart v-else class="w-4 h-4 text-amber-600" />
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-neutral-900">{{ item.display_name }}</span>
                            <span v-if="item.is_anonymous" class="inline-flex items-center gap-1 text-[10px] text-neutral-400">
                                <EyeOff class="w-3 h-3" /> Anonymous
                            </span>
                            <span v-if="item.is_private" class="inline-flex items-center gap-1 text-[10px] text-neutral-400">
                                <Lock class="w-3 h-3" /> Private
                            </span>
                            <span
                                v-if="item.is_answered"
                                class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700"
                            >Answered</span>
                        </div>
                        <p class="text-xs text-neutral-500 mt-0.5">{{ item.created_at }}</p>
                        <p class="text-sm text-neutral-700 mt-1 line-clamp-2">{{ item.request }}</p>
                    </div>

                    <ChevronDown
                        class="w-4 h-4 text-neutral-400 shrink-0 mt-1 transition-transform duration-200"
                        :class="{ 'rotate-180': expanded === item.id }"
                    />
                </div>

                <!-- Expanded detail -->
                <div v-if="expanded === item.id" class="border-t border-neutral-50 px-5 py-4 space-y-4 bg-neutral-50/50">
                    <!-- Full request text -->
                    <p class="text-sm text-neutral-700 whitespace-pre-wrap leading-relaxed">{{ item.request }}</p>

                    <!-- Admin notes -->
                    <div>
                        <label class="block text-xs font-semibold text-neutral-500 uppercase tracking-wide mb-1.5">Admin Notes (private)</label>
                        <textarea
                            v-model="notesInput[item.id]"
                            :placeholder="item.admin_notes ?? 'Add notes for your records…'"
                            rows="2"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition resize-none bg-white"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-2">
                        <button
                            v-if="!item.is_answered"
                            type="button"
                            @click="markAnswered(item)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition-colors"
                        >
                            <CheckCircle2 class="w-3.5 h-3.5" />
                            Mark as Answered
                        </button>
                        <button
                            type="button"
                            @click="deleteRequest(item)"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-200 text-red-600 hover:bg-red-50 text-xs font-semibold rounded-lg transition-colors"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <AppPagination
            :links="requests.links"
            :current-page="requests.current_page"
            :last-page="requests.last_page"
            :total="requests.total"
            :per-page="requests.per_page"
        />
    </DashboardLayout>
</template>
