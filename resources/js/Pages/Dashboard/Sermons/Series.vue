<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import {
    ArrowLeft, Plus, Pencil, Trash2, BookOpen, ToggleLeft, ToggleRight, Mic2,
} from 'lucide-vue-next'
import type { SermonSeries } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    seriesList: SermonSeries[]
}>()

// ── Create modal ───────────────────────────────────────────────────────────────

const showCreate = ref(false)

const createForm = useForm({
    title:       '',
    description: '',
    started_at:  '',
    ended_at:    '',
    sort_order:  0,
    is_active:   true,
})

function openCreate() {
    createForm.reset()
    showCreate.value = true
}

function submitCreate() {
    createForm
        .transform(data => ({
            ...data,
            description: data.description || null,
            started_at:  data.started_at  || null,
            ended_at:    data.ended_at    || null,
        }))
        .post('/dashboard/sermons/series', {
            preserveScroll: true,
            onSuccess: () => {
                showCreate.value = false
                createForm.reset()
            },
        })
}

// ── Edit modal ─────────────────────────────────────────────────────────────────

const editTarget = ref<SermonSeries | null>(null)

const editForm = useForm({
    title:       '',
    description: '',
    started_at:  '',
    ended_at:    '',
    sort_order:  0,
    is_active:   true,
})

function openEdit(series: SermonSeries) {
    editTarget.value = series
    editForm.title       = series.title
    editForm.description = series.description ?? ''
    editForm.started_at  = series.started_at  ?? ''
    editForm.ended_at    = series.ended_at    ?? ''
    editForm.sort_order  = series.sort_order
    editForm.is_active   = series.is_active
}

function submitEdit() {
    if (! editTarget.value) return
    editForm
        .transform(data => ({
            ...data,
            description: data.description || null,
            started_at:  data.started_at  || null,
            ended_at:    data.ended_at    || null,
        }))
        .put(`/dashboard/sermons/series/${editTarget.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editTarget.value = null
            },
        })
}

// ── Delete ─────────────────────────────────────────────────────────────────────

function deleteSeries(series: SermonSeries) {
    const count = series.sermon_count ?? 0
    const msg = count > 0
        ? `Delete "${series.title}"? This will unlink ${count} sermon${count !== 1 ? 's' : ''} from the series (they won't be deleted).`
        : `Delete "${series.title}"?`
    if (! confirm(msg)) return

    router.delete(`/dashboard/sermons/series/${series.id}`, {
        preserveScroll: true,
    })
}
</script>

<template>
    <DashboardLayout
        title="Sermon Series"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Sermons',   href: '/dashboard/sermons' },
            { label: 'Series' },
        ]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">Sermon Series</h1>
                <p class="text-sm text-neutral-500 mt-0.5">
                    Organise sermons into teaching series.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <AppButton href="/dashboard/sermons" variant="outline" size="sm">
                    <ArrowLeft class="w-4 h-4" /> Back
                </AppButton>
                <AppButton variant="primary" size="sm" @click="openCreate">
                    <Plus class="w-4 h-4" />
                    New Series
                </AppButton>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="seriesList.length === 0"
            class="bg-neutral-50 border-2 border-dashed border-neutral-200 rounded-2xl p-12 text-center"
        >
            <BookOpen class="w-8 h-8 text-neutral-300 mx-auto mb-3" />
            <p class="text-sm font-medium text-neutral-500">No series yet</p>
            <p class="text-xs text-neutral-400 mt-1 mb-4">
                Create a series to group sermons into teaching collections.
            </p>
            <AppButton variant="primary" size="sm" @click="openCreate">
                <Plus class="w-4 h-4" /> Create first series
            </AppButton>
        </div>

        <!-- Series list -->
        <div v-else class="space-y-3">
            <div
                v-for="series in seriesList"
                :key="series.id"
                class="bg-white border border-neutral-100 rounded-xl p-4 flex items-center gap-4"
                :class="{ 'opacity-60': !series.is_active }"
            >
                <!-- Cover / icon -->
                <div class="w-12 h-12 rounded-xl bg-brand-50 flex items-center justify-center shrink-0 overflow-hidden">
                    <img
                        v-if="series.cover_image"
                        :src="series.cover_image"
                        :alt="series.title"
                        class="w-full h-full object-cover"
                    />
                    <BookOpen v-else class="w-5 h-5 text-brand-400" />
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-semibold text-neutral-900 truncate">{{ series.title }}</p>

                        <!-- Active badge -->
                        <span
                            :class="[
                                'inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full',
                                series.is_active
                                    ? 'bg-green-50 text-green-700'
                                    : 'bg-neutral-100 text-neutral-400',
                            ]"
                        >
                            <component :is="series.is_active ? ToggleRight : ToggleLeft" class="w-3 h-3" />
                            {{ series.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3 mt-0.5 text-xs text-neutral-400">
                        <!-- Sermon count -->
                        <span class="flex items-center gap-1">
                            <Mic2 class="w-3 h-3" />
                            {{ series.sermon_count ?? 0 }} sermon{{ (series.sermon_count ?? 0) !== 1 ? 's' : '' }}
                        </span>
                        <!-- Date range -->
                        <span v-if="series.started_at">
                            {{ series.started_at }}
                            <span v-if="series.ended_at"> – {{ series.ended_at }}</span>
                        </span>
                        <!-- Description excerpt -->
                        <span v-if="series.description" class="truncate max-w-xs hidden sm:inline">
                            {{ series.description }}
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 shrink-0">
                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-neutral-400 hover:text-brand-600 hover:bg-brand-50 transition-colors"
                        title="Edit series"
                        @click="openEdit(series)"
                    >
                        <Pencil class="w-4 h-4" />
                    </button>
                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-500 hover:bg-rose-50 transition-colors"
                        title="Delete series"
                        @click="deleteSeries(series)"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Create modal ────────────────────────────────────────────────── -->
        <AppModal :open="showCreate" title="New Series" @close="showCreate = false">
            <form @submit.prevent="submitCreate" class="space-y-4">
                <AppInput
                    label="Title"
                    v-model="createForm.title"
                    placeholder="e.g. Foundations of Faith"
                    :error="createForm.errors.title"
                    required
                />
                <AppTextarea
                    label="Description"
                    v-model="createForm.description"
                    placeholder="Brief description of this series…"
                    :rows="3"
                    :error="createForm.errors.description"
                />
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-neutral-700">Starts</label>
                        <input
                            v-model="createForm.started_at"
                            type="date"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-neutral-700">Ends</label>
                        <input
                            v-model="createForm.ended_at"
                            type="date"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="create-active"
                        v-model="createForm.is_active"
                        class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                    />
                    <label for="create-active" class="text-sm text-neutral-700 cursor-pointer">
                        Active (visible on public website)
                    </label>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors"
                        @click="showCreate = false"
                    >
                        Cancel
                    </button>
                    <AppButton :loading="createForm.processing" @click="submitCreate">
                        Create series
                    </AppButton>
                </div>
            </template>
        </AppModal>

        <!-- ── Edit modal ──────────────────────────────────────────────────── -->
        <AppModal :open="!!editTarget" :title="`Edit — ${editTarget?.title}`" @close="editTarget = null">
            <form v-if="editTarget" @submit.prevent="submitEdit" class="space-y-4">
                <AppInput
                    label="Title"
                    v-model="editForm.title"
                    :error="editForm.errors.title"
                    required
                />
                <AppTextarea
                    label="Description"
                    v-model="editForm.description"
                    :rows="3"
                    :error="editForm.errors.description"
                />
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-neutral-700">Starts</label>
                        <input
                            v-model="editForm.started_at"
                            type="date"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10"
                        />
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-neutral-700">Ends</label>
                        <input
                            v-model="editForm.ended_at"
                            type="date"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="edit-active"
                        v-model="editForm.is_active"
                        class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                    />
                    <label for="edit-active" class="text-sm text-neutral-700 cursor-pointer">
                        Active
                    </label>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors"
                        @click="editTarget = null"
                    >
                        Cancel
                    </button>
                    <AppButton :loading="editForm.processing" @click="submitEdit">
                        Save changes
                    </AppButton>
                </div>
            </template>
        </AppModal>
    </DashboardLayout>
</template>
