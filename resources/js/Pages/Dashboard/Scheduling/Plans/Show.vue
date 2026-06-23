<!-- resources/js/Pages/Dashboard/Scheduling/Plans/Show.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import DeptSidebar from '@/Components/Scheduling/DeptSidebar.vue'
import PositionPanel from '@/Components/Scheduling/PositionPanel.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { ArrowLeft, Pencil, Trash2, X, Plus } from 'lucide-vue-next'
import type { ServicePlan, ServicePlanPosition, ServingPosition } from '@/types'

interface Member { id: number; name: string; avatar: string | null }
interface AvailablePosition { id: number; name: string; department: { id: number; name: string } }
interface AttendanceSessionLink { id: number; title: string; status: string }

const props = defineProps<{
    plan: ServicePlan & { plan_positions: ServicePlanPosition[] }
    members: Member[]
    availablePositions: AvailablePosition[]
    canManage: boolean
    canPublish: boolean
    canDelete: boolean
    attendanceSession: AttendanceSessionLink | null
}>()

// ── Department grouping ────────────────────────────────────────────────────────

interface DeptGroup {
    id: number; name: string; icon: string | null; color: string | null
    positions: ServicePlanPosition[]
}

const deptGroups = computed<DeptGroup[]>(() => {
    const map = new Map<number, DeptGroup>()
    for (const pp of props.plan.plan_positions ?? []) {
        const dept = pp.serving_position?.department
        if (!dept) continue
        if (!map.has(dept.id)) map.set(dept.id, { ...dept, positions: [] })
        map.get(dept.id)!.positions.push(pp)
    }
    return Array.from(map.values())
})

const selectedDeptId = ref<number | null>(deptGroups.value[0]?.id ?? null)

const selectedPositions = computed<ServicePlanPosition[]>(() =>
    deptGroups.value.find(d => d.id === selectedDeptId.value)?.positions ?? []
)

// ── Edit plan modal ────────────────────────────────────────────────────────────

const showEditModal = ref(false)

const editForm = useForm({
    title:        props.plan.title,
    description:  props.plan.description ?? '',
    scheduled_at: props.plan.scheduled_at?.slice(0, 16) ?? '',
    location:     props.plan.location ?? '',
    notes:        props.plan.notes ?? '',
})

function submitEdit() {
    editForm.put(`/dashboard/scheduling/plans/${props.plan.id}`, {
        onSuccess: () => { showEditModal.value = false },
    })
}

// ── Add position modal ─────────────────────────────────────────────────────────

const showAddPosition = ref(false)
const selectedPositionId = ref<number | null>(null)

function addPosition() {
    if (!selectedPositionId.value) return
    router.post(`/dashboard/scheduling/plans/${props.plan.id}/positions`, {
        serving_position_id: selectedPositionId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAddPosition.value = false
            selectedPositionId.value = null
        },
    })
}

// ── Delete plan ────────────────────────────────────────────────────────────────

function deletePlan() {
    if (!confirm('Delete this plan? This cannot be undone.')) return
    router.delete(`/dashboard/scheduling/plans/${props.plan.id}`)
}
</script>

<template>
    <DashboardLayout :title="plan.title">
        <!-- Top bar -->
        <div class="flex items-center justify-between border-b border-neutral-100 bg-white px-6 py-3">
            <AppButton :href="'/dashboard/scheduling/plans'" variant="ghost" size="sm">
                <ArrowLeft class="h-4 w-4" />
                Plans
            </AppButton>
            <div class="flex items-center gap-2">
                <AppButton
                    v-if="canManage"
                    variant="ghost"
                    size="sm"
                    @click="showEditModal = true"
                >
                    <Pencil class="h-3.5 w-3.5" />
                    Edit
                </AppButton>
                <AppButton
                    v-if="canDelete"
                    variant="ghost"
                    size="sm"
                    class="text-red-500 hover:text-red-600"
                    @click="deletePlan"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                    Delete
                </AppButton>
            </div>
        </div>

        <!-- Main split layout -->
        <div class="flex flex-1 overflow-hidden" style="height: calc(100vh - 112px)">
            <DeptSidebar
                :plan="plan"
                :dept-groups="deptGroups"
                :selected-dept-id="selectedDeptId"
                :can-manage="canManage"
                :can-publish="canPublish"
                :attendance-session="attendanceSession"
                @select-dept="id => selectedDeptId = id"
                @add-position="showAddPosition = true"
            />

            <!-- Position panel or empty state -->
            <div v-if="selectedDeptId" class="flex flex-1 flex-col overflow-hidden">
                <div class="border-b border-neutral-100 bg-white px-6 py-3">
                    <p class="font-semibold text-neutral-900">
                        {{ deptGroups.find(d => d.id === selectedDeptId)?.name }}
                    </p>
                </div>
                <PositionPanel
                    :positions="selectedPositions"
                    :members="members"
                    :can-manage="canManage"
                    :plan-status="plan.status"
                />
            </div>

            <div v-else class="flex flex-1 items-center justify-center text-sm text-neutral-400">
                <div class="text-center">
                    <p>No positions added to this plan yet.</p>
                    <button
                        v-if="canManage"
                        type="button"
                        class="mt-2 text-brand-600 hover:underline"
                        @click="showAddPosition = true"
                    >
                        Add a position from the library
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Plan Modal -->
        <Teleport to="body">
            <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                        <h2 class="font-semibold text-neutral-900">Edit Plan</h2>
                        <button @click="showEditModal = false" class="text-neutral-400 hover:text-neutral-600">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitEdit" class="space-y-4 px-5 py-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Title</label>
                            <input v-model="editForm.title" type="text" required
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Date & Time</label>
                            <input v-model="editForm.scheduled_at" type="datetime-local" required
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Location</label>
                            <input v-model="editForm.location" type="text"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Notes</label>
                            <textarea v-model="editForm.notes" rows="2"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none" />
                        </div>
                        <div class="flex justify-end gap-3 pt-1">
                            <AppButton variant="ghost" type="button" @click="showEditModal = false">Cancel</AppButton>
                            <AppButton variant="primary" type="submit" :disabled="editForm.processing">Save</AppButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Add Position Modal -->
        <Teleport to="body">
            <div v-if="showAddPosition" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                        <h2 class="font-semibold text-neutral-900">Add Position from Library</h2>
                        <button @click="showAddPosition = false" class="text-neutral-400 hover:text-neutral-600">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="px-5 py-4">
                        <!-- Empty library state -->
                        <div v-if="availablePositions.length === 0" class="rounded-lg bg-neutral-50 px-4 py-6 text-center">
                            <p class="text-sm text-neutral-600">No positions in the library yet.</p>
                            <a
                                href="/dashboard/scheduling/positions"
                                class="mt-1 inline-block text-sm text-brand-600 hover:underline"
                            >
                                Create positions first →
                            </a>
                            <div class="mt-4 flex justify-end">
                                <AppButton variant="ghost" type="button" @click="showAddPosition = false">Close</AppButton>
                            </div>
                        </div>

                        <!-- Normal state: position select -->
                        <template v-else>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Select Position</label>
                            <select
                                v-model="selectedPositionId"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                            >
                                <option :value="null" disabled>Choose a position…</option>
                                <optgroup
                                    v-for="dept in [...new Map(availablePositions.map(p => [p.department!.id, p.department!])).values()]"
                                    :key="dept.id"
                                    :label="dept.name"
                                >
                                    <option
                                        v-for="pos in availablePositions.filter(p => p.department!.id === dept.id)"
                                        :key="pos.id"
                                        :value="pos.id"
                                    >
                                        {{ pos.name }}
                                    </option>
                                </optgroup>
                            </select>
                            <div class="mt-4 flex justify-end gap-3">
                                <AppButton variant="ghost" type="button" @click="showAddPosition = false">Cancel</AppButton>
                                <AppButton variant="primary" type="button" :disabled="!selectedPositionId" @click="addPosition">
                                    <Plus class="h-4 w-4" />
                                    Add to Plan
                                </AppButton>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
