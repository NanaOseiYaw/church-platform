<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppBadge from '@/Components/UI/AppBadge.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { Users, Plus, Pencil, Trash2 } from 'lucide-vue-next'
import type { BroadcastAudience } from '@/types'

const props = defineProps<{
    audiences: BroadcastAudience[]
    departments: { id: number; name: string }[]
    canManage: boolean
}>()

const audienceTypeLabel = (t: string) => ({
    all_members:    'All Members',
    department:     'Department',
    role:           'By Role',
    event_attendees:'Event Attendees',
    volunteers:     'Volunteers',
})[t] ?? t

// ── Modal ──────────────────────────────────────────────────────────────────────
const showModal  = ref(false)
const editTarget = ref<BroadcastAudience | null>(null)

const form = useForm({
    name:            '',
    description:     '',
    audience_type:   'all_members',
    audience_config: null as Record<string, any> | null,
})

function openCreate() {
    editTarget.value = null
    form.reset()
    showModal.value = true
}

function openEdit(a: BroadcastAudience) {
    editTarget.value = a
    form.name            = a.name
    form.description     = a.description ?? ''
    form.audience_type   = a.audience_type
    form.audience_config = a.audience_config
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.reset()
    editTarget.value = null
}

function save() {
    if (editTarget.value) {
        form.put(`/dashboard/communication/audiences/${editTarget.value.id}`, { onSuccess: closeModal })
    } else {
        form.post('/dashboard/communication/audiences', { onSuccess: closeModal })
    }
}

function deleteAudience(a: BroadcastAudience) {
    if (!confirm(`Delete "${a.name}"?`)) return
    useForm({}).delete(`/dashboard/communication/audiences/${a.id}`)
}
</script>

<template>
    <DashboardLayout title="Saved Audiences">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-6">

            <PageHeader title="Saved Audiences" description="Reusable audience presets for your broadcasts.">
                <template #actions>
                    <AppButton v-if="canManage" size="sm" @click="openCreate">
                        <Plus class="w-4 h-4" /> New Audience
                    </AppButton>
                </template>
            </PageHeader>

            <EmptyState
                v-if="audiences.length === 0"
                :icon="Users"
                title="No saved audiences yet"
                description="Create one to reuse audience presets across broadcasts."
            >
                <template #action>
                    <AppButton v-if="canManage" size="sm" @click="openCreate">
                        <Plus class="w-4 h-4" /> New Audience
                    </AppButton>
                </template>
            </EmptyState>

            <div v-else class="space-y-3">
                <div
                    v-for="a in audiences"
                    :key="a.id"
                    class="bg-white border border-neutral-200 rounded-xl px-4 py-3 flex items-center justify-between gap-3"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">{{ a.name }}</p>
                        <p v-if="a.description" class="text-xs text-neutral-500 truncate">{{ a.description }}</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <AppBadge color="neutral" size="sm">{{ audienceTypeLabel(a.audience_type) }}</AppBadge>
                        <span class="text-xs text-neutral-500">{{ a.member_count }} members</span>
                        <div v-if="canManage" class="flex gap-1">
                            <AppButton square size="sm" variant="ghost" @click="openEdit(a)">
                                <Pencil class="w-3.5 h-3.5" />
                            </AppButton>
                            <AppButton
                                square
                                size="sm"
                                variant="ghost"
                                class="!text-neutral-400 hover:!text-rose-600 hover:!bg-rose-50"
                                @click="deleteAudience(a)"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </AppButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <AppModal :open="showModal" :title="editTarget ? 'Edit Audience' : 'New Saved Audience'" @close="closeModal">
            <div class="space-y-4">
                <AppInput v-model="form.name" label="Name" :error="form.errors.name" />
                <AppInput v-model="form.description" label="Description (optional)" />
                <AppSelect v-model="form.audience_type" label="Audience Type">
                    <option value="all_members">All Members</option>
                    <option value="department">Department</option>
                    <option value="role">By Role</option>
                    <option value="event_attendees">Event Attendees</option>
                    <option value="volunteers">Volunteers</option>
                </AppSelect>
                <!-- Department sub-picker -->
                <AppSelect
                    v-if="form.audience_type === 'department'"
                    label="Department"
                    :model-value="(form.audience_config?.department_id as number | undefined) ?? ''"
                    @update:model-value="form.audience_config = { department_id: Number($event) }"
                >
                    <option value="">— choose —</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </AppSelect>
            </div>
            <template #footer>
                <div class="flex gap-2">
                    <AppButton variant="outline" class="flex-1" @click="closeModal">Cancel</AppButton>
                    <AppButton class="flex-1" :loading="form.processing" @click="save">
                        {{ editTarget ? 'Update' : 'Create' }}
                    </AppButton>
                </div>
            </template>
        </AppModal>
    </DashboardLayout>
</template>
