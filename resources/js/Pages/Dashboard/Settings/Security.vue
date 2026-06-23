<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Settings {
    session_timeout_minutes: number
    password_min_length: number
    max_login_attempts: number
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    session_timeout_minutes: props.settings.session_timeout_minutes ?? 120,
    password_min_length:     props.settings.password_min_length     ?? 8,
    max_login_attempts:      props.settings.max_login_attempts      ?? 5,
})

function submit() {
    form.put('/dashboard/settings/security')
}

const inputCls = 'w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10'
</script>

<template>
    <SettingsLayout section="security">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Security</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Session management, password requirements, and login protection rules.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Sessions -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Sessions</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">How long a member's session stays active before they must log in again.</p>
                </div>
                <div class="p-5">
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                        Session timeout
                        <span class="text-neutral-400 font-normal ml-1">(minutes)</span>
                    </label>
                    <input
                        v-model.number="form.session_timeout_minutes"
                        type="number"
                        min="15"
                        max="10080"
                        :class="[inputCls, { 'border-rose-400': form.errors.session_timeout_minutes }]"
                    />
                    <p v-if="form.errors.session_timeout_minutes" class="mt-1 text-xs text-rose-500">{{ form.errors.session_timeout_minutes }}</p>
                    <p class="text-xs text-neutral-400 mt-1.5">
                        e.g. 60 = 1 hour, 1440 = 1 day, 10080 = 1 week
                    </p>
                </div>
            </div>

            <!-- Passwords -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Password policy</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Minimum requirements enforced at registration and password change.</p>
                </div>
                <div class="p-5">
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                        Minimum password length
                    </label>
                    <input
                        v-model.number="form.password_min_length"
                        type="number"
                        min="6"
                        max="32"
                        :class="[inputCls, { 'border-rose-400': form.errors.password_min_length }]"
                    />
                    <p v-if="form.errors.password_min_length" class="mt-1 text-xs text-rose-500">{{ form.errors.password_min_length }}</p>
                </div>
            </div>

            <!-- Login protection -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Login protection</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Limit failed login attempts before locking an account temporarily.</p>
                </div>
                <div class="p-5">
                    <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                        Max login attempts
                        <span class="text-neutral-400 font-normal ml-1">before lockout</span>
                    </label>
                    <input
                        v-model.number="form.max_login_attempts"
                        type="number"
                        min="3"
                        max="20"
                        :class="[inputCls, { 'border-rose-400': form.errors.max_login_attempts }]"
                    />
                    <p v-if="form.errors.max_login_attempts" class="mt-1 text-xs text-rose-500">{{ form.errors.max_login_attempts }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save security settings</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
