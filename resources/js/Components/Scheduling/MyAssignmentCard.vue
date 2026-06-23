<!-- resources/js/Components/Scheduling/MyAssignmentCard.vue -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { CalendarDays, MapPin, CheckCircle2, XCircle } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'

interface AssignmentRow {
    id: number
    status: string
    plan: {
        id: number
        title: string
        status: string
        scheduled_at: string
        scheduled_at_formatted: string
        scheduled_time: string
        location: string | null
    }
    position: {
        name: string | null
        department: { id: number; name: string; icon: string | null; color: string | null } | null
    }
}

defineProps<{ assignment: AssignmentRow }>()

function respond(id: number, status: 'confirmed' | 'declined') {
    router.patch(`/dashboard/scheduling/assignments/${id}/respond`, { status }, { preserveScroll: true })
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate font-semibold text-gray-900">{{ assignment.plan.title }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span class="flex items-center gap-1">
                        <CalendarDays class="h-3.5 w-3.5" />
                        {{ assignment.plan.scheduled_at_formatted }} · {{ assignment.plan.scheduled_time }}
                    </span>
                    <span v-if="assignment.plan.location" class="flex items-center gap-1">
                        <MapPin class="h-3.5 w-3.5" />
                        {{ assignment.plan.location }}
                    </span>
                    <span
                        v-if="assignment.plan.status === 'draft'"
                        class="inline-block text-[10px] font-medium bg-yellow-50 text-yellow-600 border border-yellow-200 px-1.5 py-0.5 rounded"
                    >
                        Draft schedule
                    </span>
                </div>
                <p class="mt-1 text-sm text-brand-600">
                    {{ assignment.position.name }}
                    <span v-if="assignment.position.department" class="text-gray-400">
                        · {{ assignment.position.department.name }}
                    </span>
                </p>
            </div>
            <StatusBadge :status="assignment.status" />
        </div>

        <!-- Confirm / Decline buttons (only when pending) -->
        <div v-if="assignment.status === 'pending'" class="mt-3 flex gap-2">
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-green-600 py-1.5 text-sm font-medium text-white hover:bg-green-700"
                @click="respond(assignment.id, 'confirmed')"
            >
                <CheckCircle2 class="h-4 w-4" />
                Confirm
            </button>
            <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 py-1.5 text-sm font-medium text-gray-600 hover:bg-gray-50"
                @click="respond(assignment.id, 'declined')"
            >
                <XCircle class="h-4 w-4" />
                Can't make it
            </button>
        </div>
    </div>
</template>
