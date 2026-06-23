<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { HardDrive } from 'lucide-vue-next'

interface Settings {
    max_upload_mb: number
    auto_compress: boolean
}
interface FileStats {
    total_files: number
    total_size_bytes: number
}

const props = defineProps<{ settings: Settings; file_stats: FileStats }>()

const form = useForm({
    max_upload_mb: props.settings.max_upload_mb ?? 10,
    auto_compress: props.settings.auto_compress ?? true,
})

function submit() {
    form.put('/dashboard/settings/media')
}

function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

const totalSize  = computed(() => formatBytes(props.file_stats.total_size_bytes))
const inputCls = 'w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-colors'
</script>

<template>
    <SettingsLayout section="media">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Media Library</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Upload limits, optimization settings, and storage usage.
            </p>
        </div>

        <div class="max-w-xl space-y-5">

            <!-- Storage stats -->
            <div class="bg-white border border-neutral-100 rounded-xl p-5 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-brand-50 flex items-center justify-center shrink-0">
                    <HardDrive class="w-5 h-5 text-brand-600" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-neutral-900">{{ totalSize }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        {{ file_stats.total_files }} file{{ file_stats.total_files === 1 ? '' : 's' }} stored
                    </p>
                </div>
                <a href="/dashboard/media" class="text-xs text-brand-600 hover:text-brand-700 shrink-0">
                    View library →
                </a>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- Upload limit -->
                <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                    <div class="px-5 py-4">
                        <h3 class="text-sm font-semibold text-neutral-900">Upload limits</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">Maximum file size allowed per upload.</p>
                    </div>
                    <div class="p-5">
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Max upload size <span class="text-neutral-400 font-normal">(MB)</span>
                        </label>
                        <input
                            v-model.number="form.max_upload_mb"
                            type="number"
                            min="1"
                            max="500"
                            :class="[inputCls, { 'border-rose-400': form.errors.max_upload_mb }]"
                        />
                        <p v-if="form.errors.max_upload_mb" class="mt-1 text-xs text-rose-500">{{ form.errors.max_upload_mb }}</p>
                    </div>
                </div>

                <!-- Image optimization -->
                <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                    <div class="px-5 py-4">
                        <h3 class="text-sm font-semibold text-neutral-900">Optimisation</h3>
                    </div>
                    <div class="px-5 py-4 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-neutral-900">Auto-compress images</p>
                            <p class="text-xs text-neutral-500 mt-0.5">
                                Automatically optimise uploaded images to reduce file size.
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.auto_compress"
                            @click="form.auto_compress = !form.auto_compress"
                            :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', form.auto_compress ? 'bg-brand-500' : 'bg-neutral-200']"
                        >
                            <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', form.auto_compress ? 'translate-x-4' : 'translate-x-0']" />
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                    <span v-else />
                    <AppButton type="submit" :loading="form.processing">Save media settings</AppButton>
                </div>
            </form>
        </div>

    </SettingsLayout>
</template>
