<!-- resources/js/Pages/Dashboard/Scheduling/MySchedule.vue -->
<script setup lang="ts">
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import MyAssignmentCard from '@/Components/Scheduling/MyAssignmentCard.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import { CalendarDays } from 'lucide-vue-next'

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

defineProps<{ assignments: AssignmentRow[] }>()
</script>

<template>
    <DashboardLayout title="My Schedule">
        <PageHeader title="My Schedule" />

        <div v-if="assignments.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <MyAssignmentCard
                v-for="assignment in assignments"
                :key="assignment.id"
                :assignment="assignment"
            />
        </div>

        <EmptyState
            v-else
            :icon="CalendarDays"
            title="No upcoming assignments"
            description="You haven't been assigned to any upcoming services yet."
        />
    </DashboardLayout>
</template>
