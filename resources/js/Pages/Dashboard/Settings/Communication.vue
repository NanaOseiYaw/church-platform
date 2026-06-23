<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Settings {
    sender_name: string
    sender_email: string
    announcements_moderation: boolean
    approval_workflow: boolean
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    sender_name:              props.settings.sender_name              ?? '',
    sender_email:             props.settings.sender_email             ?? '',
    announcements_moderation: props.settings.announcements_moderation ?? false,
    approval_workflow:        props.settings.approval_workflow        ?? false,
})

function submit() {
    form.put('/dashboard/settings/communication')
}
</script>

<template>
    <SettingsLayout section="communication">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Communication</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Configure email sender identity and announcement workflow rules.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Email sender -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Email sender</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Name and address used as the "From" field in all outgoing emails.
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        label="Sender name"
                        v-model="form.sender_name"
                        :error="form.errors.sender_name"
                        placeholder="Grace Community Church"
                    />
                    <AppInput
                        label="Sender email"
                        type="email"
                        v-model="form.sender_email"
                        :error="form.errors.sender_email"
                        placeholder="noreply@yourchurch.com"
                    />
                </div>
            </div>

            <!-- Announcement workflow -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Announcements</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Control who can publish announcements and whether moderation is required.
                    </p>
                </div>

                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">Enable moderation</p>
                        <p class="text-xs text-neutral-500 mt-0.5">New announcements require admin review before publishing.</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.announcements_moderation"
                        @click="form.announcements_moderation = !form.announcements_moderation"
                        :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', form.announcements_moderation ? 'bg-brand-500' : 'bg-neutral-200']"
                    >
                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', form.announcements_moderation ? 'translate-x-4' : 'translate-x-0']" />
                    </button>
                </div>

                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">Approval workflow</p>
                        <p class="text-xs text-neutral-500 mt-0.5">Coordinators must submit announcements for admin approval.</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.approval_workflow"
                        @click="form.approval_workflow = !form.approval_workflow"
                        :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', form.approval_workflow ? 'bg-brand-500' : 'bg-neutral-200']"
                    >
                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', form.approval_workflow ? 'translate-x-4' : 'translate-x-0']" />
                    </button>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save communication settings</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
