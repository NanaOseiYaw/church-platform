<!-- resources/js/Pages/Dashboard/Scheduling/Plans/Index.vue -->
<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import PlanCard from '@/Components/Scheduling/PlanCard.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { Plus, ClipboardList } from 'lucide-vue-next'
import type { ServicePlan } from '@/types'

interface Props {
    plans: {
        data: ServicePlan[]
        links: { url: string | null; label: string; active: boolean }[]
        current_page: number
        last_page: number
        total: number
    }
    filters: { tab?: string }
}

const props = defineProps<Props>()
const auth = useAuthStore()

const tabs = [
    { key: 'upcoming', label: 'Upcoming' },
    { key: 'draft',    label: 'Drafts' },
    { key: 'past',     label: 'Past' },
    { key: 'archived', label: 'Archived' },
]

const currentTab = ref(props.filters.tab ?? 'upcoming')

watch(currentTab, (tab) => {
    router.get('/dashboard/scheduling/plans', { tab }, { preserveState: true, replace: true })
})
</script>

<template>
    <DashboardLayout title="Service Plans">
        <PageHeader title="Service Plans">
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

        <!-- Tabs -->
        <div class="mb-6 flex gap-1 border-b border-neutral-100">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                :class="[
                    'px-4 py-2 text-sm font-medium transition',
                    currentTab === tab.key
                        ? 'border-b-2 border-brand-600 text-brand-600'
                        : 'text-neutral-500 hover:text-neutral-700',
                ]"
                @click="currentTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Plans grid -->
        <div v-if="plans.data.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <PlanCard v-for="plan in plans.data" :key="plan.id" :plan="plan" />
        </div>

        <EmptyState
            v-else
            :icon="ClipboardList"
            title="No plans yet"
            description="Create a service plan to start scheduling volunteers."
        >
            <AppButton
                v-if="auth.can('scheduling.manage')"
                :href="'/dashboard/scheduling/plans/create'"
                variant="primary"
            >
                <Plus class="h-4 w-4" />
                New Plan
            </AppButton>
        </EmptyState>

        <AppPagination v-if="plans.last_page > 1" :links="plans.links" class="mt-6" />
    </DashboardLayout>
</template>
