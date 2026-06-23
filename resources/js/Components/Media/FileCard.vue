<script setup lang="ts">
import { ref } from 'vue'
import { Download, Loader2, Trash2 } from 'lucide-vue-next'
import FileIcon from '@/Components/Media/FileIcon.vue'
import ImagePreview from '@/Components/Media/ImagePreview.vue'
import type { FileAttachment } from '@/types'

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = defineProps<{
    file: FileAttachment
    canDelete?: boolean
    /** True while the parent is waiting for the DELETE request to resolve. */
    deleting?: boolean
}>()

const emit = defineEmits<{
    delete: [file: FileAttachment]
}>()

// ── Image preview lightbox ─────────────────────────────────────────────────────

const previewOpen = ref(false)

function handleClick() {
    if (props.file.is_image) {
        previewOpen.value = true
    } else {
        window.open(props.file.url, '_blank')
    }
}
</script>

<template>
    <div
        :class="[
            'group flex items-center gap-3 p-3 rounded-lg border border-neutral-100 bg-white transition-colors',
            deleting
                ? 'opacity-60'
                : 'hover:border-neutral-200 hover:bg-neutral-50/50',
        ]"
    >

        <!-- Icon -->
        <button
            type="button"
            class="shrink-0 focus:outline-none"
            :title="file.is_image ? 'Preview' : 'Open'"
            @click="handleClick"
        >
            <FileIcon :file-type="file.file_type" size="md" />
        </button>

        <!-- Name + meta -->
        <div class="flex-1 min-w-0">
            <p
                class="text-sm font-medium text-neutral-800 truncate leading-tight cursor-pointer hover:text-brand-600 transition-colors"
                @click="handleClick"
            >
                {{ file.original_name }}
            </p>
            <p class="text-xs text-neutral-400 mt-0.5 flex items-center gap-1.5">
                <span>{{ file.formatted_size }}</span>
                <span class="text-neutral-200">·</span>
                <span>{{ file.uploaded_at_formatted }}</span>
                <template v-if="file.uploader">
                    <span class="text-neutral-200">·</span>
                    <span>{{ file.uploader.name }}</span>
                </template>
            </p>
        </div>

        <!-- Actions — always visible while deleting so the spinner shows; otherwise hover-revealed -->
        <div
            :class="[
                'flex items-center gap-1 shrink-0 transition-opacity',
                deleting ? 'opacity-100' : 'opacity-0 group-hover:opacity-100',
            ]"
        >
            <a
                :href="file.url"
                download
                class="p-1.5 rounded text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 transition-colors"
                title="Download"
                @click.stop
            >
                <Download class="w-3.5 h-3.5" />
            </a>

            <button
                v-if="canDelete"
                type="button"
                :disabled="deleting"
                :class="[
                    'p-1.5 rounded transition-colors',
                    deleting
                        ? 'text-neutral-300 cursor-default'
                        : 'text-neutral-400 hover:text-rose-600 hover:bg-rose-50',
                ]"
                :title="deleting ? 'Deleting…' : 'Delete'"
                @click.stop="!deleting && emit('delete', file)"
            >
                <Loader2 v-if="deleting" class="w-3.5 h-3.5 animate-spin" />
                <Trash2  v-else          class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>

    <!-- Image lightbox -->
    <ImagePreview
        v-if="previewOpen && file.is_image"
        :src="file.url"
        :alt="file.original_name"
        :download-url="file.url"
        @close="previewOpen = false"
    />
</template>
