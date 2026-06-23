<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import TaskCard from '@/Components/Dashboard/TaskCard.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import type { Task } from '@/types'
import { Plus, Search, SlidersHorizontal, X, CheckSquare } from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

interface Dept { id: number; name: string; icon: string | null; color: string | null }

const props = defineProps<{
    tasks: {
        data: Task[]
        links: { url: string | null; label: string; active: boolean }[]
        current_page: number
        last_page: number
        total: number
        per_page: number
    }
    departments: Dept[]
    filters: {
        tab?: string
        search?: string
        department_id?: number | string
        priority?: string
    }
}>()

// ── Auth ───────────────────────────────────────────────────────────────────────

const auth = useAuthStore()

function canEditTask(task: Task): boolean {
    return auth.can('tasks.edit') || task.assigned_by === auth.user?.id || task.assigned_to === auth.user?.id
}

function canDeleteTask(_task: Task): boolean {
    return auth.can('tasks.delete')
}

// ── Tabs ───────────────────────────────────────────────────────────────────────

const tabs = [
    { key: 'all',         label: 'Active' },
    { key: 'mine',        label: 'My Tasks' },
    { key: 'assigned_by', label: 'Assigned by me' },
    { key: 'overdue',     label: 'Overdue' },
    { key: 'completed',   label: 'Completed' },
    { key: 'cancelled',   label: 'Cancelled' },
]

const currentTab = ref(props.filters.tab ?? 'all')

// ── Filters ────────────────────────────────────────────────────────────────────

const search     = ref(props.filters.search ?? '')
const deptFilter = ref(props.filters.department_id ?? '')
const priority   = ref(props.filters.priority ?? '')
const showFilters = ref(false)

let debounce: ReturnType<typeof setTimeout>

function applyFilters(extra: Record<string, string | number> = {}) {
    const params: Record<string, string | number> = {}
    if (currentTab.value && currentTab.value !== 'all') params.tab = currentTab.value
    if (search.value)     params.search = search.value
    if (deptFilter.value) params.department_id = deptFilter.value
    if (priority.value)   params.priority = priority.value

    router.get('/dashboard/tasks', { ...params, ...extra }, {
        preserveState: true,
        replace: true,
    })
}

watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(applyFilters, 280)
})

watch(currentTab, () => applyFilters())
watch(deptFilter, () => applyFilters())
watch(priority,   () => applyFilters())

function clearFilters() {
    search.value = ''
    deptFilter.value = ''
    priority.value = ''
    currentTab.value = 'all'
    router.get('/dashboard/tasks', {}, { preserveState: true, replace: true })
}

const hasActiveFilters = () =>
    !! (search.value || deptFilter.value || priority.value || (currentTab.value && currentTab.value !== 'all'))
</script>

<template>
    <DashboardLayout
        title="Tasks"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Tasks' },
        ]"
    >
        <PageHeader title="Tasks" description="Manage and track work across your church.">
            <template #actions>
                <AppButton href="/dashboard/tasks/create" size="sm">
                    <Plus class="w-4 h-4 mr-1.5" /> New Task
                </AppButton>
            </template>
        </PageHeader>

        <!-- ── Tabs ──────────────────────────────────────────────────────────── -->
        <div class="flex items-center justify-between gap-4 mb-4">
            <div class="flex gap-0.5 overflow-x-auto scrollbar-none">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="px-3.5 py-1.5 text-sm font-medium rounded-lg whitespace-nowrap transition-colors"
                    :class="currentTab === tab.key
                        ? 'bg-brand-600 text-white shadow-sm'
                        : 'text-neutral-600 hover:bg-neutral-100'"
                    @click="currentTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Filter toggle -->
            <button
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors"
                :class="showFilters
                    ? 'border-brand-400 bg-brand-50 text-brand-700'
                    : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'"
                @click="showFilters = !showFilters"
            >
                <SlidersHorizontal class="w-3.5 h-3.5" /> Filters
            </button>
        </div>

        <!-- ── Filter bar ─────────────────────────────────────────────────── -->
        <div v-if="showFilters" class="mb-4 p-4 bg-white border border-neutral-100 rounded-xl space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <!-- Search -->
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search tasks…"
                        class="w-full pl-9 pr-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                    />
                </div>

                <!-- Department -->
                <select
                    v-model="deptFilter"
                    class="px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 bg-white"
                >
                    <option value="">All departments</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">
                        {{ d.icon ?? '🏛' }} {{ d.name }}
                    </option>
                </select>

                <!-- Priority -->
                <select
                    v-model="priority"
                    class="px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 bg-white"
                >
                    <option value="">All priorities</option>
                    <option value="urgent">🔴 Urgent</option>
                    <option value="high">🟠 High</option>
                    <option value="medium">🔵 Medium</option>
                    <option value="low">⚪ Low</option>
                </select>
            </div>

            <button
                v-if="hasActiveFilters()"
                type="button"
                class="inline-flex items-center gap-1 text-xs text-neutral-500 hover:text-neutral-800 transition-colors"
                @click="clearFilters"
            >
                <X class="w-3 h-3" /> Clear all filters
            </button>
        </div>

        <!-- ── Task list ──────────────────────────────────────────────────── -->
        <div v-if="tasks.data.length" class="space-y-2">
            <TaskCard
                v-for="task in tasks.data"
                :key="task.id"
                :task="task"
                :canEdit="canEditTask(task)"
                :canDelete="canDeleteTask(task)"
            />
        </div>

        <!-- Empty state -->
        <EmptyState
            v-else
            :icon="CheckSquare"
            :title="hasActiveFilters() ? 'No tasks found' : 'No active tasks'"
            :description="hasActiveFilters() ? 'Try adjusting your filters.' : 'Create your first task to get started.'"
        >
            <template #action>
                <AppButton v-if="!hasActiveFilters()" href="/dashboard/tasks/create" size="sm">
                    <Plus class="w-4 h-4" /> New Task
                </AppButton>
            </template>
        </EmptyState>

        <AppPagination
            :links="tasks.links"
            :current-page="tasks.current_page"
            :last-page="tasks.last_page"
            :total="tasks.total"
            :per-page="tasks.per_page"
        />
    </DashboardLayout>
</template>
