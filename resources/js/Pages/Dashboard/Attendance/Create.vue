<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import { ChevronLeft, CalendarCheck2 } from 'lucide-vue-next'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import type { Department } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

interface ServicePlanOption {
    id: number
    title: string
    status: string
    scheduled_at: string | null
    scheduled_at_formatted: string | null
}

const props = defineProps<{
    departments: Pick<Department, 'id' | 'name'>[]
    upcomingEvents: { id: number; title: string; start_at_formatted: string }[]
    servicePlans: ServicePlanOption[]
    prefill: { event_id?: string | number; department_id?: string | number; service_plan_id?: string | number }
}>()

// ── Form ───────────────────────────────────────────────────────────────────────

const form = useForm({
    title:           '',
    type:            'service' as string,
    description:     '',
    scheduled_at:    '',
    ended_at:        '',
    department_id:   props.prefill.department_id   ? String(props.prefill.department_id)   : '',
    event_id:        props.prefill.event_id        ? String(props.prefill.event_id)        : '',
    service_plan_id: props.prefill.service_plan_id ? String(props.prefill.service_plan_id) : '',
})

// When a plan is selected auto-fill the title and date if the user hasn't typed anything yet
function onPlanChange() {
    const plan = props.servicePlans.find(p => String(p.id) === form.service_plan_id)
    if (!plan) return

    if (!form.title) {
        form.title = plan.title
    }
    if (!form.scheduled_at && plan.scheduled_at) {
        // Convert ISO string to datetime-local value (YYYY-MM-DDTHH:MM)
        form.scheduled_at = plan.scheduled_at.slice(0, 16)
    }
}

function submit() {
    form.post('/dashboard/attendance')
}
</script>

<template>
    <DashboardLayout
        title="New Attendance Session"
        :breadcrumbs="[
            { label: 'Attendance', href: '/dashboard/attendance' },
            { label: 'New Session' }
        ]"
    >
        <div class="max-w-2xl">

            <!-- Back -->
            <Link
                href="/dashboard/attendance"
                class="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 transition-colors mb-6"
            >
                <ChevronLeft class="w-4 h-4" />
                Back to attendance
            </Link>

            <!-- Card -->
            <div class="bg-white border border-neutral-100 rounded-2xl p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center">
                        <CalendarCheck2 class="w-5 h-5 text-brand-600" />
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold text-neutral-900">New Attendance Session</h1>
                        <p class="text-sm text-neutral-400">Create a session to track who attended.</p>
                    </div>
                </div>

                <form class="space-y-5" @submit.prevent="submit">

                    <!-- Title -->
                    <AppInput
                        v-model="form.title"
                        label="Session title"
                        placeholder="e.g. Sunday Morning Service"
                        required
                        :error="form.errors.title"
                    />

                    <!-- Type + Date row -->
                    <div class="grid grid-cols-2 gap-4">
                        <AppSelect
                            v-model="form.type"
                            label="Session type"
                            required
                            :error="form.errors.type"
                        >
                            <option value="service">Service</option>
                            <option value="meeting">Meeting</option>
                            <option value="rehearsal">Rehearsal</option>
                            <option value="outreach">Outreach</option>
                            <option value="volunteer">Volunteer</option>
                            <option value="other">Other</option>
                        </AppSelect>

                        <div>
                            <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                                Scheduled at <span class="text-rose-500">*</span>
                            </label>
                            <input
                                v-model="form.scheduled_at"
                                type="datetime-local"
                                required
                                class="w-full text-sm border border-neutral-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                :class="form.errors.scheduled_at ? 'border-rose-400' : ''"
                            />
                            <p v-if="form.errors.scheduled_at" class="text-xs text-rose-500 mt-1">{{ form.errors.scheduled_at }}</p>
                        </div>
                    </div>

                    <!-- End time (optional) -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Ends at <span class="text-neutral-400 font-normal text-xs">(optional)</span>
                        </label>
                        <input
                            v-model="form.ended_at"
                            type="datetime-local"
                            class="w-full text-sm border border-neutral-200 rounded-xl px-3.5 py-2.5 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                        />
                    </div>

                    <!-- Department + Event row -->
                    <div class="grid grid-cols-2 gap-4">
                        <AppSelect
                            v-model="form.department_id"
                            label="Department"
                            :error="form.errors.department_id"
                        >
                            <option value="">None (church-wide)</option>
                            <option
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="String(dept.id)"
                            >{{ dept.name }}</option>
                        </AppSelect>

                        <AppSelect
                            v-model="form.event_id"
                            label="Linked event"
                            :error="form.errors.event_id"
                        >
                            <option value="">None</option>
                            <option
                                v-for="ev in upcomingEvents"
                                :key="ev.id"
                                :value="String(ev.id)"
                            >{{ ev.title }} · {{ ev.start_at_formatted }}</option>
                        </AppSelect>
                    </div>

                    <!-- Service plan link -->
                    <AppSelect
                        v-model="form.service_plan_id"
                        label="Linked service plan"
                        hint="Volunteers from the plan will be pre-loaded as expected attendees."
                        :error="form.errors.service_plan_id"
                        @change="onPlanChange"
                    >
                        <option value="">None</option>
                        <option
                            v-for="plan in servicePlans"
                            :key="plan.id"
                            :value="String(plan.id)"
                        >{{ plan.title }} · {{ plan.scheduled_at_formatted }}</option>
                    </AppSelect>

                    <!-- Description -->
                    <AppTextarea
                        v-model="form.description"
                        label="Notes"
                        placeholder="Any context or notes for this session…"
                        :rows="3"
                        :error="form.errors.description"
                    />

                    <!-- Actions -->
                    <div class="flex items-center gap-3 pt-2 border-t border-neutral-50">
                        <AppButton
                            type="submit"
                            :loading="form.processing"
                        >
                            Create session
                        </AppButton>
                        <Link
                            href="/dashboard/attendance"
                            class="text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </DashboardLayout>
</template>
