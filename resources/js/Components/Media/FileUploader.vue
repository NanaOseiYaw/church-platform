<script setup lang="ts">
/**
 * FileUploader
 *
 * Handles the actual Axios upload with per-file progress tracking.
 * Emits `uploaded` with the FileAttachment DTO returned by the server.
 *
 * Usage:
 *   <FileUploader
 *     attachable-type="announcement"
 *     :attachable-id="announcement.id"
 *     @uploaded="onFileUploaded"
 *   />
 */

import { ref } from 'vue'
import axios from 'axios'
import { CheckCircle2, XCircle, Loader2, X } from 'lucide-vue-next'
import UploadDropzone from '@/Components/Media/UploadDropzone.vue'
import type { FileAttachment } from '@/types'

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
    attachableType: string
    attachableId: number
    isPublic?: boolean
    multiple?: boolean
}>(), {
    isPublic: false,
    multiple: true,
})

const emit = defineEmits<{
    uploaded: [file: FileAttachment]
}>()

// ── Upload queue ───────────────────────────────────────────────────────────────

interface QueueItem {
    id: string
    name: string
    size: number
    progress: number
    status: 'pending' | 'uploading' | 'done' | 'error'
    errorMessage?: string
}

const queue = ref<QueueItem[]>([])

function humanSize(bytes: number): string {
    const kb = bytes / 1024
    return kb < 1024 ? `${Math.round(kb)} KB` : `${(kb / 1024).toFixed(1)} MB`
}

// ── Upload handler ─────────────────────────────────────────────────────────────

async function uploadFiles(files: File[]) {
    for (const file of files) {
        const id = Math.random().toString(36).slice(2)
        const item: QueueItem = {
            id,
            name:   file.name,
            size:   file.size,
            progress: 0,
            status: 'uploading',
        }
        queue.value.push(item)

        const formData = new FormData()
        formData.append('file',            file)
        formData.append('attachable_type', props.attachableType)
        formData.append('attachable_id',   String(props.attachableId))
        formData.append('is_public',       props.isPublic ? '1' : '0')

        try {
            const response = await axios.post<FileAttachment>('/dashboard/files', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                onUploadProgress(e) {
                    const pct = e.total ? Math.round((e.loaded / e.total) * 100) : 0
                    const idx = queue.value.findIndex(q => q.id === id)
                    if (idx !== -1) queue.value[idx].progress = pct
                },
            })

            const idx = queue.value.findIndex(q => q.id === id)
            if (idx !== -1) {
                queue.value[idx].status   = 'done'
                queue.value[idx].progress = 100
            }

            emit('uploaded', response.data)
        } catch (err: unknown) {
            const idx = queue.value.findIndex(q => q.id === id)
            if (idx !== -1) {
                queue.value[idx].status = 'error'
                const msg = (err as { response?: { data?: { message?: string } } })
                    ?.response?.data?.message ?? 'Upload failed.'
                queue.value[idx].errorMessage = msg
            }
        }
    }
}

function removeFromQueue(id: string) {
    queue.value = queue.value.filter(q => q.id !== id)
}
</script>

<template>
    <div class="space-y-3">
        <UploadDropzone
            :multiple="multiple"
            :disabled="queue.some(q => q.status === 'uploading')"
            @files-selected="uploadFiles"
        />

        <!-- Upload queue -->
        <div v-if="queue.length" class="space-y-2">
            <div
                v-for="item in queue"
                :key="item.id"
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg border bg-white"
                :class="{
                    'border-neutral-100': item.status !== 'error',
                    'border-rose-100 bg-rose-50': item.status === 'error',
                }"
            >
                <!-- Status icon -->
                <div class="shrink-0">
                    <CheckCircle2 v-if="item.status === 'done'"     class="w-4 h-4 text-emerald-500" />
                    <XCircle      v-else-if="item.status === 'error'" class="w-4 h-4 text-rose-400" />
                    <Loader2      v-else class="w-4 h-4 text-brand-500 animate-spin" />
                </div>

                <!-- File info -->
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-neutral-800 truncate">{{ item.name }}</p>
                    <p
                        v-if="item.status === 'error'"
                        class="text-xs text-rose-500 mt-0.5"
                    >{{ item.errorMessage }}</p>
                    <template v-else>
                        <p class="text-xs text-neutral-400">{{ humanSize(item.size) }}</p>
                        <!-- Progress bar -->
                        <div v-if="item.status === 'uploading'" class="mt-1.5 h-1 rounded-full bg-neutral-100 overflow-hidden">
                            <div
                                class="h-full bg-brand-500 rounded-full transition-all duration-200"
                                :style="{ width: item.progress + '%' }"
                            />
                        </div>
                    </template>
                </div>

                <!-- Dismiss -->
                <button
                    v-if="item.status === 'done' || item.status === 'error'"
                    type="button"
                    class="shrink-0 p-1 rounded text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 transition-colors"
                    @click="removeFromQueue(item.id)"
                >
                    <X class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
