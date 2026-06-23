<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Copy, Check, AlertTriangle } from 'lucide-vue-next'

interface ChurchConfig {
    id: number
    slug: string
    domain: string | null
    subscription_plan: string
    is_active: boolean
}

// Named 'config' (not 'church') to avoid overwriting the shared TenantStore prop.
const props = defineProps<{ config: ChurchConfig }>()

const form = useForm({
    domain: props.config.domain ?? '',
})

function submit() {
    form.put('/dashboard/settings/advanced')
}

const copied = ref(false)
function copySlug() {
    navigator.clipboard.writeText(props.config.slug)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
}
</script>

<template>
    <SettingsLayout section="advanced">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Advanced</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Platform-level configuration for your church's instance.
            </p>
        </div>

        <div class="max-w-xl space-y-5">

            <!-- Church slug (read-only) -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Church slug</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Unique identifier for your church on the platform. Contact support to change it.
                    </p>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex items-center gap-2.5 px-3.5 py-2.5 bg-neutral-50 border border-neutral-200 rounded-lg">
                            <span class="text-sm text-neutral-400 font-mono select-none">/</span>
                            <span class="text-sm font-mono text-neutral-700">{{ config.slug }}</span>
                        </div>
                        <button
                            type="button"
                            @click="copySlug"
                            class="p-2.5 rounded-lg border border-neutral-200 hover:bg-neutral-50 text-neutral-500 transition-colors"
                            :title="copied ? 'Copied!' : 'Copy slug'"
                        >
                            <Check v-if="copied" class="w-4 h-4 text-emerald-500" />
                            <Copy v-else class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Custom domain -->
            <form @submit.prevent="submit" class="space-y-5">
                <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                    <div class="px-5 py-4">
                        <h3 class="text-sm font-semibold text-neutral-900">Custom domain</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Map a custom domain to your church's platform instance.
                        </p>
                    </div>
                    <div class="p-5">
                        <AppInput
                            label="Domain"
                            v-model="form.domain"
                            :error="form.errors.domain"
                            placeholder="church.yourdomain.com"
                        />
                        <p class="text-xs text-neutral-400 mt-1.5">
                            Create a CNAME record pointing to the platform DNS before activating.
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-1">
                    <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                    <span v-else />
                    <AppButton type="submit" :loading="form.processing">Save advanced settings</AppButton>
                </div>
            </form>

            <!-- Platform info -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Platform info</h3>
                </div>
                <div class="px-5 py-3.5 flex items-center justify-between">
                    <p class="text-sm text-neutral-600">Subscription plan</p>
                    <span class="text-sm font-medium text-neutral-900 capitalize">{{ config.subscription_plan }}</span>
                </div>
                <div class="px-5 py-3.5 flex items-center justify-between">
                    <p class="text-sm text-neutral-600">Church status</p>
                    <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', config.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200']">
                        {{ config.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="px-5 py-3.5 flex items-center justify-between">
                    <p class="text-sm text-neutral-600">Church ID</p>
                    <span class="text-sm font-mono text-neutral-500">{{ config.id }}</span>
                </div>
            </div>

            <!-- Danger zone -->
            <div class="bg-white border border-rose-100 rounded-xl divide-y divide-rose-50">
                <div class="px-5 py-4 flex items-center gap-2">
                    <AlertTriangle class="w-4 h-4 text-rose-500 shrink-0" />
                    <h3 class="text-sm font-semibold text-rose-700">Danger zone</h3>
                </div>
                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">Export church data</p>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Need a data export? Contact
                            <a href="mailto:support@churchplatform.com" class="text-brand-600 hover:underline">support</a>
                            and we'll send you a full archive.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </SettingsLayout>
</template>
