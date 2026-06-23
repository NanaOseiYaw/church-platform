<script setup lang="ts">
import { ref, computed } from 'vue'
import { Eye, EyeOff } from 'lucide-vue-next'
import AppInput from '@/Components/UI/AppInput.vue'

const props = defineProps<{
    form: {
        admin_name:                  string
        admin_email:                 string
        admin_email_confirmation:    string
        admin_password:              string
        admin_password_confirmation: string
    }
    errors: Record<string, string>
}>()

const showPassword        = ref(false)
const showPasswordConfirm = ref(false)

// ── Password strength ────────────────────────────────────────────────────────────
function passwordStrength(pw: string): { score: number; label: string; color: string } {
    if (pw.length === 0) return { score: 0, label: '', color: '' }
    let score = 0
    if (pw.length >= 8)           score++
    if (pw.length >= 12)          score++
    if (/[A-Z]/.test(pw))         score++
    if (/[0-9]/.test(pw))         score++
    if (/[^A-Za-z0-9]/.test(pw))  score++

    if (score <= 1) return { score, label: 'Weak',   color: 'bg-rose-500' }
    if (score <= 3) return { score, label: 'Fair',   color: 'bg-amber-500' }
    if (score <= 4) return { score, label: 'Good',   color: 'bg-emerald-500' }
    return               { score, label: 'Strong', color: 'bg-emerald-600' }
}

const strength = computed(() => passwordStrength(props.form.admin_password))
</script>

<template>
    <div class="space-y-5">
        <p class="text-sm text-neutral-500 -mt-1">
            This account will be the church admin — you can invite additional team members after setup.
        </p>

        <!-- Full name -->
        <AppInput
            id="admin_name"
            :model-value="form.admin_name"
            label="Your full name"
            placeholder="John Smith"
            required
            :error="errors.admin_name"
            @update:model-value="form.admin_name = $event"
        />

        <!-- Email -->
        <AppInput
            id="admin_email"
            :model-value="form.admin_email"
            label="Email address"
            type="email"
            placeholder="you@yourchurch.org"
            required
            :error="errors.admin_email"
            @update:model-value="form.admin_email = $event"
        />

        <!-- Confirm email -->
        <AppInput
            id="admin_email_confirmation"
            :model-value="form.admin_email_confirmation"
            label="Confirm email address"
            type="email"
            placeholder="Confirm your email"
            required
            autocomplete="off"
            :error="errors.admin_email_confirmation"
            @update:model-value="form.admin_email_confirmation = $event"
        />

        <!-- Password with toggle -->
        <div>
            <label for="admin_password" class="block text-sm font-medium text-neutral-700 mb-1.5">
                Password
                <span class="text-neutral-400 font-normal ml-1">Min. 8 characters</span>
            </label>
            <div class="relative">
                <input
                    id="admin_password"
                    :value="form.admin_password"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Create a strong password"
                    autocomplete="new-password"
                    class="w-full h-10 rounded-lg border px-3 pr-10 text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    :class="errors.admin_password ? 'border-rose-400 bg-rose-50' : 'border-neutral-200 bg-white'"
                    @input="form.admin_password = ($event.target as HTMLInputElement).value"
                />
                <button
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-neutral-400 hover:text-neutral-600 transition-colors"
                    @click="showPassword = !showPassword"
                >
                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                </button>
            </div>

            <!-- Error -->
            <p v-if="errors.admin_password" class="text-xs text-rose-500 mt-1.5">{{ errors.admin_password }}</p>

            <!-- Strength meter -->
            <div v-if="form.admin_password.length > 0" class="mt-2 space-y-1">
                <div class="flex gap-1">
                    <div
                        v-for="n in 5"
                        :key="n"
                        :class="[
                            'h-1 flex-1 rounded-full transition-all duration-300',
                            n <= strength.score ? strength.color : 'bg-neutral-200',
                        ]"
                    />
                </div>
                <p
                    v-if="strength.label"
                    class="text-xs"
                    :class="strength.score <= 1 ? 'text-rose-500' : strength.score <= 3 ? 'text-amber-500' : 'text-emerald-600'"
                >
                    {{ strength.label }} password
                </p>
            </div>
        </div>

        <!-- Confirm password -->
        <div>
            <label for="admin_password_confirmation" class="block text-sm font-medium text-neutral-700 mb-1.5">
                Confirm password
            </label>
            <div class="relative">
                <input
                    id="admin_password_confirmation"
                    :value="form.admin_password_confirmation"
                    :type="showPasswordConfirm ? 'text' : 'password'"
                    placeholder="Repeat your password"
                    autocomplete="new-password"
                    class="w-full h-10 rounded-lg border px-3 pr-10 text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    :class="errors.admin_password_confirmation ? 'border-rose-400 bg-rose-50' : 'border-neutral-200 bg-white'"
                    @input="form.admin_password_confirmation = ($event.target as HTMLInputElement).value"
                />
                <button
                    type="button"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 p-0.5 text-neutral-400 hover:text-neutral-600 transition-colors"
                    @click="showPasswordConfirm = !showPasswordConfirm"
                >
                    <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                </button>
            </div>

            <!-- Error -->
            <p v-if="errors.admin_password_confirmation" class="text-xs text-rose-500 mt-1.5">
                {{ errors.admin_password_confirmation }}
            </p>

            <!-- Match feedback -->
            <p
                v-else-if="form.admin_password_confirmation.length > 0"
                class="text-xs mt-1.5"
                :class="form.admin_password_confirmation === form.admin_password ? 'text-emerald-600' : 'text-rose-500'"
            >
                <span v-if="form.admin_password_confirmation === form.admin_password">✓ Passwords match</span>
                <span v-else>✗ Passwords don't match</span>
            </p>
        </div>
    </div>
</template>
