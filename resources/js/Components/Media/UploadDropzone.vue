<script setup lang="ts">
import { ref } from 'vue'
import { UploadCloud } from 'lucide-vue-next'

// ── Props / emits ──────────────────────────────────────────────────────────────

// Assign to `props` so the disabled flag is accessible inside event handlers.
const props = withDefaults(defineProps<{
    accept?: string
    multiple?: boolean
    disabled?: boolean
}>(), {
    accept: '*/*',
    multiple: true,
    disabled: false,
})

const emit = defineEmits<{
    'files-selected': [files: File[]]
}>()

// ── Drag-and-drop state ────────────────────────────────────────────────────────

const isDragging = ref(false)
const inputRef   = ref<HTMLInputElement | null>(null)

function onDragOver(e: DragEvent) {
    // Prevent default so the browser shows the drop cursor, but only activate
    // the drag-highlight when the zone is not disabled.
    if (props.disabled) return
    e.preventDefault()
    isDragging.value = true
}

function onDragLeave() {
    isDragging.value = false
}

function onDrop(e: DragEvent) {
    e.preventDefault()
    isDragging.value = false
    // Guard here too — the browser fires drop even when the button is :disabled
    // because CSS pointer-events alone does not cancel the drag-and-drop API.
    if (props.disabled) return
    const files = Array.from(e.dataTransfer?.files ?? [])
    if (files.length) emit('files-selected', files)
}

function onInputChange(e: Event) {
    const files = Array.from((e.target as HTMLInputElement).files ?? [])
    if (files.length) emit('files-selected', files)
    // Reset so the same file can be re-selected
    if (inputRef.value) inputRef.value.value = ''
}

function openFileDialog() {
    inputRef.value?.click()
}
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :class="[
            'w-full rounded-xl border-2 border-dashed py-6 px-4 flex flex-col items-center gap-2 transition-colors',
            isDragging
                ? 'border-brand-400 bg-brand-50'
                : 'border-neutral-200 hover:border-brand-300 hover:bg-neutral-50',
            disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
        ]"
        @click="openFileDialog"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
    >
        <UploadCloud
            :class="['w-8 h-8', isDragging ? 'text-brand-500' : 'text-neutral-300']"
        />
        <span class="text-sm text-neutral-500">
            <span class="font-medium text-brand-600">Click to upload</span>
            &nbsp;or drag and drop
        </span>
        <span class="text-xs text-neutral-400">Max 50 MB per file</span>
    </button>

    <input
        ref="inputRef"
        type="file"
        class="hidden"
        :accept="accept"
        :multiple="multiple"
        :disabled="disabled"
        @change="onInputChange"
    />
</template>
