<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import FileCard from '@/Components/Media/FileCard.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { FolderOpen, Search, Image, Music, Video, FileText, File } from 'lucide-vue-next'
import type { FileAttachment } from '@/types'
import axios from 'axios'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    files: {
        data:         FileAttachment[]
        links:        any
        meta:         any
        current_page: number
        last_page:    number
        total:        number
    }
    typeCounts: {
        all:      number
        image:    number
        audio:    number
        video:    number
        document: number
    }
    filters:   { search?: string; type?: string }
    canDelete: boolean
}>()

// ── Filter state ───────────────────────────────────────────────────────────────

const search     = ref(props.filters.search ?? '')
const activeType = ref(props.filters.type  ?? '')

function applyFilters() {
    router.get('/dashboard/media', {
        search: search.value   || undefined,
        type:   activeType.value || undefined,
    }, { preserveState: true, replace: true })
}

let debounce: ReturnType<typeof setTimeout>
watch(search, () => {
    clearTimeout(debounce)
    debounce = setTimeout(applyFilters, 320)
})
watch(activeType, applyFilters)

// ── Type tabs ─────────────────────────────────────────────────────────────────

const tabs = [
    { key: '',         label: 'All',       icon: File      },
    { key: 'image',    label: 'Images',    icon: Image     },
    { key: 'audio',    label: 'Audio',     icon: Music     },
    { key: 'video',    label: 'Video',     icon: Video     },
    { key: 'document', label: 'Documents', icon: FileText  },
] as const

function tabCount(key: string): number {
    if (!key) return props.typeCounts.all
    return props.typeCounts[key as keyof typeof props.typeCounts] ?? 0
}

// ── Delete ─────────────────────────────────────────────────────────────────────

const notify   = useNotificationStore()
const deleting = ref<Set<number>>(new Set())

// Optimistic local list so the page re-renders immediately on delete
const localFiles = ref<FileAttachment[]>([...props.files.data])

// Sync if Inertia navigates (filters change, pagination, etc.)
watch(() => props.files.data, (fresh) => {
    localFiles.value = [...fresh]
})

async function deleteFile(file: FileAttachment) {
    if (!confirm(`Delete "${file.original_name}"? This cannot be undone.`)) return

    deleting.value.add(file.id)
    try {
        await axios.delete(`/dashboard/files/${file.id}`)
        localFiles.value = localFiles.value.filter(f => f.id !== file.id)
        notify.success(`"${file.original_name}" deleted.`)
    } catch {
        notify.error(`Could not delete "${file.original_name}". Please try again.`)
    } finally {
        deleting.value.delete(file.id)
    }
}
</script>

<template>
    <DashboardLayout
        title="Media Library"
        :breadcrumbs="[{ label: 'Media Library' }]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">Media Library</h1>
                <p class="text-sm text-neutral-500 mt-0.5">
                    {{ typeCounts.all }} file{{ typeCounts.all !== 1 ? 's' : '' }} across all resources
                </p>
            </div>
        </div>

        <!-- Filters row: type tabs + search -->
        <div class="flex flex-wrap items-center gap-3 mb-6">

            <!-- Type filter pills -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                        activeType === tab.key
                            ? 'bg-brand-600 text-white shadow-sm'
                            : 'bg-white border border-neutral-200 text-neutral-600 hover:bg-neutral-50',
                    ]"
                    @click="activeType = tab.key"
                >
                    <component :is="tab.icon" class="w-3.5 h-3.5" />
                    {{ tab.label }}
                    <span
                        :class="[
                            'text-[10px] font-semibold tabular-nums px-1.5 py-0.5 rounded-full',
                            activeType === tab.key
                                ? 'bg-white/20 text-white'
                                : 'bg-neutral-100 text-neutral-500',
                        ]"
                    >
                        {{ tabCount(tab.key) }}
                    </span>
                </button>
            </div>

            <!-- Spacer -->
            <div class="flex-1" />

            <!-- Search -->
            <div class="relative w-full sm:w-56">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search files…"
                    class="w-full h-9 pl-9 pr-3 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                />
            </div>
        </div>

        <!-- Empty state -->
        <EmptyState
            v-if="localFiles.length === 0"
            :icon="FolderOpen"
            title="No files found"
            :description="filters.search || filters.type
                ? 'Try adjusting your search or filter.'
                : 'No files have been uploaded yet.'"
        />

        <!-- File grid -->
        <div v-else class="space-y-2 mb-6">
            <FileCard
                v-for="file in localFiles"
                :key="file.id"
                :file="file"
                :can-delete="canDelete"
                :deleting="deleting.has(file.id)"
                @delete="deleteFile"
            />
        </div>

        <!-- Pagination -->
        <AppPagination
            v-if="files.last_page > 1"
            :links="files.links"
            :current-page="files.current_page"
            :last-page="files.last_page"
        />
    </DashboardLayout>
</template>
