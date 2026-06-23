<!-- resources/js/Pages/Dashboard/Scheduling/Dashboard.vue -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import PlanCard from '@/Components/Scheduling/PlanCard.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { ClipboardList, Layers, Clock3, Archive, Plus } from 'lucide-vue-next'
import type { ServicePlan } from '@/types'

defineProps<{
    stats: {
        total: number
        published: number
        draft: number
        archived: number
    }
    upcoming: ServicePlan[]
}>()

const auth = useAuthStore()
</script>

<template>
    <DashboardLayout title="Scheduling">
        <PageHeader title="Scheduling Dashboard">
            <template #actions>
                <AppButton
                    v-if="auth.can('scheduling.manage')"
                    :href="'/dashboard/scheduling/plans/create'"
                    variant="primary"
                >
                    <Plus class="h-4 w-4" />
                    New Plan
                </AppButton>
            </template>
        </PageHeader>

        <!-- Stats strip -->
        <div class="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-neutral-100 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-brand-50 p-2">
                        <Layers class="h-5 w-5 text-brand-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-neutral-900">{{ stats.total }}</p>
                        <p class="text-xs text-neutral-500">Total Plans</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-neutral-100 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-green-50 p-2">
                        <ClipboardList class="h-5 w-5 text-green-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-neutral-900">{{ stats.published }}</p>
                        <p class="text-xs text-neutral-500">Published</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-neutral-100 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-yellow-50 p-2">
                        <Clock3 class="h-5 w-5 text-yellow-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-neutral-900">{{ stats.draft }}</p>
                        <p class="text-xs text-neutral-500">Drafts</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl border border-neutral-100 bg-white p-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-neutral-100 p-2">
                        <Archive class="h-5 w-5 text-neutral-500" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-neutral-900">{{ stats.archived }}</p>
                        <p class="text-xs text-neutral-500">Archived</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick actions (admin / coordinator only) -->
        <div v-if="auth.can('scheduling.manage')" class="mb-8 grid grid-cols-2 gap-4">
            <Link
                href="/dashboard/scheduling/plans"
                class="flex items-center gap-3 rounded-xl border border-neutral-100 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:bg-brand-50/30"
            >
                <div class="rounded-lg bg-brand-50 p-2">
                    <ClipboardList class="h-5 w-5 text-brand-600" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-neutral-900">Service Plans</p>
                    <p class="text-xs text-neutral-500">Create and manage schedules</p>
                </div>
            </Link>
            <Link
                href="/dashboard/scheduling/positions"
                class="flex items-center gap-3 rounded-xl border border-neutral-100 bg-white p-4 shadow-sm transition hover:border-brand-300 hover:bg-brand-50/30"
            >
                <div class="rounded-lg bg-brand-50 p-2">
                    <Layers class="h-5 w-5 text-brand-600" />
                </div>
                <div>
                    <p class="text-sm font-semibold text-neutral-900">Position Library</p>
                    <p class="text-xs text-neutral-500">Define roles for each team</p>
                </div>
            </Link>
        </div>

        <!-- Upcoming published plans -->
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-neutral-900">Upcoming Services</h2>
                <Link
                    href="/dashboard/scheduling/plans"
                    class="text-sm text-brand-600 hover:underline"
                >
                    View all
                </Link>
            </div>

            <div v-if="upcoming.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <PlanCard v-for="plan in upcoming" :key="plan.id" :plan="plan" />
            </div>

            <EmptyState
                v-else
                :icon="ClipboardList"
                title="No upcoming services"
                description="Publish a service plan to see it here."
            >
                <AppButton
                    v-if="auth.can('scheduling.manage')"
                    :href="'/dashboard/scheduling/plans'"
                    variant="primary"
                >
                    Go to Plans
                </AppButton>
            </EmptyState>
        </div>
    </DashboardLayout>
</template>
