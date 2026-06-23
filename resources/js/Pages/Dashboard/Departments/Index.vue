<script setup lang="ts">
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppButton from '@/Components/UI/AppButton.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { deptColor, visibilityBadgeClass, visibilityLabel, visibilityIcon } from '@/composables/useDepartment'
import type { Department } from '@/types'
import {
    Building2, Plus, Users, ChevronRight, MoreHorizontal, Edit2, Trash2,
    Search, Megaphone, CalendarDays, CheckSquare, Lock, Globe, UserCheck,
} from 'lucide-vue-next'

interface PaginatedDepartments {
    data: Department[]
    current_page: number
    last_page: number
    total: number
    per_page: number
    links: { url: string | null; label: string; active: boolean }[]
}

const props = defineProps<{
    departments: PaginatedDepartments
    filters: { search?: string }
}>()

const auth    = useAuthStore()
const toasts  = useNotificationStore()
const openMenu = ref<number | null>(null)

// ── Search ─────────────────────────────────────────────────────────────────────
const searchQuery = ref(props.filters.search ?? '')
let searchTimer: ReturnType<typeof setTimeout> | null = null

function onSearch() {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get('/dashboard/departments', { search: searchQuery.value || undefined }, {
            preserveState: true,
            replace: true,
        })
    }, 300)
}

// ── Context menu ───────────────────────────────────────────────────────────────
function toggleMenu(id: number) {
    openMenu.value = openMenu.value === id ? null : id
}

function confirmDelete(dept: Department) {
    openMenu.value = null
    if (!confirm(`Delete "${dept.name}"? This cannot be undone.`)) return
    router.delete(`/dashboard/departments/${dept.id}`, {
        onSuccess: () => toasts.success(`"${dept.name}" deleted.`),
        onError:   () => toasts.error('Failed to delete department.'),
    })
}

// ── Close menus on outside click ───────────────────────────────────────────────
function closeMenus() {
    openMenu.value = null
}
</script>

<template>
    <DashboardLayout
        title="Departments"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Departments' }]"
    >
        <PageHeader title="Workspaces" description="Your church's ministry departments and teams.">
            <template #actions>
                <AppButton v-if="auth.can('departments.create')" href="/dashboard/departments/create" size="sm">
                    <Plus class="w-4 h-4" /> New workspace
                </AppButton>
            </template>
        </PageHeader>

        <!-- Search bar -->
        <div class="mb-5">
            <div class="relative max-w-sm">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                <input
                    v-model="searchQuery"
                    @input="onSearch"
                    type="search"
                    placeholder="Search workspaces…"
                    class="w-full pl-9 pr-3 py-2 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 placeholder:text-neutral-400 transition"
                />
            </div>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="departments.data.length === 0"
            :icon="Building2"
            title="No workspaces found"
            :description="filters.search ? `No departments match &quot;${filters.search}&quot;.` : 'Create your first ministry workspace to organise your church.'"
        >
            <template #action>
                <AppButton v-if="auth.can('departments.create') && !filters.search" href="/dashboard/departments/create" size="sm">
                    <Plus class="w-4 h-4" /> Create workspace
                </AppButton>
            </template>
        </EmptyState>

        <!-- Workspace grid -->
        <div v-else class="space-y-5" @click="closeMenus">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <div
                    v-for="dept in departments.data"
                    :key="dept.id"
                    class="group bg-white border border-neutral-100 rounded-xl overflow-hidden hover:shadow-md hover:border-neutral-200 transition-all duration-150"
                >
                    <!-- Color band -->
                    <div
                        class="h-1.5"
                        :style="{ background: deptColor(dept.color) }"
                    />

                    <div class="p-5 flex flex-col gap-4">
                        <!-- Header row -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-3 min-w-0">
                                <!-- Icon swatch -->
                                <div
                                    class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-xl shadow-sm"
                                    :style="{ background: deptColor(dept.color) + '22', border: `1.5px solid ${deptColor(dept.color)}44` }"
                                >
                                    {{ dept.icon ?? '🏛' }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-neutral-900 truncate leading-tight">{{ dept.name }}</p>
                                    <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                        <!-- Visibility badge -->
                                        <span :class="['inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-medium', visibilityBadgeClass(dept.visibility)]">
                                            {{ visibilityIcon(dept.visibility) }} {{ visibilityLabel(dept.visibility) }}
                                        </span>
                                        <!-- Active badge -->
                                        <span :class="['inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium', dept.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-400']">
                                            {{ dept.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Context menu -->
                            <div v-if="auth.can('departments.edit') || auth.can('departments.delete')" class="relative shrink-0" @click.stop>
                                <button
                                    class="p-1.5 rounded-lg hover:bg-neutral-100 text-neutral-300 group-hover:text-neutral-400 hover:text-neutral-600 transition-colors"
                                    @click="toggleMenu(dept.id)"
                                >
                                    <MoreHorizontal class="w-4 h-4" />
                                </button>
                                <Transition
                                    enter-active-class="transition duration-100 ease-out"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition duration-75 ease-in"
                                    leave-from-class="opacity-100"
                                    leave-to-class="opacity-0 scale-95"
                                >
                                    <div
                                        v-if="openMenu === dept.id"
                                        class="absolute right-0 top-8 w-36 bg-white border border-neutral-100 rounded-xl shadow-xl z-10 overflow-hidden p-1"
                                    >
                                        <Link
                                            v-if="auth.can('departments.edit')"
                                            :href="`/dashboard/departments/${dept.id}/edit`"
                                            class="flex items-center gap-2 px-2.5 py-2 text-sm text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 rounded-lg transition-colors"
                                        >
                                            <Edit2 class="w-3.5 h-3.5" /> Edit
                                        </Link>
                                        <button
                                            v-if="auth.can('departments.delete')"
                                            class="w-full flex items-center gap-2 px-2.5 py-2 text-sm text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                            @click="confirmDelete(dept)"
                                        >
                                            <Trash2 class="w-3.5 h-3.5" /> Delete
                                        </button>
                                    </div>
                                </Transition>
                            </div>
                        </div>

                        <!-- Description -->
                        <p v-if="dept.description" class="text-xs text-neutral-500 line-clamp-2 -mt-1">
                            {{ dept.description }}
                        </p>

                        <!-- Stats row -->
                        <div class="flex items-center gap-3 text-[11px] text-neutral-400">
                            <span class="flex items-center gap-1">
                                <Users class="w-3.5 h-3.5" />
                                {{ dept.members_count ?? 0 }}
                            </span>
                            <span class="flex items-center gap-1">
                                <Megaphone class="w-3.5 h-3.5" />
                                {{ dept.announcements_count ?? 0 }}
                            </span>
                            <span class="flex items-center gap-1">
                                <CalendarDays class="w-3.5 h-3.5" />
                                {{ dept.events_count ?? 0 }}
                            </span>
                            <span class="flex items-center gap-1">
                                <CheckSquare class="w-3.5 h-3.5" />
                                {{ dept.tasks_count ?? 0 }}
                            </span>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-between pt-2 border-t border-neutral-50">
                            <div v-if="dept.coordinator" class="flex items-center gap-1.5 text-xs text-neutral-400">
                                <UserCheck class="w-3.5 h-3.5" />
                                <span class="truncate max-w-[100px]">{{ dept.coordinator.name }}</span>
                            </div>
                            <div v-else class="text-xs text-neutral-300">No coordinator</div>

                            <Link
                                :href="`/dashboard/departments/${dept.id}`"
                                class="flex items-center gap-0.5 text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors"
                            >
                                Open <ChevronRight class="w-3.5 h-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <AppPagination
                :links="departments.links"
                :current-page="departments.current_page"
                :last-page="departments.last_page"
                :total="departments.total"
                :per-page="departments.per_page"
            />
        </div>
    </DashboardLayout>
</template>
