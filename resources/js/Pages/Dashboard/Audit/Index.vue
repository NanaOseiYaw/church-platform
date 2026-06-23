<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import { ShieldCheck, ChevronDown, ChevronUp } from 'lucide-vue-next'
import { useDebounceFn } from '@vueuse/core'

interface Actor { id: number; name: string; avatar: string | null }
interface Dept  { id: number; name: string }

interface LogEntry {
    id: number
    action: string
    model_type: string | null
    model_id: number | null
    target_name: string | null
    old_values: Record<string, unknown> | null
    new_values: Record<string, unknown> | null
    metadata: Record<string, unknown> | null
    ip_address: string | null
    created_at: string | null
    actor: Actor | null
}

interface PaginatedLogs {
    data: LogEntry[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
    total: number
}

const props = defineProps<{
    logs: PaginatedLogs
    filters: {
        module?: string
        action?: string
        user_id?: string
        department_id?: string
        date_from?: string
        date_to?: string
    }
    actors: Actor[]
    modules: string[]
    departments: Dept[]
    isAdmin: boolean
}>()

// ── Filter state ───────────────────────────────────────────────────────────────

const module      = ref(props.filters.module ?? '')
const action      = ref(props.filters.action ?? '')
const userId      = ref(props.filters.user_id ?? '')
const deptId      = ref(props.filters.department_id ?? '')
const dateFrom    = ref(props.filters.date_from ?? '')
const dateTo      = ref(props.filters.date_to ?? '')

function applyFilters() {
    router.get('/dashboard/audit', {
        module:        module.value || undefined,
        action:        action.value || undefined,
        user_id:       userId.value || undefined,
        department_id: deptId.value || undefined,
        date_from:     dateFrom.value || undefined,
        date_to:       dateTo.value || undefined,
    }, { preserveState: true, replace: true })
}

const debouncedApply = useDebounceFn(applyFilters, 300)

watch([module, action, userId, deptId, dateFrom, dateTo], () => debouncedApply())

function clearFilters() {
    module.value = ''
    action.value = ''
    userId.value = ''
    deptId.value = ''
    dateFrom.value = ''
    dateTo.value = ''
}

// ── Expand/collapse rows ───────────────────────────────────────────────────────

const expanded = ref<Set<number>>(new Set())

function toggleExpand(id: number) {
    if (expanded.value.has(id)) {
        expanded.value.delete(id)
    } else {
        expanded.value.add(id)
    }
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function formatAction(action: string): string {
    return action.split('.').map(p => p.replace(/_/g, ' ')).join(' › ')
}

function formatDate(iso: string | null): string {
    if (!iso) return '–'
    return new Date(iso).toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

function timeAgo(iso: string | null): string {
    if (!iso) return ''
    const diff = Date.now() - new Date(iso).getTime()
    const mins  = Math.floor(diff / 60000)
    if (mins < 1)  return 'just now'
    if (mins < 60) return `${mins}m ago`
    const hrs  = Math.floor(mins / 60)
    if (hrs  < 24) return `${hrs}h ago`
    return `${Math.floor(hrs / 24)}d ago`
}

function moduleColor(action: string): string {
    const prefix = action.split('.')[0]
    const colors: Record<string, string> = {
        auth:         'bg-brand-50 text-brand-700 border-brand-200',
        member:       'bg-blue-50 text-blue-700 border-blue-200',
        department:   'bg-brand-50 text-brand-700 border-brand-200',
        announcement: 'bg-amber-50 text-amber-700 border-amber-200',
        event:        'bg-green-50 text-green-700 border-green-200',
        task:         'bg-orange-50 text-orange-700 border-orange-200',
        attendance:   'bg-cyan-50 text-cyan-700 border-cyan-200',
        file:         'bg-slate-50 text-slate-700 border-slate-200',
        media:        'bg-slate-50 text-slate-700 border-slate-200',
        schedule:     'bg-brand-50 text-brand-700 border-brand-200',
        sermon:       'bg-rose-50 text-rose-700 border-rose-200',
        settings:     'bg-gray-50 text-gray-700 border-gray-200',
        notification: 'bg-teal-50 text-teal-700 border-teal-200',
        admin:        'bg-red-50 text-red-700 border-red-200',
        role:         'bg-pink-50 text-pink-700 border-pink-200',
    }
    return colors[prefix] ?? 'bg-gray-50 text-gray-700 border-gray-200'
}

function hasDetails(entry: LogEntry): boolean {
    return !!(entry.old_values || entry.new_values || entry.metadata)
}

const hasActiveFilters = computed(() =>
    module.value || action.value || userId.value || deptId.value || dateFrom.value || dateTo.value
)
</script>

<template>
    <DashboardLayout title="Audit Log">
        <PageHeader title="Audit Log">
            <template #subtitle>
                Complete activity trail for your church platform
            </template>
        </PageHeader>

        <!-- Filter bar -->
        <div class="mb-6 rounded-xl border border-neutral-100 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <!-- Module -->
                <select
                    v-model="module"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                >
                    <option value="">All modules</option>
                    <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                </select>

                <!-- Actor -->
                <select
                    v-if="isAdmin"
                    v-model="userId"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                >
                    <option value="">All actors</option>
                    <option v-for="actor in actors" :key="actor.id" :value="String(actor.id)">{{ actor.name }}</option>
                </select>

                <!-- Department -->
                <select
                    v-if="isAdmin"
                    v-model="deptId"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                >
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                </select>

                <!-- Date from -->
                <input
                    v-model="dateFrom"
                    type="date"
                    placeholder="From date"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                />

                <!-- Date to -->
                <input
                    v-model="dateTo"
                    type="date"
                    placeholder="To date"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                />

                <!-- Clear -->
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-500 hover:bg-gray-50"
                    @click="clearFilters"
                >
                    Clear filters
                </button>
            </div>
        </div>

        <!-- Stats pill -->
        <p class="mb-3 text-xs text-gray-500">
            {{ logs.total }} total entries
            <span v-if="hasActiveFilters"> matching current filters</span>
        </p>

        <!-- Empty state -->
        <div
            v-if="logs.data.length === 0"
            class="flex flex-col items-center justify-center rounded-xl border border-gray-200 bg-white p-12 text-center shadow-sm"
        >
            <ShieldCheck class="mb-3 h-8 w-8 text-gray-200" />
            <p class="text-sm font-medium text-gray-600">No audit entries found</p>
            <p class="mt-1 text-xs text-gray-400">Try adjusting your filters.</p>
        </div>

        <!-- Log table -->
        <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="divide-y divide-gray-50">
                <div
                    v-for="entry in logs.data"
                    :key="entry.id"
                    class="group"
                >
                    <!-- Main row -->
                    <div
                        class="flex items-start gap-3 px-5 py-3.5 hover:bg-gray-50/60 transition-colors cursor-default"
                        :class="{ 'cursor-pointer': hasDetails(entry) }"
                        @click="hasDetails(entry) && toggleExpand(entry.id)"
                    >
                        <!-- Module badge -->
                        <span
                            :class="['inline-flex shrink-0 items-center rounded-md border px-2 py-0.5 text-[10px] font-semibold', moduleColor(entry.action)]"
                        >
                            {{ entry.action.split('.').pop() }}
                        </span>

                        <!-- Details -->
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium capitalize text-gray-800">
                                {{ formatAction(entry.action) }}
                                <span v-if="entry.target_name" class="font-normal text-gray-500">
                                    — {{ entry.target_name }}
                                </span>
                            </p>
                            <p class="mt-0.5 text-[11px] text-gray-400">
                                {{ entry.actor?.name ?? 'System' }}
                                <span v-if="entry.ip_address" class="text-gray-300"> · {{ entry.ip_address }}</span>
                            </p>
                        </div>

                        <!-- Timestamp + expand -->
                        <div class="flex shrink-0 items-center gap-2">
                            <div class="text-right">
                                <p class="text-[11px] text-gray-500">{{ timeAgo(entry.created_at) }}</p>
                                <p class="text-[10px] text-gray-300">{{ formatDate(entry.created_at) }}</p>
                            </div>
                            <button
                                v-if="hasDetails(entry)"
                                type="button"
                                class="rounded p-0.5 text-gray-300 hover:text-gray-500"
                            >
                                <ChevronDown v-if="!expanded.has(entry.id)" class="h-3.5 w-3.5" />
                                <ChevronUp   v-else class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Expandable details -->
                    <div
                        v-if="expanded.has(entry.id) && hasDetails(entry)"
                        class="border-t border-gray-50 bg-gray-50/50 px-5 py-3 font-mono text-xs text-gray-600"
                    >
                        <div v-if="entry.old_values" class="mb-2">
                            <p class="mb-1 font-sans text-[10px] font-semibold uppercase tracking-wider text-gray-400">Before</p>
                            <pre class="whitespace-pre-wrap break-all text-[11px]">{{ JSON.stringify(entry.old_values, null, 2) }}</pre>
                        </div>
                        <div v-if="entry.new_values" class="mb-2">
                            <p class="mb-1 font-sans text-[10px] font-semibold uppercase tracking-wider text-gray-400">After</p>
                            <pre class="whitespace-pre-wrap break-all text-[11px]">{{ JSON.stringify(entry.new_values, null, 2) }}</pre>
                        </div>
                        <div v-if="entry.metadata">
                            <p class="mb-1 font-sans text-[10px] font-semibold uppercase tracking-wider text-gray-400">Context</p>
                            <pre class="whitespace-pre-wrap break-all text-[11px]">{{ JSON.stringify(entry.metadata, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <AppPagination v-if="logs.last_page > 1" :links="logs.links" class="mt-6" />
    </DashboardLayout>
</template>
