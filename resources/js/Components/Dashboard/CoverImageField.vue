<script setup lang="ts">
/**
 * Optional image or flyer for an event or announcement.
 *
 * Bound to two form fields: `file` (the new upload) and `remove` (ask the
 * server to clear the current image). Neither is set unless the admin acts, so
 * saving a form without touching this keeps the existing image.
 */
import { ref, computed, onBeforeUnmount } from 'vue'
import { ImagePlus, Upload, Trash2, Undo2 } from 'lucide-vue-next'
import AppButton from '@/Components/UI/AppButton.vue'

const props = defineProps<{
    current?: string | null
    error?: string | null
}>()

const file   = defineModel<File | null>('file', { default: null })
const remove = defineModel<boolean>('remove', { default: false })

// Matches the server rule. Checked here first so an oversized flyer gets a
// plain explanation instead of a failed save.
const MAX_BYTES = 10 * 1024 * 1024

const input      = ref<HTMLInputElement | null>(null)
const previewUrl = ref<string | null>(null)
const localError = ref<string | null>(null)

const shown = computed(() => previewUrl.value ?? (remove.value ? null : props.current ?? null))

function pick() {
    input.value?.click()
}

function onChange(e: Event) {
    const el = e.target as HTMLInputElement
    const f  = el.files?.[0]
    localError.value = null
    if (!f) return

    if (f.size > MAX_BYTES) {
        localError.value = `That file is ${(f.size / 1024 / 1024).toFixed(1)} MB; the limit is 10 MB. Export it smaller or as a JPG.`
        el.value = ''
        return
    }

    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = URL.createObjectURL(f)
    file.value   = f
    remove.value = false
}

function clear() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value)
        previewUrl.value = null
    }
    file.value = null
    if (input.value) input.value.value = ''
    // Only ask the server to remove an image that is actually saved.
    remove.value = !!props.current
}

onBeforeUnmount(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
})
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
            Image or flyer <span class="font-normal text-neutral-400">(optional)</span>
        </label>

        <div
            class="rounded-xl border border-dashed border-neutral-200 bg-neutral-50 overflow-hidden"
            :class="shown ? 'h-64' : 'h-36'"
        >
            <!-- Shown whole, as it will appear on the website, so a flyer's text is visible here too. -->
            <img v-if="shown" :src="shown" alt="Selected image" class="w-full h-full object-contain" />
            <button
                v-else
                type="button"
                class="w-full h-full flex flex-col items-center justify-center gap-2 text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 transition-colors"
                @click="pick"
            >
                <ImagePlus class="w-6 h-6" />
                <span class="text-sm">Add a photo or flyer</span>
            </button>
        </div>

        <input
            ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            class="sr-only"
            @change="onChange"
        />

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <AppButton v-if="shown" type="button" variant="outline" size="sm" @click="pick">
                <Upload class="w-3.5 h-3.5 mr-1.5" />
                Replace
            </AppButton>
            <AppButton v-if="shown" type="button" variant="ghost" size="sm" @click="clear">
                <Trash2 class="w-3.5 h-3.5 mr-1.5" />
                Remove
            </AppButton>
            <template v-if="remove && !file">
                <span class="text-xs text-amber-600">The image will be removed when you save.</span>
                <button type="button" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700" @click="remove = false">
                    <Undo2 class="w-3 h-3" /> Undo
                </button>
            </template>
        </div>

        <p class="mt-1.5 text-xs text-neutral-400">
            JPG, PNG or WebP, up to 10 MB. Shown in full on the website — never cropped — so the text on a flyer stays readable.
        </p>
        <p v-if="localError || error" class="mt-1 text-xs text-rose-500">{{ localError ?? error }}</p>
    </div>
</template>
