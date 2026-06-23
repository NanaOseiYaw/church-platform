<!-- resources/js/Components/Scheduling/AssignmentCard.vue -->
<script setup lang="ts">
import { X } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'
import type { VolunteerAssignment } from '@/types'

defineProps<{
    assignment: VolunteerAssignment
    canManage: boolean
}>()

defineEmits<{ remove: [assignment: VolunteerAssignment] }>()
</script>

<template>
    <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
        <!-- Avatar -->
        <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
            <img
                v-if="assignment.volunteer?.avatar"
                :src="assignment.volunteer.avatar"
                :alt="assignment.volunteer.name"
                class="h-8 w-8 rounded-full object-cover"
            />
            <span v-else>{{ assignment.volunteer?.name?.slice(0, 1).toUpperCase() ?? '?' }}</span>
        </div>

        <span class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800">
            {{ assignment.volunteer?.name ?? 'Unknown' }}
        </span>

        <StatusBadge :status="assignment.status" />

        <button
            v-if="canManage"
            type="button"
            class="ml-1 rounded p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-500"
            title="Remove assignment"
            @click="$emit('remove', assignment)"
        >
            <X class="h-3.5 w-3.5" />
        </button>
    </div>
</template>
