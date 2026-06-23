<script setup lang="ts">
import { type Component } from 'vue'
import type { FileType } from '@/types'
// Rename `File` → `FileGenericIcon` to avoid shadowing the native DOM `File`
// constructor, which is used in sibling components (UploadDropzone, FileUploader).
import {
    FileText, FileImage, FileAudio, FileVideo, File as FileGenericIcon,
} from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
    fileType?: FileType
    size?: 'sm' | 'md' | 'lg'
}>(), {
    fileType: 'document',
    size: 'md',
})

// ── Derived ────────────────────────────────────────────────────────────────────

const sizeClasses = {
    sm: 'w-4 h-4',
    md: 'w-5 h-5',
    lg: 'w-6 h-6',
}

const config: Record<FileType, { component: Component; iconClass: string; bgClass: string }> = {
    image:    { component: FileImage,       iconClass: 'text-brand-600', bgClass: 'bg-brand-50'   },
    pdf:      { component: FileText,        iconClass: 'text-rose-600',   bgClass: 'bg-rose-50'     },
    audio:    { component: FileAudio,       iconClass: 'text-amber-600',  bgClass: 'bg-amber-50'    },
    video:    { component: FileVideo,       iconClass: 'text-blue-600',   bgClass: 'bg-blue-50'     },
    document: { component: FileGenericIcon, iconClass: 'text-neutral-500',bgClass: 'bg-neutral-100' },
}

const current = config[props.fileType] ?? config.document
</script>

<template>
    <span
        :class="['inline-flex items-center justify-center rounded-lg shrink-0', current.bgClass,
            size === 'sm' ? 'w-7 h-7' : size === 'lg' ? 'w-11 h-11' : 'w-9 h-9']"
    >
        <component
            :is="current.component"
            :class="[sizeClasses[size], current.iconClass]"
        />
    </span>
</template>
