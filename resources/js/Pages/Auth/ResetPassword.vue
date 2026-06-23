<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useTenantStore } from '@/stores/useTenantStore'

const props = defineProps<{ token: string; email: string }>()
const tenant = useTenantStore()

const form = useForm({
    token:                 props.token,
    email:                 props.email,
    password:              '',
    password_confirmation: '',
})

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Reset Password" />
    <div class="min-h-screen gradient-mesh flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <div class="w-9 h-9 rounded-xl gradient-brand flex items-center justify-center shadow-sm mx-auto mb-5">
                    <span class="text-white text-sm font-bold">{{ tenant.churchInitials }}</span>
                </div>
                <h1 class="text-2xl font-semibold text-neutral-900 mb-1">Set new password</h1>
            </div>
            <div class="bg-white border border-neutral-100 rounded-2xl p-8 shadow-sm">
                <form @submit.prevent="submit" class="space-y-4">
                    <AppInput id="email" v-model="form.email" label="Email" type="email" required :error="form.errors.email" />
                    <AppInput id="password" v-model="form.password" label="New Password" type="password" placeholder="Min. 8 characters" required :error="form.errors.password" />
                    <AppInput id="password_confirmation" v-model="form.password_confirmation" label="Confirm Password" type="password" required :error="form.errors.password_confirmation" />
                    <AppButton type="submit" variant="primary" size="lg" :disabled="form.processing" class="w-full">
                        {{ form.processing ? 'Saving...' : 'Reset Password' }}
                    </AppButton>
                </form>
            </div>
        </div>
    </div>
</template>
