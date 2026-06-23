<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Settings {
    provider: string
    stream_url: string
    embed_url: string
    auto_live: boolean
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    provider:   props.settings.provider   ?? '',
    stream_url: props.settings.stream_url ?? '',
    embed_url:  props.settings.embed_url  ?? '',
    auto_live:  props.settings.auto_live  ?? false,
})

function submit() {
    form.put('/dashboard/settings/livestream')
}

const providers = [
    { value: '',        label: 'Select provider…' },
    { value: 'youtube', label: 'YouTube Live' },
    { value: 'vimeo',   label: 'Vimeo' },
    { value: 'custom',  label: 'Custom embed' },
]
</script>

<template>
    <SettingsLayout section="livestream">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Livestream</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Configure your livestream provider and embed settings for the public website.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Provider -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Provider</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Where your church streams live video.</p>
                </div>
                <div class="p-5 space-y-4">
                    <AppSelect
                        label="Livestream provider"
                        v-model="form.provider"
                        :options="providers"
                        :error="form.errors.provider"
                    />
                    <AppInput
                        label="Stream URL"
                        v-model="form.stream_url"
                        :error="form.errors.stream_url"
                        placeholder="https://www.youtube.com/watch?v=..."
                    />
                    <AppInput
                        label="Embed URL / iFrame src"
                        v-model="form.embed_url"
                        :error="form.errors.embed_url"
                        placeholder="https://www.youtube.com/embed/live_stream?channel=..."
                    />
                </div>
            </div>

            <!-- Auto-detect -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Automation</h3>
                </div>
                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">Auto-live detection</p>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Automatically show the live stream player when the channel goes live.
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-amber-50 text-amber-600 text-[10px] font-medium ml-1 border border-amber-200">
                                Coming soon
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.auto_live"
                        @click="form.auto_live = !form.auto_live"
                        :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', form.auto_live ? 'bg-brand-500' : 'bg-neutral-200']"
                    >
                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', form.auto_live ? 'translate-x-4' : 'translate-x-0']" />
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save livestream settings</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
