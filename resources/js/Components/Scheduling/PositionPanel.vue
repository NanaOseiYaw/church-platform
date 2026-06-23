<!-- resources/js/Components/Scheduling/PositionPanel.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { UserPlus } from 'lucide-vue-next'
import AssignmentCard from '@/Components/Scheduling/AssignmentCard.vue'
import AssignDrawer from '@/Components/Scheduling/AssignDrawer.vue'
import type { ServicePlanPosition, VolunteerAssignment } from '@/types'

interface Member { id: number; name: string; avatar: string | null }

const props = defineProps<{
    positions: ServicePlanPosition[]
    members: Member[]
    canManage: boolean
    planStatus: string
}>()

// Track which slot the drawer is open for
const drawerPositionId = ref<number | null>(null)

function openDrawer(planPositionId: number) {
    drawerPositionId.value = planPositionId
}

function closeDrawer() {
    drawerPositionId.value = null
}

function existingFor(planPositionId: number): VolunteerAssignment[] {
    return props.positions.find(p => p.id === planPositionId)?.assignments ?? []
}

function assignVolunteer(planPositionId: number, userId: number) {
    router.post('/dashboard/scheduling/assignments', {
        service_plan_position_id: planPositionId,
        user_id: userId,
    }, {
        preserveScroll: true,
        onSuccess: () => closeDrawer(),
    })
}

function removeAssignment(assignment: VolunteerAssignment) {
    if (!confirm('Remove this volunteer from the slot?')) return
    router.delete(`/dashboard/scheduling/assignments/${assignment.id}`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <div class="flex flex-1 flex-col overflow-y-auto bg-gray-50 p-6">
        <div v-if="positions.length === 0" class="flex flex-1 items-center justify-center text-sm text-gray-400">
            No positions in this department yet.
        </div>

        <div v-else class="space-y-4">
            <div
                v-for="pp in positions"
                :key="pp.id"
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <!-- Position header -->
                <div class="mb-3 flex items-center justify-between gap-2">
                    <div>
                        <p class="font-medium text-gray-900">{{ pp.serving_position?.name }}</p>
                        <p v-if="pp.notes" class="mt-0.5 text-xs text-gray-500">{{ pp.notes }}</p>
                    </div>
                    <span
                        :class="[
                            'text-xs font-medium',
                            pp.is_filled ? 'text-green-600' : 'text-gray-400',
                        ]"
                    >
                        {{ pp.is_filled ? 'Filled' : 'Open' }}
                    </span>
                </div>

                <!-- Assignments -->
                <div class="space-y-2">
                    <AssignmentCard
                        v-for="a in pp.assignments"
                        :key="a.id"
                        :assignment="a"
                        :can-manage="canManage && planStatus !== 'archived'"
                        @remove="removeAssignment"
                    />
                </div>

                <!-- Assign button -->
                <button
                    v-if="canManage && planStatus !== 'archived'"
                    type="button"
                    class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:border-brand-400 hover:text-brand-600"
                    @click="openDrawer(pp.id)"
                >
                    <UserPlus class="h-3.5 w-3.5" />
                    Assign volunteer
                </button>
            </div>
        </div>

        <!-- Assign drawer -->
        <AssignDrawer
            :open="drawerPositionId !== null"
            :plan-position-id="drawerPositionId ?? 0"
            :members="members"
            :existing-assignments="drawerPositionId ? existingFor(drawerPositionId) : []"
            @close="closeDrawer"
            @assign="assignVolunteer"
        />
    </div>
</template>
