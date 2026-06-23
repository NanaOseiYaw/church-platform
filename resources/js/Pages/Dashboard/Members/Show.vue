<script setup lang="ts">
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import MemberProfileForm from '@/Components/Members/MemberProfileForm.vue'
import MemberTimeline from '@/Components/Members/MemberTimeline.vue'
import {
    ArrowLeft, Mail, Phone, CalendarDays, Crown,
    Building2, MapPin, Heart, User, AlertCircle,
    CheckCircle2, Clock, Pencil, ShieldCheck, UserX, UserCheck,
} from 'lucide-vue-next'

// ── Types ──────────────────────────────────────────────────────────────────────

interface Role { name: string }

interface Department {
    id: number
    name: string
    icon: string | null
    color: string | null
    is_active: boolean
    members_count: number
    coordinator: { id: number; name: string } | null
    pivot?: { role: string; joined_at: string | null; joined_at_formatted: string | null }
}

interface ProfileData {
    date_of_birth: string | null
    gender: string | null
    marital_status: string | null
    address: string | null
    emergency_contact_name: string | null
    emergency_contact_relationship: string | null
    emergency_contact_phone: string | null
    membership_date?: string | null
    baptism_date?: string | null
    salvation_date?: string | null
}

interface Member {
    id: number
    name: string
    email: string
    avatar: string | null
    is_active: boolean
    phone: string | null
    timezone: string | null
    created_at: string | null
    created_at_formatted: string
    roles: Role[]
    departments: Department[]
    profile: ProfileData | null
}

interface AttendanceEntry {
    id: string
    status: string
    checked_in_at: string | null
    created_at_formatted: string
    session: {
        id: number
        title: string
        type: string
        type_label: string
        scheduled_at: string | null
        scheduled_at_formatted: string
        scheduled_date_short: string
        status: string
    } | null
}

interface TaskEntry {
    id: number
    title: string
    priority: string
    status: string
    due_at: string | null
    due_at_short: string | null
    is_overdue: boolean
    completed_at: string | null
}

interface TimelineEntry {
    type: 'attendance' | 'task_assigned' | 'task_completed' | 'dept_joined' | 'dept_left' | 'role_changed'
    label: string
    meta: string | null
    timestamp: string
    formatted: string
}

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    member: Member
    recentAttendances: AttendanceEntry[]
    recentTasks: TaskEntry[]
    timeline: TimelineEntry[]
    canEdit: boolean
    canEditAdminFields: boolean
    canAssignRole: boolean
    canToggleActive: boolean
}>()

// ── State ──────────────────────────────────────────────────────────────────────

const activeTab = ref<'departments' | 'attendance' | 'tasks' | 'timeline'>('departments')
const editingProfile = ref(false)

// ── Role assignment ────────────────────────────────────────────────────────────

const roleForm = useForm({
    role: props.member.roles[0]?.name ?? 'member',
})

function saveRole() {
    roleForm.patch(`/dashboard/members/${props.member.id}/role`, {
        preserveScroll: true,
        onSuccess: () => roleForm.reset(),
    })
}

// ── Active / deactivate toggle ─────────────────────────────────────────────────

const toggleActiveForm = useForm({})

function toggleActive() {
    toggleActiveForm.patch(`/dashboard/members/${props.member.id}/activate`, {
        preserveScroll: true,
    })
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function primaryRole(member: Member): string {
    return member.roles[0]?.name.replace(/_/g, ' ') ?? 'member'
}

const roleColorMap: Record<string, string> = {
    super_admin:  'bg-brand-100 text-brand-800',
    church_admin: 'bg-brand-100 text-brand-700',
    coordinator:  'bg-blue-100 text-blue-700',
    member:       'bg-neutral-100 text-neutral-600',
}

function roleColor(name: string): string {
    return roleColorMap[name] ?? roleColorMap['member']
}

const statusColor: Record<string, string> = {
    present: 'bg-emerald-50 text-emerald-700',
    late:    'bg-amber-50 text-amber-700',
    absent:  'bg-rose-50 text-rose-700',
    excused: 'bg-neutral-100 text-neutral-500',
}

const priorityColor: Record<string, string> = {
    urgent: 'bg-rose-50 text-rose-700',
    high:   'bg-orange-50 text-orange-700',
    medium: 'bg-amber-50 text-amber-700',
    low:    'bg-neutral-100 text-neutral-500',
}

const tabs: { key: 'departments' | 'attendance' | 'tasks' | 'timeline'; label: string; count?: number }[] = [
    { key: 'departments', label: 'Departments', count: props.member.departments.length },
    { key: 'attendance',  label: 'Attendance',  count: props.recentAttendances.length  },
    { key: 'tasks',       label: 'Tasks',       count: props.recentTasks.length        },
    { key: 'timeline',    label: 'Timeline',    count: props.timeline.length           },
]
</script>

<template>
    <DashboardLayout
        :title="member.name"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Members',   href: '/dashboard/members' },
            { label: member.name },
        ]"
    >
        <PageHeader :title="member.name" description="Member profile and activity.">
            <template #actions>
                <AppButton href="/dashboard/members" variant="outline" size="sm">
                    <ArrowLeft class="w-4 h-4" /> Back
                </AppButton>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <!-- ── SIDEBAR ──────────────────────────────────────────────────── -->
            <div class="lg:col-span-1 space-y-4">

                <!-- Profile card -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5 space-y-4">

                    <!-- Avatar + name + role -->
                    <div class="flex flex-col items-center text-center gap-2">
                        <AppAvatar :name="member.name" :src="member.avatar" size="lg" />
                        <div>
                            <p class="text-base font-semibold text-neutral-900">{{ member.name }}</p>
                            <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-1.5 capitalize', roleColor(member.roles[0]?.name ?? 'member')]">
                                {{ primaryRole(member) }}
                            </span>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="space-y-2.5 pt-3 border-t border-neutral-50">
                        <div class="flex items-center gap-2.5 text-sm text-neutral-600">
                            <Mail class="w-4 h-4 text-neutral-400 shrink-0" />
                            <a :href="`mailto:${member.email}`" class="truncate hover:text-brand-600 transition-colors">{{ member.email }}</a>
                        </div>
                        <div v-if="member.phone" class="flex items-center gap-2.5 text-sm text-neutral-600">
                            <Phone class="w-4 h-4 text-neutral-400 shrink-0" />
                            <span>{{ member.phone }}</span>
                        </div>
                        <div class="flex items-center gap-2.5 text-sm text-neutral-500">
                            <CalendarDays class="w-4 h-4 text-neutral-400 shrink-0" />
                            <span>Joined {{ member.created_at_formatted }}</span>
                        </div>
                    </div>

                    <!-- CRM profile fields (read-only) -->
                    <template v-if="member.profile && !editingProfile">
                        <div class="pt-3 border-t border-neutral-50 space-y-2">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400">Profile</p>

                            <div v-if="member.profile.date_of_birth" class="flex items-start gap-2 text-sm text-neutral-600">
                                <User class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                <span>{{ new Date(member.profile.date_of_birth).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) }}</span>
                            </div>
                            <div v-if="member.profile.marital_status" class="flex items-start gap-2 text-sm text-neutral-600 capitalize">
                                <Heart class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                <span>{{ member.profile.marital_status }}</span>
                            </div>
                            <div v-if="member.profile.address" class="flex items-start gap-2 text-sm text-neutral-600">
                                <MapPin class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                <span class="whitespace-pre-line">{{ member.profile.address }}</span>
                            </div>

                            <template v-if="member.profile.membership_date || member.profile.baptism_date || member.profile.salvation_date">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 pt-2">Church journey</p>
                                <div v-if="member.profile.membership_date" class="text-xs text-neutral-500">
                                    Member since {{ member.profile.membership_date }}
                                </div>
                                <div v-if="member.profile.baptism_date" class="text-xs text-neutral-500">
                                    Baptised {{ member.profile.baptism_date }}
                                </div>
                                <div v-if="member.profile.salvation_date" class="text-xs text-neutral-500">
                                    Saved {{ member.profile.salvation_date }}
                                </div>
                            </template>

                            <template v-if="member.profile.emergency_contact_name">
                                <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 pt-2">Emergency</p>
                                <div class="flex items-start gap-2 text-sm text-neutral-600">
                                    <AlertCircle class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                                    <div>
                                        <p>{{ member.profile.emergency_contact_name }}</p>
                                        <p class="text-xs text-neutral-400">{{ member.profile.emergency_contact_relationship }}</p>
                                        <p class="text-xs text-neutral-400">{{ member.profile.emergency_contact_phone }}</p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>

                    <!-- Empty profile placeholder -->
                    <div v-else-if="!member.profile && !editingProfile" class="pt-3 border-t border-neutral-50">
                        <p class="text-xs text-neutral-400 text-center py-2">No profile details yet.</p>
                    </div>

                    <!-- Edit button -->
                    <div v-if="canEdit && !editingProfile" class="pt-1">
                        <button
                            type="button"
                            @click="editingProfile = true"
                            class="w-full flex items-center justify-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-neutral-700 border border-neutral-200 rounded-lg py-2 hover:bg-neutral-50 transition-colors"
                        >
                            <Pencil class="w-3.5 h-3.5" /> Edit profile
                        </button>
                    </div>

                    <!-- Inline edit form -->
                    <div v-if="editingProfile" class="pt-3 border-t border-neutral-50">
                        <MemberProfileForm
                            :member-id="member.id"
                            :profile="member.profile"
                            :can-edit-admin-fields="canEditAdminFields"
                            @cancel="editingProfile = false"
                        />
                    </div>
                </div>

                <!-- All roles (when more than one) -->
                <div v-if="member.roles.length > 1" class="bg-white border border-neutral-100 rounded-xl p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-neutral-400 mb-2">All roles</p>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="r in member.roles"
                            :key="r.name"
                            :class="['px-2 py-0.5 rounded-full text-xs font-medium capitalize', roleColor(r.name)]"
                        >
                            {{ r.name.replace(/_/g, ' ') }}
                        </span>
                    </div>
                </div>

                <!-- Admin: role assignment -->
                <div v-if="canAssignRole" class="bg-white border border-neutral-100 rounded-xl p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <ShieldCheck class="w-4 h-4 text-brand-500 shrink-0" />
                        <p class="text-xs font-semibold text-neutral-700">Change Role</p>
                    </div>
                    <form @submit.prevent="saveRole" class="flex items-center gap-2">
                        <select
                            v-model="roleForm.role"
                            class="flex-1 px-3 py-2 text-sm bg-white border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                        >
                            <option value="member">Member</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="church_admin">Admin</option>
                        </select>
                        <button
                            type="submit"
                            :disabled="roleForm.processing || roleForm.role === (member.roles[0]?.name ?? 'member')"
                            class="px-3 py-2 text-xs font-semibold bg-brand-500 text-white rounded-lg hover:bg-brand-600 disabled:opacity-40 transition-colors"
                        >
                            Save
                        </button>
                    </form>
                    <p v-if="roleForm.wasSuccessful" class="text-xs text-emerald-600 mt-1.5 font-medium">Role updated.</p>
                    <p v-if="roleForm.errors.role" class="text-xs text-rose-600 mt-1.5">{{ roleForm.errors.role }}</p>
                </div>

                <!-- Admin: deactivate / reactivate -->
                <div v-if="canToggleActive" class="bg-white border rounded-xl p-4"
                    :class="member.is_active ? 'border-neutral-100' : 'border-rose-100 bg-rose-50/40'"
                >
                    <div class="flex items-center gap-2 mb-3">
                        <component
                            :is="member.is_active ? UserX : UserCheck"
                            class="w-4 h-4 shrink-0"
                            :class="member.is_active ? 'text-rose-500' : 'text-emerald-500'"
                        />
                        <p class="text-xs font-semibold text-neutral-700">
                            {{ member.is_active ? 'Deactivate account' : 'Reactivate account' }}
                        </p>
                    </div>
                    <p class="text-xs text-neutral-500 mb-3">
                        <template v-if="member.is_active">
                            Deactivating prevents the member from signing in but preserves all their data.
                        </template>
                        <template v-else>
                            <span class="font-medium text-rose-700">This account is currently inactive.</span>
                            Reactivate to restore access.
                        </template>
                    </p>
                    <button
                        type="button"
                        :disabled="toggleActiveForm.processing"
                        class="w-full px-3 py-2 text-xs font-semibold rounded-lg transition-colors disabled:opacity-40"
                        :class="member.is_active
                            ? 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100'
                            : 'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100'"
                        @click="toggleActive"
                    >
                        {{ member.is_active ? 'Deactivate member' : 'Reactivate member' }}
                    </button>
                </div>

            </div>

            <!-- ── MAIN (tabs) ───────────────────────────────────────────────── -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">

                    <!-- Tab bar -->
                    <div class="flex border-b border-neutral-100 overflow-x-auto">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            @click="activeTab = tab.key"
                            :class="[
                                'flex-shrink-0 flex items-center gap-1.5 px-5 py-3.5 text-sm font-medium transition-colors border-b-2',
                                activeTab === tab.key
                                    ? 'border-brand-500 text-brand-600'
                                    : 'border-transparent text-neutral-500 hover:text-neutral-700',
                            ]"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.count !== undefined"
                                :class="['text-[10px] px-1.5 py-0.5 rounded-full font-medium', activeTab === tab.key ? 'bg-brand-50 text-brand-600' : 'bg-neutral-100 text-neutral-500']"
                            >{{ tab.count }}</span>
                        </button>
                    </div>

                    <!-- Departments tab -->
                    <div v-if="activeTab === 'departments'" class="divide-y divide-neutral-50">
                        <div v-if="member.departments.length === 0" class="px-5 py-10 text-center">
                            <Building2 class="w-8 h-8 text-neutral-300 mx-auto mb-2" />
                            <p class="text-sm text-neutral-500">Not assigned to any departments.</p>
                        </div>
                        <div
                            v-for="dept in member.departments"
                            :key="dept.id"
                            class="flex items-center gap-4 px-5 py-3.5"
                        >
                            <div
                                class="w-9 h-9 rounded-lg flex items-center justify-center text-white text-base shrink-0"
                                :style="{ background: dept.color ?? '#1e5aa8' }"
                            >
                                {{ dept.icon ?? '🏛' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-neutral-900 truncate">{{ dept.name }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-neutral-400 capitalize">{{ dept.pivot?.role ?? 'member' }}</span>
                                    <span v-if="dept.coordinator?.id === member.id" class="inline-flex items-center gap-0.5 text-[10px] text-amber-600">
                                        <Crown class="w-3 h-3" /> Coordinator
                                    </span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-xs text-neutral-500">{{ dept.members_count }} members</p>
                                <Link
                                    :href="`/dashboard/departments/${dept.id}`"
                                    class="text-xs text-brand-600 hover:text-brand-800 font-medium transition-colors"
                                >View →</Link>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance tab -->
                    <div v-else-if="activeTab === 'attendance'" class="divide-y divide-neutral-50">
                        <div v-if="recentAttendances.length === 0" class="px-5 py-10 text-center">
                            <CheckCircle2 class="w-8 h-8 text-neutral-300 mx-auto mb-2" />
                            <p class="text-sm text-neutral-500">No attendance records found.</p>
                        </div>
                        <div
                            v-for="att in recentAttendances"
                            :key="att.id"
                            class="flex items-center justify-between px-5 py-3"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-neutral-800 truncate">{{ att.session?.title ?? 'Session' }}</p>
                                <p class="text-xs text-neutral-400 mt-0.5">{{ att.session?.scheduled_date_short ?? att.created_at_formatted }}</p>
                            </div>
                            <span :class="['ml-3 shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize', statusColor[att.status] ?? 'bg-neutral-100 text-neutral-500']">
                                {{ att.status }}
                            </span>
                        </div>
                    </div>

                    <!-- Tasks tab -->
                    <div v-else-if="activeTab === 'tasks'" class="divide-y divide-neutral-50">
                        <div v-if="recentTasks.length === 0" class="px-5 py-10 text-center">
                            <Clock class="w-8 h-8 text-neutral-300 mx-auto mb-2" />
                            <p class="text-sm text-neutral-500">No tasks assigned.</p>
                        </div>
                        <div
                            v-for="task in recentTasks"
                            :key="task.id"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <div class="flex-1 min-w-0">
                                <p :class="['text-sm font-medium truncate', task.status === 'completed' ? 'line-through text-neutral-400' : 'text-neutral-800']">
                                    {{ task.title }}
                                </p>
                                <p v-if="task.due_at_short" class="text-xs mt-0.5" :class="task.is_overdue ? 'text-rose-500' : 'text-neutral-400'">
                                    {{ task.is_overdue ? 'Overdue · ' : 'Due ' }}{{ task.due_at_short }}
                                </p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize', priorityColor[task.priority] ?? 'bg-neutral-100 text-neutral-500']">
                                    {{ task.priority }}
                                </span>
                                <span class="text-xs text-neutral-400 capitalize">{{ task.status.replace(/_/g, ' ') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline tab -->
                    <div v-else-if="activeTab === 'timeline'" class="p-5">
                        <MemberTimeline :entries="timeline" />
                    </div>

                </div>
            </div>

        </div>
    </DashboardLayout>
</template>
