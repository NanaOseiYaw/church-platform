<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Settings {
    meta_title:          string
    meta_description:    string
    google_analytics_id: string
    clarity_id:          string
    robots:              string
    og_image:            string | null
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    meta_title:          props.settings.meta_title          ?? '',
    meta_description:    props.settings.meta_description    ?? '',
    google_analytics_id: props.settings.google_analytics_id ?? '',
    clarity_id:          props.settings.clarity_id          ?? '',
    robots:              props.settings.robots              ?? 'index,follow',
})

function submit() {
    form.put('/dashboard/settings/seo')
}

// ── Default OG image upload ────────────────────────────────────────────────────
const ogFileRef    = ref<HTMLInputElement | null>(null)
const ogForm       = useForm({ og_image: null as File | null })
const ogPreviewUrl = ref<string | null>(props.settings.og_image)

function onOgFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    ogPreviewUrl.value = URL.createObjectURL(file)
    ogForm.og_image = file
    ogForm.post('/dashboard/settings/seo/og-image', {
        onSuccess: () => {
            ogForm.reset()
            if (ogFileRef.value) ogFileRef.value.value = ''
        },
    })
}

const robotsOptions = [
    { value: 'index,follow',       label: 'Index & Follow (default)' },
    { value: 'noindex,follow',     label: 'No Index, Follow links' },
    { value: 'index,nofollow',     label: 'Index, No Follow links' },
    { value: 'noindex,nofollow',   label: 'No Index, No Follow (hidden)' },
]

</script>

<template>
    <SettingsLayout section="seo">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">SEO &amp; Analytics</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Meta tags, Open Graph settings, analytics integrations, and crawler rules.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Meta tags -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Meta tags</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Shown in Google search results and when sharing your website on social media.
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <AppInput
                            label="Meta title"
                            v-model="form.meta_title"
                            :error="form.errors.meta_title"
                            placeholder="Grace Community Church — A Place to Belong"
                        />
                        <p class="text-[11px] text-neutral-400 mt-1">
                            {{ form.meta_title.length }}/80 characters — keep under 60 for best display.
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Meta description</label>
                        <textarea
                            v-model="form.meta_description"
                            rows="3"
                            maxlength="200"
                            placeholder="A welcoming community church in the heart of the city..."
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 resize-none"
                            :class="{ 'border-rose-400': form.errors.meta_description }"
                        />
                        <div class="flex items-center justify-between mt-1">
                            <p v-if="form.errors.meta_description" class="text-xs text-rose-500">{{ form.errors.meta_description }}</p>
                            <span v-else />
                            <p class="text-[11px] text-neutral-400">{{ form.meta_description.length }}/200</p>
                        </div>
                    </div>
                    <AppSelect
                        label="Robots directive"
                        v-model="form.robots"
                        :options="robotsOptions"
                        :error="form.errors.robots"
                    />
                </div>
            </div>

            <!-- Default social share image -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Default social share image</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Shown when someone shares your website on Facebook, X, LinkedIn, etc. Recommended: 1200 × 630 px.
                    </p>
                </div>
                <div class="p-5">
                    <div class="flex items-start gap-4">
                        <!-- Preview -->
                        <div class="w-40 h-21 rounded-lg overflow-hidden bg-neutral-100 border border-neutral-200 shrink-0 flex items-center justify-center aspect-video">
                            <img
                                v-if="ogPreviewUrl"
                                :src="ogPreviewUrl"
                                alt="Social share preview"
                                class="w-full h-full object-cover"
                            />
                            <span v-else class="text-[10px] text-neutral-400 text-center px-2 leading-snug">No image set — church logo used</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <input
                                ref="ogFileRef"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="hidden"
                                @change="onOgFileChange"
                            />
                            <AppButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                :loading="ogForm.processing"
                                @click="ogFileRef?.click()"
                            >
                                {{ ogForm.processing ? 'Uploading…' : (ogPreviewUrl ? 'Replace image' : 'Upload image') }}
                            </AppButton>
                            <p v-if="ogForm.errors.og_image" class="text-xs text-rose-500">{{ ogForm.errors.og_image }}</p>
                            <p class="text-[11px] text-neutral-400 leading-relaxed">PNG, JPG or WebP · max 5 MB</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Analytics -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Analytics</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Paste your tracking IDs to enable analytics on the public website.</p>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        label="Google Analytics Measurement ID"
                        v-model="form.google_analytics_id"
                        :error="form.errors.google_analytics_id"
                        placeholder="G-XXXXXXXXXX"
                    />
                    <AppInput
                        label="Microsoft Clarity Project ID"
                        v-model="form.clarity_id"
                        :error="form.errors.clarity_id"
                        placeholder="xxxxxxxxxx"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save SEO settings</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
