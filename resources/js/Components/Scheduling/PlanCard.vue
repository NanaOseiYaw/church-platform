<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { CalendarDays, MapPin, Users } from 'lucide-vue-next'
import StatusBadge from '@/Components/Scheduling/StatusBadge.vue'
import type { ServicePlan } from '@/types'

defineProps<{ plan: ServicePlan }>()
</script>

<template>
    <Link
        :href="`/dashboard/scheduling/plans/${plan.id}`"
        class="block rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md hover:border-brand-300"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate font-semibold text-gray-900">{{ plan.title }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">
                    <span class="flex items-center gap-1">
                        <CalendarDays class="h-3.5 w-3.5" />
                        {{ plan.scheduled_at_formatted }}
                        <span v-if="plan.scheduled_time" class="text-gray-400">· {{ plan.scheduled_time }}</span>
                    </span>
                    <span v-if="plan.location" class="flex items-center gap-1">
                        <MapPin class="h-3.5 w-3.5" />
                        {{ plan.location }}
                    </span>
                </div>
            </div>
            <StatusBadge :status="plan.status" />
        </div>

        <!-- Fill rate bar -->
        <div v-if="plan.total_positions" class="mt-4">
            <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                <span class="flex items-center gap-1">
                    <Users class="h-3.5 w-3.5" />
                    {{ plan.filled_positions }} / {{ plan.total_positions }} filled
                </span>
                <span>{{ plan.fill_rate }}%</span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-gray-100">
                <div
                    class="h-1.5 rounded-full bg-brand-500 transition-all"
                    :style="{ width: `${plan.fill_rate}%` }"
                />
            </div>
        </div>
        <p v-else class="mt-3 text-xs text-gray-400">No positions added yet</p>
    </Link>
</template>
