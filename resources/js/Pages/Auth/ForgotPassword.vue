<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useChurch } from '@/composables/useChurch'
import { useTenantStore } from '@/stores/useTenantStore'
import { ArrowLeft } from 'lucide-vue-next'

const { church } = useChurch()
const tenant = useTenantStore()
const form = useForm({ email: '' })
function submit() { form.post('/forgot-password') }
</script>

<template>
    <Head title="Forgot Password" />
    <div class="min-h-screen gradient-mesh flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="w-9 h-9 rounded-xl gradient-brand flex items-center justify-center shadow-sm mx-auto mb-5">
                    <span class="text-white text-sm font-bold">{{ tenant.churchInitials }}</span>
                </div>
                <h1 class="text-2xl font-semibold text-neutral-900 mb-1">Reset your password</h1>
                <p class="text-sm text-neutral-500">We'll send a reset link to your email address.</p>
            </div>

            <div v-if="$page.props.flash?.success"
                class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl mb-5 text-center">
                {{ $page.props.flash.success }}
            </div>

            <div class="bg-white border border-neutral-100 rounded-2xl p-8 shadow-sm">
                <form @submit.prevent="submit" class="space-y-4">
                    <AppInput
                        id="email" v-model="form.email"
                        label="Email Address" type="email"
                        placeholder="you@example.com" required
                        :error="form.errors.email"
                    />
                    <AppButton type="submit" variant="primary" size="lg" :disabled="form.processing" class="w-full">
                        {{ form.processing ? 'Sending...' : 'Send Reset Link' }}
                    </AppButton>
                </form>
            </div>

            <div class="text-center mt-5">
                <Link href="/login" class="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-700 transition-colors">
                    <ArrowLeft class="w-3.5 h-3.5" /> Back to sign in
                </Link>
            </div>
        </div>
    </div>
</template>
