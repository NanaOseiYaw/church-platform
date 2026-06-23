<!-- resources/js/Pages/Dashboard/Scheduling/Plans/Create.vue -->
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { ArrowLeft } from 'lucide-vue-next'

const form = useForm({
    title:        '',
    description:  '',
    scheduled_at: '',
    location:     '',
    notes:        '',
})

function submit() {
    form.post('/dashboard/scheduling/plans')
}
</script>

<template>
    <DashboardLayout title="New Service Plan">
        <PageHeader title="New Service Plan">
            <template #actions>
                <AppButton :href="'/dashboard/scheduling/plans'" variant="ghost">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Plans
                </AppButton>
            </template>
        </PageHeader>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="space-y-5 rounded-xl border border-neutral-100 bg-white p-6 shadow-sm">

                <!-- Title -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">Title <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="Sunday Morning Service"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        :class="{ 'border-red-400': form.errors.title }"
                        required
                    />
                    <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                </div>

                <!-- Date & Time -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">Date & Time <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.scheduled_at"
                        type="datetime-local"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                        :class="{ 'border-red-400': form.errors.scheduled_at }"
                        required
                    />
                    <p v-if="form.errors.scheduled_at" class="mt-1 text-xs text-red-500">{{ form.errors.scheduled_at }}</p>
                </div>

                <!-- Location -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">Location</label>
                    <input
                        v-model="form.location"
                        type="text"
                        placeholder="Main Sanctuary"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    />
                </div>

                <!-- Description -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        placeholder="Brief description of this service…"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    />
                </div>

                <!-- Notes -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">Internal Notes</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        placeholder="Notes visible to coordinators only…"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <AppButton :href="'/dashboard/scheduling/plans'" variant="ghost">Cancel</AppButton>
                    <AppButton type="submit" variant="primary" :disabled="form.processing">
                        {{ form.processing ? 'Creating…' : 'Create Plan' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
