<script setup lang="ts">
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { ImagePlus, Upload } from 'lucide-vue-next'

interface Church {
    id: number
    name: string
    tagline: string | null
    logo: string | null
    favicon: string | null
    primary_color: string | null
    secondary_color: string | null
}

const props = defineProps<{ church: Church }>()

const form = useForm({
    name:            props.church.name,
    tagline:         props.church.tagline         ?? '',
    primary_color:   props.church.primary_color   ?? '#6366f1',
    secondary_color: props.church.secondary_color ?? '',
})

// Live previews of the colour picker values
const colorPreview          = ref(form.primary_color)
const secondaryColorPreview = ref(form.secondary_color)
watch(() => form.primary_color,   v => { colorPreview.value          = v })
watch(() => form.secondary_color, v => { secondaryColorPreview.value = v })

function submit() {
    form.put('/dashboard/settings/branding')
}

// ── Logo upload ────────────────────────────────────────────────────────────────
const logoFileRef    = ref<HTMLInputElement | null>(null)
const logoForm       = useForm({ logo: null as File | null })
const logoPreviewUrl = ref<string | null>(props.church.logo)

function triggerLogoUpload() {
    logoFileRef.value?.click()
}

function onLogoFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    // Local preview
    logoPreviewUrl.value = URL.createObjectURL(file)
    logoForm.logo = file
    logoForm.post('/dashboard/settings/branding/logo', {
        preserveScroll: true,
        onSuccess: () => {
            logoForm.reset()
            // Reset the file input so re-upload of same file triggers onChange again
            if (logoFileRef.value) logoFileRef.value.value = ''
        },
    })
}

// ── Favicon upload ─────────────────────────────────────────────────────────────
const faviconFileRef    = ref<HTMLInputElement | null>(null)
const faviconForm       = useForm({ favicon: null as File | null })
const faviconPreviewUrl = ref<string | null>(props.church.favicon)

function triggerFaviconUpload() {
    faviconFileRef.value?.click()
}

function onFaviconFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    faviconPreviewUrl.value = URL.createObjectURL(file)
    faviconForm.favicon = file
    faviconForm.post('/dashboard/settings/branding/favicon', {
        preserveScroll: true,
        onSuccess: () => {
            faviconForm.reset()
            if (faviconFileRef.value) faviconFileRef.value.value = ''
        },
    })
}

const presetColors = [
    '#6366f1', // Indigo
    '#3b82f6', // Blue
    '#1e5aa8', // Dark Blue
    '#0e4d92', // Royal Blue
    '#14b8a6', // Teal
    '#10b981', // Emerald
    '#1a6b3c', // Forest Green
    '#f59e0b', // Amber
    '#ef4444', // Red
    '#8b5cf6', // Violet
    '#64748b', // Slate
    '#1f2937', // Charcoal
]
</script>

<template>
    <SettingsLayout section="branding">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Branding &amp; Theme</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Visual identity applied across the dashboard, emails, and public website.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Church name + tagline (branding-level editing) -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Church name</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Shown in the header, email subjects, and notification senders.
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        label="Church name"
                        v-model="form.name"
                        :error="form.errors.name"
                        required
                    />
                    <AppInput
                        label="Tagline"
                        v-model="form.tagline"
                        placeholder="A Place to Belong"
                        :error="form.errors.tagline"
                    />
                </div>
            </div>

            <!-- Logo -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Logo</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Displayed in the sidebar, emails, and public pages.</p>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-4">
                        <!-- Current logo / placeholder -->
                        <div class="w-16 h-16 rounded-xl border-2 border-dashed border-neutral-200 flex items-center justify-center bg-neutral-50 shrink-0 overflow-hidden">
                            <img
                                v-if="logoPreviewUrl"
                                :src="logoPreviewUrl"
                                class="w-full h-full object-contain"
                                alt="Church logo"
                            />
                            <ImagePlus v-else class="w-6 h-6 text-neutral-300" />
                        </div>
                        <div>
                            <!-- Hidden file input -->
                            <input
                                ref="logoFileRef"
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                class="sr-only"
                                @change="onLogoFileChange"
                            />
                            <AppButton
                                variant="outline"
                                size="sm"
                                type="button"
                                :loading="logoForm.processing"
                                @click="triggerLogoUpload"
                            >
                                <Upload class="w-3.5 h-3.5 mr-1.5" />
                                {{ logoForm.processing ? 'Uploading…' : 'Upload logo' }}
                            </AppButton>
                            <p class="text-xs text-neutral-400 mt-1.5">PNG, SVG, JPG or WebP · max 2 MB</p>
                            <p v-if="logoForm.errors.logo" class="text-xs text-rose-500 mt-1">
                                {{ logoForm.errors.logo }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Favicon -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Favicon</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Small icon shown in browser tabs. Use a square PNG or ICO, 32×32 or 64×64 px.</p>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-4">
                        <!-- Current favicon / placeholder -->
                        <div class="w-10 h-10 rounded-lg border-2 border-dashed border-neutral-200 flex items-center justify-center bg-neutral-50 shrink-0 overflow-hidden">
                            <img
                                v-if="faviconPreviewUrl"
                                :src="faviconPreviewUrl"
                                class="w-full h-full object-contain"
                                alt="Favicon"
                            />
                            <ImagePlus v-else class="w-4 h-4 text-neutral-300" />
                        </div>
                        <div>
                            <!-- Hidden file input -->
                            <input
                                ref="faviconFileRef"
                                type="file"
                                accept="image/png,image/x-icon,image/vnd.microsoft.icon,image/webp"
                                class="sr-only"
                                @change="onFaviconFileChange"
                            />
                            <AppButton
                                variant="outline"
                                size="sm"
                                type="button"
                                :loading="faviconForm.processing"
                                @click="triggerFaviconUpload"
                            >
                                <Upload class="w-3.5 h-3.5 mr-1.5" />
                                {{ faviconForm.processing ? 'Uploading…' : 'Upload favicon' }}
                            </AppButton>
                            <p class="text-xs text-neutral-400 mt-1.5">PNG or ICO · square · max 512 KB</p>
                            <p v-if="faviconForm.errors.favicon" class="text-xs text-rose-500 mt-1">
                                {{ faviconForm.errors.favicon }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Brand colours -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Brand colours</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Applied to buttons, links, and accent elements. Updates live after saving.
                    </p>
                </div>
                <div class="p-5 space-y-6">
                    <!-- Primary colour -->
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-neutral-700">Primary colour</label>
                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                v-model="form.primary_color"
                                class="w-10 h-9 rounded-lg border border-neutral-200 cursor-pointer p-0.5 bg-white"
                            />
                            <span class="text-sm text-neutral-700 font-mono tracking-widest">
                                {{ form.primary_color }}
                            </span>
                            <div
                                class="w-6 h-6 rounded-md border border-white shadow-sm"
                                :style="{ backgroundColor: colorPreview }"
                            />
                        </div>
                        <p v-if="form.errors.primary_color" class="text-xs text-rose-500">
                            {{ form.errors.primary_color }}
                        </p>
                        <!-- Preset swatches -->
                        <div>
                            <p class="text-xs text-neutral-500 mb-2">Presets</p>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="color in presetColors"
                                    :key="color"
                                    type="button"
                                    :title="color"
                                    :style="{ backgroundColor: color }"
                                    :class="[
                                        'w-6 h-6 rounded-md border-2 transition-all duration-100',
                                        form.primary_color === color
                                            ? 'border-neutral-900 scale-110'
                                            : 'border-transparent hover:scale-110 hover:border-neutral-300',
                                    ]"
                                    @click="form.primary_color = color"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Secondary colour -->
                    <div class="space-y-3 pt-2 border-t border-neutral-100">
                        <div>
                            <label class="block text-sm font-medium text-neutral-700">Secondary colour <span class="font-normal text-neutral-400">(optional)</span></label>
                            <p class="text-xs text-neutral-400 mt-0.5">Used for accents and highlights alongside the primary colour.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <input
                                type="color"
                                v-model="form.secondary_color"
                                class="w-10 h-9 rounded-lg border border-neutral-200 cursor-pointer p-0.5 bg-white"
                            />
                            <span class="text-sm text-neutral-700 font-mono tracking-widest">
                                {{ form.secondary_color || '—' }}
                            </span>
                            <div
                                v-if="form.secondary_color"
                                class="w-6 h-6 rounded-md border border-white shadow-sm"
                                :style="{ backgroundColor: secondaryColorPreview }"
                            />
                            <button
                                v-if="form.secondary_color"
                                type="button"
                                class="text-xs text-neutral-400 hover:text-rose-500 transition-colors ml-1"
                                @click="form.secondary_color = ''"
                            >
                                Clear
                            </button>
                        </div>
                        <p v-if="form.errors.secondary_color" class="text-xs text-rose-500">
                            {{ form.errors.secondary_color }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Save row -->
            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">
                    ✓ Branding saved
                </p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">
                    Save branding
                </AppButton>
            </div>

        </form>
    </SettingsLayout>
</template>
