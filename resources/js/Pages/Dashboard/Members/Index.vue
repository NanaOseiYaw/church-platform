<script setup lang="ts">
import { ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { Users, Search, ChevronRight, UserPlus, Copy, Check } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/useAuthStore'

interface Role { name: string }

interface Member {
    id: number
    name: string
    email: string
    avatar: string | null
    is_active: boolean
    departments_count: number
    roles: Role[]
}

interface PaginatedMembers {
    data: Member[]
    current_page: number
    last_page: number
    total: number
    per_page: number
    links: { url: string | null; label: string; active: boolean }[]
}

interface Dept {
    id: number
    name: string
    icon: string | null
    color: string | null
}

interface Stats {
    total: number
    active: number
    inactive: number
    admins: number
    coordinators: number
    members: number
}

const props = defineProps<{
    members: PaginatedMembers
    filters: { search?: string; role?: string; dept_id?: string; status?: string }
    stats: Stats
    departments: Dept[]
    newMember?: { name: string; email: string; temp_password: string } | null
}>()

const search  = ref(props.filters.search   ?? '')
const role    = ref(props.filters.role     ?? '')
const deptId  = ref(props.filters.dept_id  ?? '')
const status  = ref(props.filters.status   ?? '')

function pushFilters() {
    router.get('/dashboard/members', {
        search:  search.value,
        role:    role.value,
        dept_id: deptId.value,
        status:  status.value,
    }, { preserveState: true, replace: true })
}

let searchTimer: ReturnType<typeof setTimeout>
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(pushFilters, 350)
})
watch(role,   pushFilters)
watch(deptId, pushFilters)
watch(status, pushFilters)

const ROLE_OPTIONS = [
    { value: '',                      label: 'All roles' },
    { value: 'super_admin',           label: 'Super Admin' },
    { value: 'church_admin',          label: 'Admin' },
    { value: 'coordinator',           label: 'Coordinator' },
    { value: 'assistant_coordinator', label: 'Asst. Coordinator' },
    { value: 'member',                label: 'Member' },
]

const auth = useAuthStore()

// ── Add member (admin-creates an account with a temporary password) ─────────────
const showAddModal = ref(false)
const addForm = useForm({ name: '', email: '', role: 'member' })

function openAdd() {
    addForm.reset()
    addForm.clearErrors()
    showAddModal.value = true
}

function submitAdd() {
    addForm.post('/dashboard/members', {
        preserveScroll: true,
        onSuccess: () => { showAddModal.value = false },
    })
}

// ── One-time credentials shown after creating a member ──────────────────────────
const showCredsModal = ref(false)
const createdMember  = ref<{ name: string; email: string; temp_password: string } | null>(null)
const pwCopied       = ref(false)

watch(() => props.newMember, (v) => {
    if (v) {
        createdMember.value = v
        showCredsModal.value = true
    }
}, { immediate: true })

function copyPassword() {
    if (!createdMember.value) return
    navigator.clipboard.writeText(createdMember.value.temp_password)
    pwCopied.value = true
    setTimeout(() => { pwCopied.value = false }, 2500)
}

function primaryRole(member: Member): string {
    return (member.roles[0]?.name ?? 'member').replace(/_/g, ' ')
}

// ── Invite link ────────────────────────────────────────────────────────────────
const inviteCopied  = ref(false)
const inviteLink    = `${window.location.origin}/register`

function copyInviteLink() {
    navigator.clipboard.writeText(inviteLink)
    inviteCopied.value = true
    setTimeout(() => { inviteCopied.value = false }, 2500)
}

const roleColor: Record<string, string> = {
    super_admin:           'bg-rose-50 text-rose-700',
    church_admin:          'bg-brand-50 text-brand-700',
    coordinator:           'bg-blue-50 text-blue-700',
    assistant_coordinator: 'bg-sky-50 text-sky-700',
    member:                'bg-neutral-100 text-neutral-600',
}

function getRoleColor(member: Member): string {
    const r = member.roles[0]?.name ?? 'member'
    return roleColor[r] ?? roleColor['member']
}
</script>

<template>
    <DashboardLayout
        title="Members"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Members' }]"
    >
        <PageHeader title="Members" description="Everyone in your church community.">
            <template v-if="auth.isAdmin" #actions>
                <AppButton size="sm" @click="openAdd">
                    <UserPlus class="w-4 h-4" /> Add member
                </AppButton>
            </template>
        </PageHeader>

        <!-- Admin: invite banner -->
        <div
            v-if="auth.isAdmin"
            class="flex items-center gap-4 bg-brand-50 border border-brand-100 rounded-xl px-5 py-3.5 mb-5 flex-wrap"
        >
            <div class="flex items-center gap-2.5 flex-1 min-w-0">
                <UserPlus class="w-4 h-4 text-brand-500 shrink-0" />
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-900">Invite a new member</p>
                    <p class="text-xs text-brand-600 truncate">Share this link: <span class="font-mono">{{ inviteLink }}</span></p>
                </div>
            </div>
            <button
                type="button"
                @click="copyInviteLink"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors shrink-0"
                :class="inviteCopied
                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    : 'bg-white text-brand-700 border-brand-200 hover:bg-brand-100'"
            >
                <Check v-if="inviteCopied" class="w-3.5 h-3.5" />
                <Copy v-else class="w-3.5 h-3.5" />
                {{ inviteCopied ? 'Copied!' : 'Copy link' }}
            </button>
        </div>

        <!-- Stats strip -->
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-5 text-sm text-neutral-500">
            <span><span class="font-semibold text-neutral-800">{{ stats.total }}</span> total</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-emerald-700">{{ stats.active }}</span> active</span>
            <span v-if="stats.inactive > 0" class="text-neutral-300">·</span>
            <span v-if="stats.inactive > 0">
                <span class="font-semibold text-rose-600">{{ stats.inactive }}</span> deactivated
            </span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-neutral-800">{{ stats.admins }}</span> admins</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-neutral-800">{{ stats.coordinators }}</span> coordinators</span>
            <span class="text-neutral-300">·</span>
            <span><span class="font-semibold text-neutral-800">{{ stats.members }}</span> members</span>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <!-- Search -->
            <div class="relative flex-1 max-w-xs">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email…"
                    class="w-full pl-9 pr-4 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                />
            </div>

            <!-- Role filter -->
            <select
                v-model="role"
                class="px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 bg-white transition"
            >
                <option v-for="opt in ROLE_OPTIONS" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>

            <!-- Department filter -->
            <select
                v-model="deptId"
                class="px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 bg-white transition"
            >
                <option value="">All departments</option>
                <option v-for="d in departments" :key="d.id" :value="String(d.id)">{{ d.icon ? d.icon + ' ' : '' }}{{ d.name }}</option>
            </select>

            <!-- Status filter -->
            <select
                v-model="status"
                class="px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 bg-white transition"
            >
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Deactivated</option>
            </select>
        </div>

        <!-- Empty -->
        <EmptyState
            v-if="members.data.length === 0"
            :icon="Users"
            title="No members found"
            description="Try adjusting your search or filters."
        />

        <!-- Table -->
        <div v-else class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-50">
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Member</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 hidden sm:table-cell">Role</th>
                        <th class="text-left px-5 py-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400 hidden md:table-cell">Depts</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-50">
                    <tr
                        v-for="member in members.data"
                        :key="member.id"
                        class="hover:bg-neutral-50/50 transition-colors"
                    >
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="relative shrink-0">
                                    <AppAvatar :name="member.name" :src="member.avatar" size="sm" :class="{ 'opacity-50': !member.is_active }" />
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-medium truncate" :class="member.is_active ? 'text-neutral-900' : 'text-neutral-400'">{{ member.name }}</p>
                                        <span
                                            v-if="!member.is_active"
                                            class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-rose-50 text-rose-600 border border-rose-100 shrink-0"
                                        >
                                            Deactivated
                                        </span>
                                    </div>
                                    <p class="text-xs text-neutral-400 truncate">{{ member.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 hidden sm:table-cell">
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize', getRoleColor(member)]">
                                {{ primaryRole(member) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 hidden md:table-cell text-neutral-500">
                            {{ member.departments_count }}
                        </td>
                        <td class="px-3 py-3">
                            <Link
                                :href="`/dashboard/members/${member.id}`"
                                class="p-1.5 rounded-lg hover:bg-neutral-100 text-neutral-400 hover:text-neutral-600 transition-colors flex"
                            >
                                <ChevronRight class="w-4 h-4" />
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <AppPagination
            :links="members.links"
            :current-page="members.current_page"
            :last-page="members.last_page"
            :total="members.total"
            :per-page="members.per_page"
        />

        <!-- Add member modal -->
        <AppModal :open="showAddModal" title="Add member" @close="showAddModal = false">
            <div class="space-y-4">
                <p class="text-xs text-neutral-500">
                    Creates an account directly and generates a temporary password to share with the member.
                </p>
                <AppInput v-model="addForm.name" label="Full name" :error="addForm.errors.name" />
                <AppInput v-model="addForm.email" type="email" label="Email" :error="addForm.errors.email" />
                <AppSelect v-model="addForm.role" label="Role" :error="addForm.errors.role">
                    <option value="member">Member</option>
                    <option value="assistant_coordinator">Assistant Coordinator</option>
                    <option value="coordinator">Coordinator</option>
                    <option value="church_admin">Admin</option>
                </AppSelect>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <AppButton variant="outline" class="flex-1" @click="showAddModal = false">Cancel</AppButton>
                    <AppButton class="flex-1" :loading="addForm.processing" @click="submitAdd">Create member</AppButton>
                </div>
            </template>
        </AppModal>

        <!-- One-time credentials modal -->
        <AppModal :open="showCredsModal" title="Member created" @close="showCredsModal = false">
            <div v-if="createdMember" class="space-y-4">
                <p class="text-sm text-neutral-600">
                    <span class="font-medium text-neutral-900">{{ createdMember.name }}</span> can now sign in.
                    Share these credentials securely — the password won't be shown again.
                </p>
                <div class="rounded-xl border border-neutral-200 bg-neutral-50 divide-y divide-neutral-200">
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="text-xs font-medium text-neutral-500">Email</span>
                        <span class="text-sm font-mono text-neutral-800 truncate">{{ createdMember.email }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="text-xs font-medium text-neutral-500">Temporary password</span>
                        <span class="text-sm font-mono text-neutral-800">{{ createdMember.temp_password }}</span>
                    </div>
                </div>
                <p class="text-xs text-neutral-400">Ask the member to change their password after first sign-in.</p>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <AppButton variant="outline" class="flex-1" @click="copyPassword">
                        <Check v-if="pwCopied" class="w-4 h-4" />
                        <Copy v-else class="w-4 h-4" />
                        {{ pwCopied ? 'Copied!' : 'Copy password' }}
                    </AppButton>
                    <AppButton class="flex-1" @click="showCredsModal = false">Done</AppButton>
                </div>
            </template>
        </AppModal>
    </DashboardLayout>
</template>
