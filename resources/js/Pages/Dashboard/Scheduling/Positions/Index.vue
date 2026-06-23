<!-- resources/js/Pages/Dashboard/Scheduling/Positions/Index.vue -->
<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Plus, Pencil, Trash2, X } from 'lucide-vue-next'
import type { ServingPosition } from '@/types'

interface Dept { id: number; name: string; icon: string | null; color: string | null }

const props = defineProps<{
    positions: ServingPosition[]
    departments: Dept[]
}>()

// Group positions by department
function positionsFor(deptId: number) {
    return props.positions.filter(p => p.department_id === deptId)
}

// ── Create / Edit form ─────────────────────────────────────────────────────────

const showForm  = ref(false)
const editingId = ref<number | null>(null)

const form = useForm({
    department_id: null as number | null,
    name:          '',
    description:   '',
    sort_order:    0,
    is_active:     true,
})

function openCreate(deptId?: number) {
    editingId.value = null
    form.reset()
    form.department_id = deptId ?? null
    showForm.value = true
}

function openEdit(pos: ServingPosition) {
    editingId.value = pos.id
    form.department_id = pos.department_id
    form.name = pos.name
    form.description = pos.description ?? ''
    form.sort_order = pos.sort_order
    form.is_active = pos.is_active
    showForm.value = true
}

function submitForm() {
    if (editingId.value) {
        form.put(`/dashboard/scheduling/positions/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false },
        })
    } else {
        form.post('/dashboard/scheduling/positions', {
            preserveScroll: true,
            onSuccess: () => { showForm.value = false },
        })
    }
}

function deletePosition(pos: ServingPosition) {
    if (!confirm(`Delete "${pos.name}"?`)) return
    router.delete(`/dashboard/scheduling/positions/${pos.id}`, { preserveScroll: true })
}
</script>

<template>
    <DashboardLayout title="Position Library">
        <PageHeader title="Position Library">
            <template #actions>
                <AppButton variant="primary" @click="openCreate()">
                    <Plus class="h-4 w-4" />
                    New Position
                </AppButton>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <div v-for="dept in departments" :key="dept.id" class="rounded-xl border border-neutral-100 bg-white shadow-sm">
                <!-- Dept header -->
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <p class="font-medium text-neutral-900">{{ dept.name }}</p>
                    <button
                        type="button"
                        class="flex items-center gap-1 text-xs text-brand-600 hover:underline"
                        @click="openCreate(dept.id)"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        Add position
                    </button>
                </div>

                <!-- Position rows -->
                <ul>
                    <li
                        v-for="pos in positionsFor(dept.id)"
                        :key="pos.id"
                        class="flex items-center gap-3 border-b border-gray-50 px-5 py-3 last:border-0"
                        :class="{ 'opacity-50': !pos.is_active }"
                    >
                        <div class="flex-1">
                            <p class="text-sm font-medium text-neutral-800">
                                {{ pos.name }}
                                <span v-if="!pos.is_active" class="ml-1 text-xs text-neutral-400">(inactive)</span>
                            </p>
                            <p v-if="pos.description" class="text-xs text-neutral-500">{{ pos.description }}</p>
                        </div>
                        <button @click="openEdit(pos)" class="rounded p-1 text-neutral-400 hover:text-brand-600">
                            <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button @click="deletePosition(pos)" class="rounded p-1 text-neutral-400 hover:text-red-500">
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </li>
                    <li v-if="positionsFor(dept.id).length === 0" class="px-5 py-4 text-sm text-neutral-400">
                        No positions yet.
                    </li>
                </ul>
            </div>

            <p v-if="departments.length === 0" class="text-center text-sm text-neutral-400 py-12">
                No departments found. Create a department first.
            </p>
        </div>

        <!-- Create / Edit modal -->
        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="w-full max-w-md rounded-xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
                        <h2 class="font-semibold text-neutral-900">{{ editingId ? 'Edit Position' : 'New Position' }}</h2>
                        <button @click="showForm = false" class="text-neutral-400 hover:text-neutral-600">
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <form @submit.prevent="submitForm" class="space-y-4 px-5 py-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Department</label>
                            <select
                                v-model="form.department_id"
                                required
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                                :class="{ 'border-red-400': form.errors.department_id }"
                            >
                                <option :value="null" disabled>Select department…</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                            <p v-if="form.errors.department_id" class="mt-1 text-xs text-red-500">{{ form.errors.department_id }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Position Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Camera Operator"
                                required
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                                :class="{ 'border-red-400': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-neutral-700">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none"
                            />
                        </div>
                        <div class="flex items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded border-neutral-300" />
                            <label for="is_active" class="text-sm text-neutral-700">Active (available for new plans)</label>
                        </div>
                        <div class="flex justify-end gap-3 pt-1">
                            <AppButton variant="ghost" type="button" @click="showForm = false">Cancel</AppButton>
                            <AppButton variant="primary" type="submit" :disabled="form.processing">
                                {{ editingId ? 'Save Changes' : 'Create Position' }}
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </DashboardLayout>
</template>
