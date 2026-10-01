<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useChurch } from '@/composables/useChurch'
import { useTenantStore } from '@/stores/useTenantStore'
import { Eye, EyeOff } from 'lucide-vue-next'
import { ref } from 'vue'

const { church } = useChurch()
const tenant = useTenantStore()

const form = useForm({
    email:    '',
    password: '',
    remember: false,
})

const showPassword = ref(false)

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Sign In" />

    <div class="min-h-screen gradient-mesh flex">
        <!-- Left panel — branding -->
        <div class="hidden lg:flex lg:w-1/2 bg-neutral-950 relative overflow-hidden flex-col justify-between p-12">
            <div class="absolute inset-0 opacity-25"
                style="background: radial-gradient(ellipse 70% 60% at 30% 50%, rgba(30,90,168,0.6) 0%, transparent 70%);"
                aria-hidden="true"></div>

            <div class="relative">
                <Link href="/" class="inline-flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg gradient-brand flex items-center justify-center">
                        <span class="text-white text-xs font-bold">{{ tenant.churchInitials }}</span>
                    </div>
                    <span class="font-semibold text-white text-sm">{{ church.name }}</span>
                </Link>
            </div>

            <div class="relative">
                <h2 class="text-4xl font-display text-white leading-tight mb-4">
                    Welcome back to<br />your community.
                </h2>
                <p class="text-neutral-400 leading-relaxed">
                    Manage your church, connect with members, track events, and more — all in one place.
                </p>
            </div>

            <p class="relative text-xs text-neutral-600">
                &copy; {{ new Date().getFullYear() }} {{ church.name }}
            </p>
        </div>

        <!-- Right panel — form -->
        <div class="flex-1 flex items-center justify-center p-6 lg:p-12">
            <div class="w-full max-w-sm">
                <!-- Mobile logo -->
                <Link href="/" class="inline-flex items-center gap-2 mb-8 lg:hidden">
                    <div class="w-7 h-7 rounded-lg gradient-brand flex items-center justify-center">
                        <span class="text-white text-xs font-bold">{{ tenant.churchInitials }}</span>
                    </div>
                    <span class="font-semibold text-neutral-900 text-sm">{{ church.name }}</span>
                </Link>

                <h1 class="text-2xl font-semibold text-neutral-900 mb-1">Sign in</h1>
                <p class="text-sm text-neutral-500 mb-8">
                    Don't have an account?
                    <Link href="/register" class="text-brand-600 hover:text-brand-700 font-medium transition-colors">Create one</Link>
                </p>

                <!-- Success flash -->
                <div v-if="$page.props.flash?.success"
                    class="bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 rounded-xl mb-5">
                    {{ $page.props.flash.success }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <AppInput
                        id="email"
                        v-model="form.email"
                        label="Email"
                        type="email"
                        placeholder="you@example.com"
                        required
                        :error="form.errors.email"
                    />

                    <div class="relative">
                        <AppInput
                            id="password"
                            v-model="form.password"
                            label="Password"
                            :type="showPassword ? 'text' : 'password'"
                            placeholder="••••••••"
                            required
                            :error="form.errors.password"
                        />
                        <button
                            type="button"
                            class="absolute right-3 top-8 text-neutral-400 hover:text-neutral-600"
                            @click="showPassword = !showPassword"
                            tabindex="-1"
                        >
                            <EyeOff v-if="showPassword" class="w-4 h-4" />
                            <Eye v-else class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-sm text-neutral-600 cursor-pointer">
                            <input v-model="form.remember" type="checkbox" class="rounded border-neutral-300 text-brand-500" />
                            Remember me
                        </label>
                        <Link href="/forgot-password" class="text-sm text-brand-600 hover:text-brand-700 font-medium transition-colors">
                            Forgot password?
                        </Link>
                    </div>

                    <AppButton type="submit" variant="primary" size="lg" :disabled="form.processing" class="w-full mt-2">
                        {{ form.processing ? 'Signing in...' : 'Sign In' }}
                    </AppButton>
                </form>

                <p class="text-xs text-neutral-400 text-center mt-8">
                    By signing in you agree to our
                    <a href="#" class="hover:text-neutral-600 underline underline-offset-2">Terms</a> and
                    <a href="#" class="hover:text-neutral-600 underline underline-offset-2">Privacy Policy</a>.
                </p>
            </div>
        </div>
    </div>
</template>
