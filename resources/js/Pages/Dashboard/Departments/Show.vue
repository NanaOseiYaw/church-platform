<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useNotificationStore } from '@/stores/useNotificationStore'
import {
    deptColor, roleLabel, roleBadgeClass,
    visibilityLabel, visibilityIcon, visibilityBadgeClass,
} from '@/composables/useDepartment'
import AttachmentList from '@/Components/Media/AttachmentList.vue'
import type { Department, DepartmentMember, FileAttachment } from '@/types'
import {
    UserPlus, UserMinus, Users, Crown, ArrowLeft,
    LayoutGrid, Megaphone, CalendarDays, CheckSquare, Settings2,
    ChevronRight, Clock, AlertCircle, X, Paperclip,
} from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

interface AvailableMember {
    id: number
    name: string
    avatar: string | null
}

interface RecentAnnouncement {
    id: number
    title: string
    published_at: string | null
    published_at_formatted: string   // pre-formatted by DepartmentController
}

interface UpcomingEvent {
    id: number
    title: string
    start_at: string | null
    start_at_formatted: string       // pre-formatted by DepartmentController
    location: string | null
}

interface RecentTask {
    id: number
    title: string
    status: string
    priority: string
    due_at: string | null
    due_at_formatted: string | null  // pre-formatted by DepartmentController
}

interface RoleOption {
    value: string
    label: string
}

const props = defineProps<{
    department: Department
    members: DepartmentMember[]
    recentAnnouncements: RecentAnnouncement[]
    upcomingEvents: UpcomingEvent[]
    recentTasks: RecentTask[]
    availableMembers: AvailableMember[]
    roleOptions: RoleOption[]
    files: FileAttachment[]
    canEdit: boolean
    canManageMembers: boolean
    canUpdateRoles: boolean
    currentUserPivotRole: string | null
    currentUserId: number
    canUpload: boolean
}>()

const auth   = useAuthStore()
const toasts = useNotificationStore()

// ── Tabs ───────────────────────────────────────────────────────────────────────
type Tab = 'overview' | 'members' | 'announcements' | 'events' | 'tasks' | 'files'
const activeTab = ref<Tab>('overview')

const tabs: { id: Tab; label: string; icon: unknown; count?: number }[] = [
    { id: 'overview',      label: 'Overview',      icon: LayoutGrid },
    { id: 'members',       label: 'Members',        icon: Users,         count: props.members.length },
    { id: 'announcements', label: 'Announcements',  icon: Megaphone,     count: props.recentAnnouncements.length },
    { id: 'events',        label: 'Events',         icon: CalendarDays,  count: props.upcomingEvents.length },
    { id: 'tasks',         label: 'Tasks',          icon: CheckSquare,   count: props.recentTasks.length },
    { id: 'files',         label: 'Files',          icon: Paperclip,     count: props.files.length },
]

// ── Add-member panel ───────────────────────────────────────────────────────────
const showAddPanel = ref(false)

const addForm = useForm({
    user_id: null as number | null,
    role:    'member',
})

function submitAddMember() {
    addForm.post(`/dashboard/departments/${props.department.id}/members`, {
        onSuccess: () => {
            addForm.reset()
            showAddPanel.value = false
            toasts.success('Member added.')
        },
        onError: () => toasts.error('Could not add member.'),
    })
}

// ── Role update ────────────────────────────────────────────────────────────────
function updateRole(member: DepartmentMember, role: string) {
    router.patch(
        `/dashboard/departments/${props.department.id}/members/${member.id}`,
        { role },
        {
            preserveScroll: true,
            onSuccess: () => toasts.success(`${member.name}'s role updated.`),
            // Surface the exact server validation message (e.g. "last coordinator"
            // or "only admins can promote to coordinator") rather than a generic string.
            onError: (errors) => {
                const msg = Object.values(errors)[0]
                toasts.error(msg || 'Could not update role.')
            },
        },
    )
}

// ── Remove member ──────────────────────────────────────────────────────────────
function removeMember(member: DepartmentMember) {
    if (!confirm(`Remove ${member.name} from ${props.department.name}?`)) return
    router.delete(
        `/dashboard/departments/${props.department.id}/members/${member.id}`,
        {
            preserveScroll: true,
            onSuccess: () => toasts.success(`${member.name} removed.`),
            onError: (errors) => {
                const msg = Object.values(errors)[0]
                toasts.error(msg || 'Could not remove member.')
            },
        },
    )
}

// ── Governance helpers ─────────────────────────────────────────────────────────

/**
 * Whether the current user may see a remove button for the given member row.
 *
 * The server enforces the same rules — this is purely a UI concern to avoid
 * showing controls that would immediately fail.
 *
 * Rules:
 *  - Church admins: show for all rows (server guards last-coordinator).
 *  - Non-admins: hide for own row, hide based on hierarchy restrictions.
 *  - Coordinator: cannot remove coordinators.
 *  - Assistant coordinator: can only remove regular members.
 */
function canRemoveMember(member: DepartmentMember): boolean {
    // Admins see the button; the server enforces the last-coordinator rule.
    if (props.canEdit) return true

    // No self-removal.
    if (member.id === props.currentUserId) return false

    // Coordinators cannot remove other coordinators.
    if (props.currentUserPivotRole === 'coordinator' && member.pivot.role === 'coordinator') {
        return false
    }

    // Assistant coordinators can only remove regular members.
    if (props.currentUserPivotRole === 'assistant_coordinator' && member.pivot.role !== 'member') {
        return false
    }

    return true
}

// ── Helpers ────────────────────────────────────────────────────────────────────
// All dates are pre-formatted by DepartmentController — no new Date() needed.

function taskStatusClass(status: string): string {
    const map: Record<string, string> = {
        pending:     'bg-neutral-100 text-neutral-600',
        in_progress: 'bg-blue-100 text-blue-700',
        completed:   'bg-emerald-100 text-emerald-700',
        overdue:     'bg-rose-100 text-rose-600',
    }
    return map[status] ?? 'bg-neutral-100 text-neutral-500'
}

function taskPriorityClass(p: string): string {
    const map: Record<string, string> = {
        urgent: 'text-rose-500',
        high:   'text-amber-500',
        medium: 'text-blue-500',
        low:    'text-neutral-400',
    }
    return map[p] ?? 'text-neutral-400'
}

const color = computed(() => deptColor(props.department.color))
</script>

<template>
    <DashboardLayout
        :title="department.name"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Departments', href: '/dashboard/departments' },
            { label: department.name },
        ]"
    >
        <!-- ── Workspace header ─────────────────────────────────────────────── -->
        <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden mb-6">
            <!-- Color band -->
            <div class="h-2" :style="{ background: color }" />

            <div class="px-6 py-5 flex items-start justify-between gap-4 flex-wrap">
                <div class="flex items-center gap-4">
                    <div
                        class="w-14 h-14 rounded-xl flex items-center justify-center text-3xl shadow-sm shrink-0"
                        :style="{ background: color + '22', border: `2px solid ${color}44` }"
                    >
                        {{ department.icon ?? '🏛' }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-xl font-bold text-neutral-900">{{ department.name }}</h1>
                            <span :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium', visibilityBadgeClass(department.visibility)]">
                                {{ visibilityIcon(department.visibility) }} {{ visibilityLabel(department.visibility) }}
                            </span>
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', department.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-400']">
                                {{ department.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p v-if="department.description" class="text-sm text-neutral-500 mt-1 max-w-lg">
                            {{ department.description }}
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 shrink-0">
                    <AppButton href="/dashboard/departments" variant="outline" size="sm">
                        <ArrowLeft class="w-3.5 h-3.5" /> Back
                    </AppButton>
                    <AppButton
                        v-if="canEdit"
                        :href="`/dashboard/departments/${department.id}/edit`"
                        size="sm"
                    >
                        <Settings2 class="w-3.5 h-3.5" /> Settings
                    </AppButton>
                </div>
            </div>

            <!-- Stats chips -->
            <div class="px-6 pb-5 flex items-center gap-4 flex-wrap">
                <div v-if="department.coordinator" class="flex items-center gap-1.5 text-xs text-neutral-500">
                    <Crown class="w-3.5 h-3.5 text-amber-500" />
                    <span>{{ department.coordinator.name }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-neutral-500">
                    <Users class="w-3.5 h-3.5" />
                    <span>{{ members.length }} {{ members.length === 1 ? 'member' : 'members' }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-neutral-500">
                    <Megaphone class="w-3.5 h-3.5" />
                    <span>{{ recentAnnouncements.length }} announcements</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-neutral-500">
                    <CalendarDays class="w-3.5 h-3.5" />
                    <span>{{ upcomingEvents.length }} upcoming events</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-neutral-500">
                    <CheckSquare class="w-3.5 h-3.5" />
                    <span>{{ recentTasks.length }} active tasks</span>
                </div>
            </div>
        </div>

        <!-- ── Tab bar ──────────────────────────────────────────────────────── -->
        <div class="flex items-center gap-1 mb-6 border-b border-neutral-100 overflow-x-auto">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id"
                :class="[
                    'flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium whitespace-nowrap border-b-2 -mb-px transition-colors',
                    activeTab === tab.id
                        ? 'border-brand-600 text-brand-700'
                        : 'border-transparent text-neutral-500 hover:text-neutral-800 hover:border-neutral-200',
                ]"
            >
                <component :is="tab.icon" class="w-3.5 h-3.5" />
                {{ tab.label }}
                <span
                    v-if="tab.count !== undefined && tab.count > 0"
                    :class="[
                        'text-[10px] font-semibold px-1.5 py-0.5 rounded-full',
                        activeTab === tab.id ? 'bg-brand-100 text-brand-700' : 'bg-neutral-100 text-neutral-500',
                    ]"
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- ── TAB: Overview ─────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <!-- Recent announcements -->
            <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-50">
                    <div class="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                        <Megaphone class="w-4 h-4 text-brand-500" /> Announcements
                    </div>
                    <button @click="activeTab = 'announcements'" class="text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors flex items-center gap-0.5">
                        See all <ChevronRight class="w-3 h-3" />
                    </button>
                </div>
                <div v-if="recentAnnouncements.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No announcements yet.</p>
                </div>
                <ul v-else class="divide-y divide-neutral-50">
                    <li v-for="a in recentAnnouncements" :key="a.id" class="px-5 py-3">
                        <p class="text-sm text-neutral-800 font-medium truncate">{{ a.title }}</p>
                        <p class="text-[11px] text-neutral-400 flex items-center gap-1 mt-0.5">
                            <Clock class="w-3 h-3" /> {{ a.published_at_formatted }}
                        </p>
                    </li>
                </ul>
            </div>

            <!-- Upcoming events -->
            <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-50">
                    <div class="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                        <CalendarDays class="w-4 h-4 text-brand-500" /> Upcoming Events
                    </div>
                    <button @click="activeTab = 'events'" class="text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors flex items-center gap-0.5">
                        See all <ChevronRight class="w-3 h-3" />
                    </button>
                </div>
                <div v-if="upcomingEvents.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No upcoming events.</p>
                </div>
                <ul v-else class="divide-y divide-neutral-50">
                    <li v-for="e in upcomingEvents" :key="e.id" class="px-5 py-3">
                        <p class="text-sm text-neutral-800 font-medium truncate">{{ e.title }}</p>
                        <p class="text-[11px] text-neutral-400 flex items-center gap-1 mt-0.5">
                            <Clock class="w-3 h-3" /> {{ e.start_at_formatted }}
                            <span v-if="e.location" class="text-neutral-300">·</span>
                            <span v-if="e.location">{{ e.location }}</span>
                        </p>
                    </li>
                </ul>
            </div>

            <!-- Active tasks -->
            <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden lg:col-span-2">
                <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-50">
                    <div class="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                        <CheckSquare class="w-4 h-4 text-brand-500" /> Active Tasks
                    </div>
                    <button @click="activeTab = 'tasks'" class="text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors flex items-center gap-0.5">
                        See all <ChevronRight class="w-3 h-3" />
                    </button>
                </div>
                <div v-if="recentTasks.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No active tasks.</p>
                </div>
                <ul v-else class="divide-y divide-neutral-50">
                    <li
                        v-for="t in recentTasks"
                        :key="t.id"
                        class="px-5 py-3 flex items-center gap-3"
                    >
                        <AlertCircle :class="['w-3.5 h-3.5 shrink-0', taskPriorityClass(t.priority)]" />
                        <p class="text-sm text-neutral-800 font-medium flex-1 truncate">{{ t.title }}</p>
                        <span :class="['text-[10px] font-medium px-2 py-0.5 rounded-full', taskStatusClass(t.status)]">
                            {{ t.status.replace('_', ' ') }}
                        </span>
                        <span v-if="t.due_at_formatted" class="text-[11px] text-neutral-400 flex items-center gap-0.5 shrink-0">
                            <Clock class="w-3 h-3" /> {{ t.due_at_formatted }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── TAB: Members ──────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'members'" class="space-y-4">
            <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-neutral-50">
                    <h3 class="text-sm font-semibold text-neutral-900">
                        Members <span class="text-neutral-400 font-normal">({{ members.length }})</span>
                    </h3>
                    <AppButton
                        v-if="canManageMembers && availableMembers.length > 0"
                        size="sm"
                        @click="showAddPanel = !showAddPanel"
                    >
                        <UserPlus class="w-3.5 h-3.5" /> Add member
                    </AppButton>
                </div>

                <!-- Add member panel -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 -translate-y-1"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0 -translate-y-1"
                >
                    <div v-if="showAddPanel" class="px-5 py-4 bg-neutral-50 border-b border-neutral-100">
                        <form @submit.prevent="submitAddMember" class="flex items-end gap-3 flex-wrap">
                            <div class="flex-1 min-w-[200px]">
                                <label class="block text-xs font-medium text-neutral-600 mb-1">Member</label>
                                <select
                                    v-model="addForm.user_id"
                                    class="w-full px-3 py-2 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                                >
                                    <option :value="null">Select a member…</option>
                                    <option v-for="m in availableMembers" :key="m.id" :value="m.id">{{ m.name }}</option>
                                </select>
                                <p v-if="addForm.errors.user_id" class="mt-1 text-xs text-rose-500">{{ addForm.errors.user_id }}</p>
                            </div>
                            <div class="w-44">
                                <label class="block text-xs font-medium text-neutral-600 mb-1">Role</label>
                                <select
                                    v-model="addForm.role"
                                    class="w-full px-3 py-2 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                                >
                                    <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <AppButton
                                    type="submit"
                                    size="sm"
                                    :loading="addForm.processing"
                                    :disabled="!addForm.user_id"
                                >
                                    Add
                                </AppButton>
                                <button
                                    type="button"
                                    @click="showAddPanel = false; addForm.reset()"
                                    class="px-3 py-2 text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        </form>
                    </div>
                </Transition>

                <!-- Empty state -->
                <div v-if="members.length === 0" class="px-5 py-12 text-center">
                    <Users class="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                    <p class="text-sm text-neutral-500">No members yet.</p>
                </div>

                <!-- Member rows -->
                <ul v-else class="divide-y divide-neutral-50">
                    <li
                        v-for="member in members"
                        :key="member.id"
                        class="flex items-center gap-3 px-5 py-3.5 hover:bg-neutral-50/50 transition-colors"
                    >
                        <AppAvatar :name="member.name" :src="member.avatar" size="sm" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-neutral-900 truncate">{{ member.name }}</p>
                            <p class="text-xs text-neutral-400 truncate">{{ member.email }}</p>
                        </div>

                        <!-- Role badge / selector
                             Three rendering paths:
                             1. Admin (canEdit): unrestricted full selector for every row.
                             2. Coordinator (canUpdateRoles && !canEdit): restricted selector —
                                badge only for own row and other coordinator rows;
                                restricted selector (no coordinator option) for everyone else.
                             3. Everyone else (assistant_coordinator, member): read-only badge. -->
                        <template v-if="canUpdateRoles">
                            <!-- Path 1: Admin — full selector, all options, all rows -->
                            <select
                                v-if="canEdit"
                                :value="member.pivot.role"
                                @change="updateRole(member, ($event.target as HTMLSelectElement).value)"
                                :class="['text-[11px] font-medium px-2 py-1 rounded-full border-0 cursor-pointer focus:ring-2 focus:ring-brand-500/20 outline-none', roleBadgeClass(member.pivot.role)]"
                            >
                                <option v-for="r in roleOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>

                            <!-- Path 2: Coordinator -->
                            <template v-else>
                                <!-- Own row or another coordinator → badge only (no self/peer edit) -->
                                <span
                                    v-if="member.id === currentUserId || member.pivot.role === 'coordinator'"
                                    :class="['text-[11px] font-medium px-2 py-0.5 rounded-full', roleBadgeClass(member.pivot.role)]"
                                >
                                    {{ roleLabel(member.pivot.role) }}
                                </span>
                                <!-- Other rows → selector without coordinator option (no escalation) -->
                                <select
                                    v-else
                                    :value="member.pivot.role"
                                    @change="updateRole(member, ($event.target as HTMLSelectElement).value)"
                                    :class="['text-[11px] font-medium px-2 py-1 rounded-full border-0 cursor-pointer focus:ring-2 focus:ring-brand-500/20 outline-none', roleBadgeClass(member.pivot.role)]"
                                >
                                    <option
                                        v-for="r in roleOptions.filter(r => r.value !== 'coordinator')"
                                        :key="r.value"
                                        :value="r.value"
                                    >{{ r.label }}</option>
                                </select>
                            </template>
                        </template>

                        <!-- Path 3: No role-update authority → read-only badge -->
                        <span
                            v-else
                            :class="['text-[11px] font-medium px-2 py-0.5 rounded-full', roleBadgeClass(member.pivot.role)]"
                        >
                            {{ roleLabel(member.pivot.role) }}
                        </span>

                        <span class="text-[11px] text-neutral-400 shrink-0">
                            Since {{ member.pivot.joined_at_formatted ?? '—' }}
                        </span>

                        <!-- Remove button: hidden for self, hidden per hierarchy rules -->
                        <button
                            v-if="canManageMembers && canRemoveMember(member)"
                            class="p-1.5 rounded-lg hover:bg-rose-50 text-neutral-300 hover:text-rose-500 transition-colors shrink-0"
                            title="Remove member"
                            @click="removeMember(member)"
                        >
                            <UserMinus class="w-3.5 h-3.5" />
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── TAB: Announcements ────────────────────────────────────────────── -->
        <div v-if="activeTab === 'announcements'">
            <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-neutral-50">
                    <h3 class="text-sm font-semibold text-neutral-900">Announcements</h3>
                </div>
                <div v-if="recentAnnouncements.length === 0" class="px-5 py-12 text-center">
                    <Megaphone class="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                    <p class="text-sm text-neutral-500">No announcements for this department.</p>
                </div>
                <ul v-else class="divide-y divide-neutral-50">
                    <li v-for="a in recentAnnouncements" :key="a.id" class="px-5 py-4">
                        <p class="text-sm font-medium text-neutral-900">{{ a.title }}</p>
                        <p class="text-xs text-neutral-400 flex items-center gap-1 mt-1">
                            <Clock class="w-3 h-3" /> {{ a.published_at_formatted }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── TAB: Events ───────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'events'">
            <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-neutral-50">
                    <h3 class="text-sm font-semibold text-neutral-900">Upcoming Events</h3>
                </div>
                <div v-if="upcomingEvents.length === 0" class="px-5 py-12 text-center">
                    <CalendarDays class="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                    <p class="text-sm text-neutral-500">No upcoming events for this department.</p>
                </div>
                <ul v-else class="divide-y divide-neutral-50">
                    <li v-for="e in upcomingEvents" :key="e.id" class="px-5 py-4">
                        <p class="text-sm font-medium text-neutral-900">{{ e.title }}</p>
                        <div class="flex items-center gap-3 mt-1 text-xs text-neutral-400">
                            <span class="flex items-center gap-1"><Clock class="w-3 h-3" /> {{ e.start_at_formatted }}</span>
                            <span v-if="e.location">{{ e.location }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── TAB: Tasks ────────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'tasks'">
            <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-neutral-50">
                    <h3 class="text-sm font-semibold text-neutral-900">Active Tasks</h3>
                </div>
                <div v-if="recentTasks.length === 0" class="px-5 py-12 text-center">
                    <CheckSquare class="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                    <p class="text-sm text-neutral-500">No active tasks for this department.</p>
                </div>
                <ul v-else class="divide-y divide-neutral-50">
                    <li
                        v-for="t in recentTasks"
                        :key="t.id"
                        class="flex items-center gap-3 px-5 py-4"
                    >
                        <AlertCircle :class="['w-4 h-4 shrink-0', taskPriorityClass(t.priority)]" />
                        <p class="text-sm font-medium text-neutral-900 flex-1 truncate">{{ t.title }}</p>
                        <span :class="['text-[10px] font-medium px-2 py-0.5 rounded-full capitalize whitespace-nowrap', taskStatusClass(t.status)]">
                            {{ t.status.replace('_', ' ') }}
                        </span>
                        <span v-if="t.due_at_formatted" class="text-xs text-neutral-400 flex items-center gap-1 shrink-0">
                            <Clock class="w-3 h-3" /> {{ t.due_at_formatted }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- ── TAB: Files ────────────────────────────────────────────────────── -->
        <div v-if="activeTab === 'files'">
            <AttachmentList
                :files="files"
                attachable-type="department"
                :attachable-id="department.id"
                :can-upload="canUpload"
            />
        </div>
    </DashboardLayout>
</template>
