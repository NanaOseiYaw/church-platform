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
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import { FileText, Plus, Pencil, Trash2 } from 'lucide-vue-next'
import type { BroadcastTemplate } from '@/types'

const props = defineProps<{
    templates: BroadcastTemplate[]
    canManage: boolean
}>()

// ── Modal state ────────────────────────────────────────────────────────────────
const showModal  = ref(false)
const editTarget = ref<BroadcastTemplate | null>(null)

const form = useForm({
    name:     '',
    subject:  '',
    body:     '',
    category: '',
})

function openCreate() {
    editTarget.value = null
    form.reset()
    showModal.value = true
}

function openEdit(t: BroadcastTemplate) {
    editTarget.value = t
    form.name     = t.name
    form.subject  = t.subject
    form.body     = t.body
    form.category = t.category ?? ''
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    editTarget.value = null
    form.reset()
}

function save() {
    if (editTarget.value) {
        form.put(`/dashboard/communication/templates/${editTarget.value.id}`, { onSuccess: closeModal })
    } else {
        form.post('/dashboard/communication/templates', { onSuccess: closeModal })
    }
}

function deleteTemplate(t: BroadcastTemplate) {
    if (!confirm(`Delete template "${t.name}"?`)) return
    useForm({}).delete(`/dashboard/communication/templates/${t.id}`)
}
</script>

<template>
    <DashboardLayout title="Templates">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-6">

            <PageHeader title="Templates" description="Reusable message templates to speed up your broadcasts.">
                <template #actions>
                    <AppButton v-if="canManage" size="sm" @click="openCreate">
                        <Plus class="w-4 h-4" /> New Template
                    </AppButton>
                </template>
            </PageHeader>

            <EmptyState
                v-if="templates.length === 0"
                :icon="FileText"
                title="No templates yet"
                description="Create a template to speed up your broadcasts."
            >
                <template #action>
                    <AppButton v-if="canManage" size="sm" @click="openCreate">
                        <Plus class="w-4 h-4" /> New Template
                    </AppButton>
                </template>
            </EmptyState>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div
                    v-for="t in templates"
                    :key="t.id"
                    class="bg-white border border-neutral-200 rounded-xl p-4 flex flex-col gap-2"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium text-neutral-800 truncate">{{ t.name }}</p>
                            <p class="text-xs text-neutral-500 truncate">{{ t.subject }}</p>
                        </div>
                        <div v-if="canManage" class="flex gap-1 shrink-0">
                            <AppButton square size="sm" variant="ghost" @click="openEdit(t)">
                                <Pencil class="w-3.5 h-3.5" />
                            </AppButton>
                            <AppButton
                                square
                                size="sm"
                                variant="ghost"
                                class="!text-neutral-400 hover:!text-rose-600 hover:!bg-rose-50"
                                @click="deleteTemplate(t)"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </AppButton>
                        </div>
                    </div>
                    <p class="text-xs text-neutral-500 line-clamp-3">{{ t.body }}</p>
                    <div class="flex items-center gap-2 mt-auto pt-1">
                        <AppBadge v-if="t.category" color="neutral" size="sm">{{ t.category }}</AppBadge>
                        <span class="text-xs text-neutral-400 ml-auto">Used {{ t.usage_count }}×</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <AppModal :open="showModal" :title="editTarget ? 'Edit Template' : 'New Template'" @close="closeModal">
            <div class="space-y-4">
                <AppInput v-model="form.name" label="Name" :error="form.errors.name" />
                <AppInput v-model="form.subject" label="Subject" :error="form.errors.subject" />
                <AppTextarea v-model="form.body" label="Body" :rows="5" :error="form.errors.body" />
                <AppInput v-model="form.category" label="Category (optional)" :error="form.errors.category" />
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
