<script setup lang="ts">
/**
 * AttachmentList
 *
 * Renders the full file-management panel for a single resource (announcement,
 * event, task, or department).  Shows existing files, handles optimistic
 * removal on delete, and embeds the uploader when canUpload is true.
 *
 * Backend changes (upload / delete) are confirmed via server re-renders;
 * the local state is updated optimistically for immediate feedback.
 */

import { ref } from 'vue'
import axios from 'axios'
import { Paperclip } from 'lucide-vue-next'
import FileCard from '@/Components/Media/FileCard.vue'
import FileUploader from '@/Components/Media/FileUploader.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import type { FileAttachment } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    files: FileAttachment[]
    attachableType: string
    attachableId: number
    canUpload?: boolean
    isPublic?: boolean
}>()

const notify = useNotificationStore()

// ── Local file list (optimistically maintained) ────────────────────────────────

const localFiles = ref<FileAttachment[]>([...props.files])

// ── Upload ─────────────────────────────────────────────────────────────────────

function onUploaded(file: FileAttachment) {
    localFiles.value.unshift(file)
}

// ── Delete ─────────────────────────────────────────────────────────────────────

const deleting = ref<Set<number>>(new Set())

async function deleteFile(file: FileAttachment) {
    if (! confirm(`Delete "${file.original_name}"?`)) return

    deleting.value.add(file.id)

    try {
        await axios.delete(`/dashboard/files/${file.id}`)
        localFiles.value = localFiles.value.filter(f => f.id !== file.id)
    } catch {
        notify.error(`Could not delete "${file.original_name}". Please try again.`)
    } finally {
        deleting.value.delete(file.id)
    }
}
</script>

<template>
    <div class="bg-white border border-neutral-100 rounded-xl p-5 space-y-4">
        <!-- Header -->
        <h3 class="text-sm font-semibold text-neutral-900 flex items-center gap-2">
            <Paperclip class="w-4 h-4 text-neutral-400" />
            Attachments
            <span
                v-if="localFiles.length"
                class="ml-auto text-xs font-normal text-neutral-400"
            >
                {{ localFiles.length }} file{{ localFiles.length !== 1 ? 's' : '' }}
            </span>
        </h3>

        <!-- Uploader -->
        <FileUploader
            v-if="canUpload"
            :attachable-type="attachableType"
            :attachable-id="attachableId"
            :is-public="isPublic ?? false"
            @uploaded="onUploaded"
        />

        <!-- File list -->
        <div v-if="localFiles.length" class="space-y-2">
            <FileCard
                v-for="file in localFiles"
                :key="file.id"
                :file="file"
                :can-delete="canUpload"
                :deleting="deleting.has(file.id)"
                @delete="deleteFile"
            />
        </div>

        <!-- Empty state when no uploader -->
        <p
            v-else-if="!canUpload"
            class="text-sm text-neutral-400 italic text-center py-2"
        >
            No attachments.
        </p>
    </div>
</template>
