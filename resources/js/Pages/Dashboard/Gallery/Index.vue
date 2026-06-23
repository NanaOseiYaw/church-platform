<script setup lang="ts">
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Images, Upload, Eye, EyeOff, Trash2, Plus } from 'lucide-vue-next'

interface GalleryImage {
    id: number
    title: string | null
    caption: string | null
    url: string
    is_published: boolean
    display_order: number
    created_at: string
}

defineProps<{
    images: GalleryImage[]
}>()

// ── Upload form ────────────────────────────────────────────────────────────────
const showUpload = ref(false)
const form = useForm({
    image:   null as File | null,
    title:   '',
    caption: '',
})

const fileInput = ref<HTMLInputElement | null>(null)
const preview   = ref<string | null>(null)

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    form.image = file
    if (file) {
        const reader = new FileReader()
        reader.onload = (ev) => { preview.value = ev.target?.result as string }
        reader.readAsDataURL(file)
    } else {
        preview.value = null
    }
}

function upload() {
    form.post('/dashboard/gallery', {
        forceFormData: true,
        onSuccess: () => {
            form.reset()
            preview.value = null
            showUpload.value = false
        },
    })
}

function togglePublish(image: GalleryImage) {
    router.patch(`/dashboard/gallery/${image.id}/publish`, {}, { preserveScroll: true })
}

function deleteImage(image: GalleryImage) {
    if (! confirm('Delete this image? This cannot be undone.')) return
    router.delete(`/dashboard/gallery/${image.id}`, { preserveScroll: true })
}
</script>

<template>
    <DashboardLayout
        title="Gallery"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Gallery' }]"
    >
        <PageHeader
            title="Gallery"
            description="Upload and manage photos shown on your public Gallery page."
        >
            <template #actions>
                <AppButton variant="primary" @click="showUpload = !showUpload">
                    <Plus class="w-4 h-4" />
                    Upload Photo
                </AppButton>
            </template>
        </PageHeader>

        <!-- Upload panel -->
        <div v-if="showUpload" class="mb-6 bg-white border border-neutral-100 rounded-xl p-5 shadow-sm space-y-4">
            <h3 class="text-sm font-semibold text-neutral-900">Upload New Photo</h3>

            <!-- Drop zone / file picker -->
            <div
                class="relative border-2 border-dashed border-neutral-200 rounded-xl p-6 text-center hover:border-brand-400 transition-colors cursor-pointer"
                @click="fileInput?.click()"
            >
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    class="hidden"
                    @change="onFileChange"
                />
                <img v-if="preview" :src="preview" class="max-h-40 mx-auto rounded-lg mb-3 object-contain" />
                <div v-else class="flex flex-col items-center gap-2">
                    <Upload class="w-8 h-8 text-neutral-300" />
                    <p class="text-sm text-neutral-500">Click to select an image</p>
                    <p class="text-xs text-neutral-400">JPG, PNG, WebP — max 10 MB</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Title <span class="text-neutral-400 font-normal">(optional)</span></label>
                    <input
                        v-model="form.title"
                        type="text"
                        placeholder="e.g. Youth Sunday 2025"
                        class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-neutral-700 mb-1">Caption <span class="text-neutral-400 font-normal">(optional)</span></label>
                    <input
                        v-model="form.caption"
                        type="text"
                        placeholder="A short description…"
                        class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <AppButton variant="ghost" @click="showUpload = false">Cancel</AppButton>
                <AppButton
                    variant="primary"
                    :disabled="!form.image || form.processing"
                    @click="upload"
                >
                    {{ form.processing ? 'Uploading…' : 'Upload' }}
                </AppButton>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="images.length === 0"
            class="flex flex-col items-center justify-center py-20 text-center"
        >
            <div class="w-14 h-14 bg-neutral-100 rounded-2xl flex items-center justify-center mb-4">
                <Images class="w-7 h-7 text-neutral-300" />
            </div>
            <p class="text-sm font-medium text-neutral-600 mb-1">No photos yet</p>
            <p class="text-xs text-neutral-400">Upload your first photo to get started.</p>
        </div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            <div
                v-for="image in images"
                :key="image.id"
                class="group relative bg-neutral-100 rounded-xl overflow-hidden aspect-square"
            >
                <img
                    :src="image.url"
                    :alt="image.title ?? 'Gallery image'"
                    class="w-full h-full object-cover"
                    loading="lazy"
                />

                <!-- Hover overlay -->
                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-between p-3">
                    <!-- Top: draft badge -->
                    <div class="flex justify-end">
                        <span
                            :class="image.is_published
                                ? 'bg-emerald-500 text-white'
                                : 'bg-neutral-700 text-white/70'"
                            class="text-[9px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full"
                        >
                            {{ image.is_published ? 'Published' : 'Draft' }}
                        </span>
                    </div>

                    <!-- Bottom: title + actions -->
                    <div>
                        <p v-if="image.title" class="text-white text-xs font-medium truncate mb-2">{{ image.title }}</p>
                        <div class="flex gap-1.5">
                            <button
                                type="button"
                                @click.stop="togglePublish(image)"
                                class="flex-1 flex items-center justify-center gap-1 py-1.5 text-xs font-medium rounded-lg transition-colors"
                                :class="image.is_published
                                    ? 'bg-white/20 hover:bg-white/30 text-white'
                                    : 'bg-brand-500 hover:bg-brand-600 text-white'"
                            >
                                <Eye v-if="!image.is_published" class="w-3 h-3" />
                                <EyeOff v-else class="w-3 h-3" />
                                {{ image.is_published ? 'Unpublish' : 'Publish' }}
                            </button>
                            <button
                                type="button"
                                @click.stop="deleteImage(image)"
                                class="p-1.5 bg-red-500/80 hover:bg-red-500 text-white rounded-lg transition-colors"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
