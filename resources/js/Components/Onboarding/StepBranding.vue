<script setup lang="ts">
import { ref, onUnmounted } from 'vue'
import { Upload, X } from 'lucide-vue-next'

const VALID_IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp'] as const

const props = defineProps<{
    form: {
        primary_color: string
        logo:          File | null
    }
    errors:       Record<string, string>
    colorPresets: { color: string; name: string }[]
}>()

// ── Logo preview ────────────────────────────────────────────────────────────────
const logoPreviewUrl = ref<string | null>(null)
const fileInput      = ref<HTMLInputElement | null>(null)
const isDragging = ref(false)
const dragError  = ref(false)

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    if (!file) return

    props.form.logo = file

    const reader = new FileReader()
    reader.onload = ev => { logoPreviewUrl.value = ev.target?.result as string }
    reader.readAsDataURL(file)
}

function removeLogo() {
    props.form.logo = null
    logoPreviewUrl.value = null
    if (fileInput.value) fileInput.value.value = ''
}

// ── Drag-and-drop ───────────────────────────────────────────────────────────────
let dragErrorTimer: ReturnType<typeof setTimeout> | null = null
onUnmounted(() => { if (dragErrorTimer !== null) clearTimeout(dragErrorTimer) })

function onDragOver(e: DragEvent) {
    e.preventDefault()
    isDragging.value = true
}

function onDragLeave(e: DragEvent) {
    if (!(e.currentTarget as HTMLElement).contains(e.relatedTarget as Node | null)) {
        isDragging.value = false
    }
}

function onDrop(e: DragEvent) {
    e.preventDefault()
    isDragging.value = false
    const file = e.dataTransfer?.files[0] ?? null
    if (!file) return
    if (!VALID_IMAGE_TYPES.includes(file.type as typeof VALID_IMAGE_TYPES[number])) {
        dragError.value = true
        dragErrorTimer = setTimeout(() => { dragError.value = false }, 2000)
        return
    }
    props.form.logo = file
    const reader = new FileReader()
    reader.onload = ev => { logoPreviewUrl.value = ev.target?.result as string }
    reader.readAsDataURL(file)
}

// ── Accent color ────────────────────────────────────────────────────────────────
function pickPreset(color: string) {
    props.form.primary_color = color
}
</script>

<template>
    <div class="space-y-6">

        <!-- Logo upload -->
        <div>
            <label class="block text-sm font-medium text-neutral-700 mb-2">
                Church logo
                <span class="text-neutral-400 font-normal">(optional)</span>
            </label>

            <!-- Preview state -->
            <div v-if="logoPreviewUrl" class="flex items-center gap-4">
                <div class="w-20 h-20 rounded-2xl border border-neutral-200 overflow-hidden bg-neutral-50 shrink-0">
                    <img :src="logoPreviewUrl" alt="Logo preview" class="w-full h-full object-contain p-1" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-neutral-700 truncate">{{ form.logo?.name }}</p>
                    <p class="text-xs text-neutral-400 mt-0.5">
                        {{ form.logo ? (form.logo.size / 1024).toFixed(0) + ' KB' : '' }}
                    </p>
                    <button
                        type="button"
                        class="mt-2 flex items-center gap-1 text-xs font-medium text-rose-500 hover:text-rose-700 transition-colors"
                        @click="removeLogo"
                    >
                        <X class="w-3.5 h-3.5" />
                        Remove
                    </button>
                </div>
            </div>

            <!-- Upload dropzone -->
            <button
                v-else
                type="button"
                :class="[
                    'w-full h-28 border-2 border-dashed rounded-2xl flex flex-col items-center justify-center gap-2 transition-all group',
                    dragError  && 'border-rose-300 bg-rose-50 cursor-not-allowed pointer-events-none',
                    isDragging && !dragError && 'border-brand-400 bg-brand-50/50',
                    !isDragging && !dragError && 'border-neutral-200 hover:border-brand-300 hover:bg-brand-50/30',
                ]"
                @click="fileInput?.click()"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
            >
                <!-- dragError state -->
                <template v-if="dragError">
                    <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center">
                        <X class="w-[18px] h-[18px] text-rose-500" />
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-semibold text-rose-600">Not an image file</p>
                        <p class="text-xs text-rose-400">PNG, JPG, or WebP only</p>
                    </div>
                </template>

                <!-- isDragging state -->
                <template v-else-if="isDragging">
                    <div class="w-10 h-10 bg-brand-100 rounded-xl flex items-center justify-center">
                        <Upload class="w-[18px] h-[18px] text-brand-600" />
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-semibold text-brand-700">Drop to upload</p>
                        <p class="text-xs text-brand-500">PNG, JPG, WebP up to 2 MB</p>
                    </div>
                </template>

                <!-- Idle state -->
                <template v-else>
                    <div class="w-10 h-10 bg-neutral-100 group-hover:bg-brand-100 rounded-xl flex items-center justify-center transition-colors">
                        <Upload class="w-[18px] h-[18px] text-neutral-400 group-hover:text-brand-600 transition-colors" />
                    </div>
                    <div class="text-center">
                        <p class="text-sm font-medium text-neutral-600 group-hover:text-brand-700 transition-colors">Upload logo</p>
                        <p class="text-xs text-neutral-400">PNG, JPG, WebP up to 2 MB</p>
                    </div>
                </template>
            </button>

            <input
                ref="fileInput"
                type="file"
                accept="image/png,image/jpeg,image/jpg,image/webp"
                class="hidden"
                @change="onFileChange"
            />

            <p v-if="errors.logo" class="text-xs text-rose-500 mt-1.5">{{ errors.logo }}</p>
        </div>

        <!-- Accent color -->
        <div>
            <label class="block text-sm font-medium text-neutral-700 mb-2">
                Brand color
            </label>

            <!-- Preset swatches -->
            <div class="flex flex-wrap gap-2 mb-3">
                <button
                    v-for="preset in colorPresets"
                    :key="preset.color"
                    type="button"
                    :title="preset.name"
                    :class="[
                        'w-8 h-8 rounded-lg border-2 transition-all duration-150',
                        form.primary_color === preset.color
                            ? 'border-neutral-800 scale-110'
                            : 'border-transparent hover:scale-105',
                    ]"
                    :style="{ backgroundColor: preset.color }"
                    @click="pickPreset(preset.color)"
                />
            </div>

            <!-- Custom hex input + colour picker -->
            <div class="flex items-center gap-2.5">
                <input
                    :value="form.primary_color"
                    type="color"
                    class="w-10 h-10 rounded-lg border border-neutral-200 cursor-pointer p-0.5 bg-white shrink-0"
                    title="Custom color"
                    @input="form.primary_color = ($event.target as HTMLInputElement).value"
                />
                <input
                    :value="form.primary_color"
                    type="text"
                    placeholder="#1e5aa8"
                    maxlength="7"
                    class="flex-1 h-10 rounded-lg border border-neutral-200 px-3 text-sm font-mono text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    @input="form.primary_color = ($event.target as HTMLInputElement).value"
                />
            </div>

            <!-- Live preview pill -->
            <div class="mt-3 flex items-center gap-2.5 p-3 bg-neutral-50 rounded-xl border border-neutral-100">
                <div
                    class="w-6 h-6 rounded-lg shrink-0 transition-colors"
                    :style="{ backgroundColor: form.primary_color }"
                />
                <div>
                    <p class="text-xs font-medium text-neutral-700">Accent preview</p>
                    <p class="text-xs text-neutral-400">Your dashboard will use this color</p>
                </div>
                <div class="ml-auto">
                    <span
                        class="px-3 py-1 rounded-full text-xs font-semibold text-white transition-colors"
                        :style="{ backgroundColor: form.primary_color }"
                    >
                        Active
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
