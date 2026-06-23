<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { ArrowLeft } from 'lucide-vue-next'

interface Department {
    id: number
    name: string
    description: string | null
    icon: string | null
    color: string | null
    coordinator_id: number | null
    visibility: string
    is_active: boolean
}

interface StaffMember {
    id: number
    name: string
}

interface VisibilityOption {
    value: string
    label: string
    description: string
}

const props = defineProps<{
    department: Department
    staff: StaffMember[]
    visibilityOptions: VisibilityOption[]
}>()

const form = useForm({
    name:           props.department.name,
    description:    props.department.description ?? '',
    icon:           props.department.icon ?? '',
    color:          props.department.color ?? '#1e5aa8',
    coordinator_id: props.department.coordinator_id,
    visibility:     props.department.visibility ?? 'public',
    is_active:      props.department.is_active,
})

const coordinatorOptions = [
    { value: '', label: 'No coordinator' },
    ...props.staff.map(s => ({ value: s.id, label: s.name })),
]

function submit() {
    form.put(`/dashboard/departments/${props.department.id}`)
}
</script>

<template>
    <DashboardLayout
        :title="`Edit — ${department.name}`"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Departments', href: '/dashboard/departments' },
            { label: department.name, href: `/dashboard/departments/${department.id}` },
            { label: 'Edit' },
        ]"
    >
        <PageHeader :title="`Edit ${department.name}`" description="Update the department details.">
            <template #actions>
                <AppButton :href="`/dashboard/departments/${department.id}`" variant="outline" size="sm">
                    <ArrowLeft class="w-4 h-4" /> Back
                </AppButton>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-50">

                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Basic information</h3>

                    <AppInput
                        label="Department name"
                        v-model="form.name"
                        :error="form.errors.name"
                        required
                    />

                    <div>
                        <label class="block text-xs font-medium text-neutral-700 mb-1.5">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full px-3 py-2 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 placeholder:text-neutral-400 resize-none transition"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-rose-500">{{ form.errors.description }}</p>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Visual identity</h3>
                    <div class="flex gap-4">
                        <div class="flex-1">
                            <AppInput
                                label="Icon (emoji)"
                                v-model="form.icon"
                                placeholder="🏛"
                            />
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium text-neutral-700 mb-1.5">Brand color</label>
                            <div class="flex items-center gap-2">
                                <input
                                    type="color"
                                    v-model="form.color"
                                    class="w-10 h-9 rounded-lg border border-neutral-200 cursor-pointer p-0.5"
                                />
                                <span class="text-xs text-neutral-500 font-mono">{{ form.color }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 p-3 bg-neutral-50 rounded-lg">
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center text-xl text-white shrink-0"
                            :style="{ background: form.color }"
                        >
                            {{ form.icon || '🏛' }}
                        </div>
                        <p class="text-sm font-medium text-neutral-900">{{ form.name }}</p>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Assignment</h3>

                    <AppSelect
                        label="Coordinator"
                        v-model="form.coordinator_id"
                        :options="coordinatorOptions"
                        :error="form.errors.coordinator_id"
                    />

                    <!-- Visibility -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-700 mb-2">Workspace visibility</label>
                        <div class="space-y-2">
                            <label
                                v-for="opt in visibilityOptions"
                                :key="opt.value"
                                class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                :class="form.visibility === opt.value
                                    ? 'border-brand-400 bg-brand-50'
                                    : 'border-neutral-200 hover:border-neutral-300'"
                            >
                                <input
                                    type="radio"
                                    v-model="form.visibility"
                                    :value="opt.value"
                                    class="mt-0.5 text-brand-600 focus:ring-brand-500"
                                />
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ opt.label }}</p>
                                    <p class="text-xs text-neutral-500">{{ opt.description }}</p>
                                </div>
                            </label>
                        </div>
                        <p v-if="form.errors.visibility" class="mt-1 text-xs text-rose-500">{{ form.errors.visibility }}</p>
                    </div>

                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                        />
                        <span class="text-sm text-neutral-700">Active (visible to members)</span>
                    </label>
                </div>

                <div class="px-6 py-4 flex items-center justify-end gap-3">
                    <Link :href="`/dashboard/departments/${department.id}`" class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors">
                        Cancel
                    </Link>
                    <AppButton type="submit" :loading="form.processing">
                        Save changes
                    </AppButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
