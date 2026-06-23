<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useChurch } from '@/composables/useChurch'
import { useTenantStore } from '@/stores/useTenantStore'

const { church } = useChurch()
const tenant = useTenantStore()

const form = useForm({
    name:                  '',
    email:                 '',
    password:              '',
    password_confirmation: '',
})

function submit() {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Create Account" />

    <div class="min-h-screen gradient-mesh flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <Link href="/" class="inline-flex items-center gap-2.5 justify-center mb-6">
                    <div class="w-9 h-9 rounded-xl gradient-brand flex items-center justify-center shadow-sm">
                        <span class="text-white text-sm font-bold">{{ tenant.churchInitials }}</span>
                    </div>
                </Link>
                <h1 class="text-2xl font-semibold text-neutral-900 mb-1">Join {{ church.name }}</h1>
                <p class="text-sm text-neutral-500">
                    Already a member?
                    <Link href="/login" class="text-brand-600 hover:text-brand-700 font-medium transition-colors">Sign in</Link>
                </p>
            </div>

            <div class="bg-white border border-neutral-100 rounded-2xl p-8 shadow-sm">
                <form @submit.prevent="submit" class="space-y-4">
                    <AppInput
                        id="name"
                        v-model="form.name"
                        label="Full Name"
                        placeholder="Your name"
                        required
                        :error="form.errors.name"
                    />
                    <AppInput
                        id="email"
                        v-model="form.email"
                        label="Email Address"
                        type="email"
                        placeholder="you@example.com"
                        required
                        :error="form.errors.email"
                    />
                    <AppInput
                        id="password"
                        v-model="form.password"
                        label="Password"
                        type="password"
                        placeholder="Min. 8 characters"
                        required
                        :error="form.errors.password"
                    />
                    <AppInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        label="Confirm Password"
                        type="password"
                        placeholder="Repeat password"
                        required
                        :error="form.errors.password_confirmation"
                    />

                    <AppButton type="submit" variant="primary" size="lg" :disabled="form.processing" class="w-full mt-2">
                        {{ form.processing ? 'Creating account...' : 'Create Account' }}
                    </AppButton>
                </form>
            </div>

            <p class="text-xs text-neutral-400 text-center mt-6">
                By creating an account you agree to our
                <a href="#" class="hover:text-neutral-600 underline underline-offset-2">Terms of Service</a>.
            </p>
        </div>
    </div>
</template>
