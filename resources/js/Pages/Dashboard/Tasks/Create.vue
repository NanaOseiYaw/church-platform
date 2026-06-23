<script setup lang="ts">
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { ArrowLeft, Flame, ArrowUp, ArrowRight, ArrowDown } from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

interface Member { id: number; name: string; avatar: string | null }
interface Dept   { id: number; name: string; icon: string | null; color: string | null }

const props = defineProps<{
    members:     Member[]
    departments: Dept[]
}>()

// ── Form ───────────────────────────────────────────────────────────────────────

const form = useForm({
    title:         '',
    description:   '',
    priority:      'medium' as 'low' | 'medium' | 'high' | 'urgent',
    assigned_to:   null as number | null,
    department_id: null as number | null,
    due_at:        '',
})

// ── Priority options ───────────────────────────────────────────────────────────

const priorities = [
    { value: 'low',    label: 'Low',    icon: ArrowDown, classes: 'border-neutral-200 text-neutral-500' },
    { value: 'medium', label: 'Medium', icon: ArrowRight, classes: 'border-blue-200 text-blue-600' },
    { value: 'high',   label: 'High',   icon: ArrowUp,   classes: 'border-amber-300 text-amber-600' },
    { value: 'urgent', label: 'Urgent', icon: Flame,     classes: 'border-rose-300 text-rose-600' },
]

// ── Select options ─────────────────────────────────────────────────────────────

const memberOptions = computed(() => [
    { value: '', label: 'Unassigned' },
    ...props.members.map(m => ({ value: m.id, label: m.name })),
])

const deptOptions = computed(() => [
    { value: '', label: 'No department' },
    ...props.departments.map(d => ({ value: d.id, label: `${d.icon ?? '🏛'} ${d.name}` })),
])

// ── Submit ─────────────────────────────────────────────────────────────────────

function submit() {
    form.post('/dashboard/tasks')
}
</script>

<template>
    <DashboardLayout
        title="New Task"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Tasks',     href: '/dashboard/tasks' },
            { label: 'New' },
        ]"
    >
        <PageHeader title="New Task" description="Create a task and assign it to a church member.">
            <template #actions>
                <AppButton href="/dashboard/tasks" variant="outline" size="sm">
                    <ArrowLeft class="w-4 h-4" /> Back
                </AppButton>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-50">

                <!-- ── Section 1: Content ──────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Task details</h3>

                    <AppInput
                        label="Title"
                        v-model="form.title"
                        placeholder="e.g. Prepare slides for Sunday service"
                        :error="form.errors.title"
                        required
                    />

                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Description <span class="font-normal text-neutral-400">(optional)</span>
                        </label>
                        <AppTextarea
                            v-model="form.description"
                            placeholder="What needs to be done?"
                            :rows="4"
                            :error="form.errors.description"
                        />
                    </div>
                </div>

                <!-- ── Section 2: Priority ────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Priority</h3>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <label
                            v-for="p in priorities"
                            :key="p.value"
                            class="flex items-center justify-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-all text-sm font-medium"
                            :class="form.priority === p.value
                                ? `${p.classes} bg-opacity-10 ring-2 ring-current ring-opacity-20`
                                : 'border-neutral-200 text-neutral-500 hover:border-neutral-300'"
                        >
                            <input type="radio" v-model="form.priority" :value="p.value" class="sr-only" />
                            <component :is="p.icon" class="w-3.5 h-3.5" />
                            {{ p.label }}
                        </label>
                    </div>
                    <p v-if="form.errors.priority" class="text-xs text-rose-500">{{ form.errors.priority }}</p>
                </div>

                <!-- ── Section 3: Assignment ──────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Assignment</h3>

                    <AppSelect
                        label="Assign to"
                        v-model="form.assigned_to"
                        :options="memberOptions"
                        :error="form.errors.assigned_to"
                    />

                    <AppSelect
                        label="Department (optional)"
                        v-model="form.department_id"
                        :options="deptOptions"
                        :error="form.errors.department_id"
                    />
                </div>

                <!-- ── Section 4: Due date ────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Deadline</h3>

                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Due date <span class="font-normal text-neutral-400">(optional)</span>
                        </label>
                        <input
                            type="datetime-local"
                            v-model="form.due_at"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 transition-colors focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                            :class="{ 'border-rose-400': form.errors.due_at }"
                        />
                        <p v-if="form.errors.due_at" class="mt-1 text-xs text-rose-500">{{ form.errors.due_at }}</p>
                    </div>
                </div>

                <!-- ── Actions ─────────────────────────────────────────────── -->
                <div class="px-6 py-4 flex items-center justify-end gap-3">
                    <Link
                        href="/dashboard/tasks"
                        class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors"
                    >
                        Cancel
                    </Link>
                    <AppButton type="submit" :loading="form.processing">
                        Create task
                    </AppButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
