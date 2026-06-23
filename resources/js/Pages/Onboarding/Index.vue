<script setup lang="ts">
/**
 * Onboarding wizard — self-service church workspace creation.
 *
 * All step data is collected client-side across 3 steps then submitted
 * as a single multipart POST. Inertia's forceFormData option handles
 * the logo File object transparently.
 *
 * Steps:
 *   1 — Church Info    (name, tagline, denomination, country, timezone)
 *   2 — Branding       (logo upload, accent color)
 *   3 — Admin Account  (name, email, password)
 */

import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { ArrowLeft, ArrowRight, Loader2, Sparkles } from 'lucide-vue-next'
import OnboardingLayout from '@/Layouts/OnboardingLayout.vue'
import StepProgress from '@/Components/Onboarding/StepProgress.vue'
import StepChurchInfo from '@/Components/Onboarding/StepChurchInfo.vue'
import StepBranding from '@/Components/Onboarding/StepBranding.vue'
import StepAdminAccount from '@/Components/Onboarding/StepAdminAccount.vue'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    timezones:     { value: string; label: string }[]
    colorPresets:  { color: string; name: string }[]
    denominations: string[]
    countries:     { value: string; label: string }[]
}>()

// ── Step state ─────────────────────────────────────────────────────────────────

const STEPS = ['Church Info', 'Branding', 'Admin Account'] as const
const currentStep = ref<1 | 2 | 3>(1)
const direction   = ref<'forward' | 'back'>('forward')

// ── Inertia form ───────────────────────────────────────────────────────────────

const form = useForm({
    // Step 1
    church_name:                  '',
    church_tagline:               '',
    timezone:                     'UTC',
    denomination:                 '',
    country:                      '',
    // Step 2
    primary_color:                '#1e5aa8',
    logo:                         null as File | null,
    // Step 3
    admin_name:                   '',
    admin_email:                  '',
    admin_email_confirmation:     '',
    admin_password:               '',
    admin_password_confirmation:  '',
})

// ── Step-scoped errors ─────────────────────────────────────────────────────────

const stepErrors = computed<Record<string, string>>(() => {
    const all = form.errors as Record<string, string>
    if (currentStep.value === 1) {
        return pick(all, ['church_name', 'church_tagline', 'timezone', 'denomination', 'country'])
    }
    if (currentStep.value === 2) {
        return pick(all, ['primary_color', 'logo'])
    }
    return pick(all, ['admin_name', 'admin_email', 'admin_email_confirmation', 'admin_password', 'admin_password_confirmation'])
})

function pick(obj: Record<string, string>, keys: string[]): Record<string, string> {
    return Object.fromEntries(Object.entries(obj).filter(([k]) => keys.includes(k)))
}

// ── Client-side validation ─────────────────────────────────────────────────────

function validateStep(): boolean {
    if (currentStep.value === 1) return form.church_name.trim().length >= 2
    if (currentStep.value === 2) return /^#[0-9a-fA-F]{6}$/.test(form.primary_color)
    return (
        form.admin_name.trim().length > 0 &&
        form.admin_email.includes('@') &&
        form.admin_email_confirmation === form.admin_email &&
        form.admin_password.length >= 8 &&
        form.admin_password_confirmation === form.admin_password
    )
}

// ── Navigation ─────────────────────────────────────────────────────────────────

function next() {
    if (!validateStep()) return
    if (currentStep.value === 3) {
        submit()
        return
    }
    direction.value = 'forward'
    currentStep.value = (currentStep.value + 1) as 1 | 2 | 3
}

function back() {
    if (currentStep.value === 1) return
    direction.value = 'back'
    currentStep.value = (currentStep.value - 1) as 1 | 2 | 3
}

// ── Submit ─────────────────────────────────────────────────────────────────────

const STEP_FIELDS = {
    1: ['church_name', 'church_tagline', 'timezone', 'denomination', 'country'],
    2: ['primary_color', 'logo'],
    3: ['admin_name', 'admin_email', 'admin_email_confirmation', 'admin_password', 'admin_password_confirmation'],
} as const

function firstStepWithErrors(errors: Record<string, string>): 1 | 2 | 3 | null {
    for (const step of [1, 2, 3] as const) {
        if (STEP_FIELDS[step].some(f => f in errors)) return step
    }
    return null
}

function submit() {
    form.post('/onboarding', {
        forceFormData: true,
        onError(errors) {
            const step = firstStepWithErrors(errors)
            if (step) {
                direction.value = step < currentStep.value ? 'back' : 'forward'
                currentStep.value = step
            }
        },
    })
}

// ── Step heading copy ──────────────────────────────────────────────────────────

const headings: Record<number, { title: string; subtitle: string }> = {
    1: { title: 'Tell us about your church',   subtitle: 'We\'ll use this to personalise your workspace.' },
    2: { title: 'Make it yours',               subtitle: 'Upload your logo and choose a brand color.' },
    3: { title: 'Create your admin account',   subtitle: 'These credentials will be used to sign in.' },
}

const heading  = computed(() => headings[currentStep.value])
const ctaLabel = computed(() => {
    if (form.processing) return 'Creating workspace…'
    return currentStep.value === 3 ? 'Create workspace' : 'Continue'
})
const ctaDisabled = computed(() => form.processing || !validateStep())

// ── Validation hint ────────────────────────────────────────────────────────────

const stepHint = computed<string | null>(() => {
    if (form.processing || validateStep()) return null
    if (currentStep.value === 1) {
        if (form.church_name.trim().length < 2) return 'Enter your church name to continue.'
    }
    if (currentStep.value === 2) {
        if (!/^#[0-9a-fA-F]{6}$/.test(form.primary_color)) return 'Choose a valid brand colour to continue.'
    }
    if (currentStep.value === 3) {
        if (!form.admin_name.trim())                                  return 'Enter your full name to continue.'
        if (!form.admin_email.includes('@'))                          return 'Enter a valid email address.'
        if (form.admin_email_confirmation !== form.admin_email)       return "Email addresses don't match."
        if (form.admin_password.length < 8)                          return 'Password must be at least 8 characters.'
        if (form.admin_password_confirmation !== form.admin_password) return "Passwords don't match."
    }
    return null
})

// ── Autofocus ──────────────────────────────────────────────────────────────────

const stepContainer = ref<HTMLDivElement | null>(null)

function focusFirstInput() {
    nextTick(() => {
        const el = stepContainer.value?.querySelector('input:not([type="file"]):not([type="color"])') as HTMLInputElement | null
        el?.focus()
    })
}

onMounted(focusFirstInput)
watch(currentStep, focusFirstInput)
</script>

<template>
    <OnboardingLayout title="Set up your church workspace">

        <!-- Step progress -->
        <StepProgress :current-step="currentStep" :steps="[...STEPS]" />

        <!-- Heading -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-neutral-900 leading-tight">
                {{ heading.title }}
            </h1>
            <p class="text-sm text-neutral-500 mt-1">{{ heading.subtitle }}</p>
        </div>

        <!-- Step panels (slide transition) -->
        <Transition
            :name="direction === 'forward' ? 'slide-left' : 'slide-right'"
            mode="out-in"
        >
            <div :key="currentStep" ref="stepContainer">
                <StepChurchInfo
                    v-if="currentStep === 1"
                    :form="form"
                    :errors="stepErrors"
                    :timezones="timezones"
                    :denominations="denominations"
                    :countries="countries"
                />
                <StepBranding
                    v-else-if="currentStep === 2"
                    :form="form"
                    :errors="stepErrors"
                    :color-presets="colorPresets"
                />
                <StepAdminAccount
                    v-else
                    :form="form"
                    :errors="stepErrors"
                />
            </div>
        </Transition>

        <!-- Non-field server error -->
        <div
            v-if="form.hasErrors && Object.keys(stepErrors).length === 0 && currentStep === 3"
            class="mt-4 p-3 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-700"
        >
            Something went wrong. Please check your details and try again.
        </div>

        <!-- Navigation -->
        <div class="flex items-center gap-3 mt-7">
            <button
                v-if="currentStep > 1"
                type="button"
                :disabled="form.processing"
                class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium text-neutral-600 bg-neutral-100 hover:bg-neutral-200 disabled:opacity-50 transition-colors"
                @click="back"
            >
                <ArrowLeft class="w-4 h-4" />
                Back
            </button>

            <button
                type="button"
                :disabled="ctaDisabled"
                class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white gradient-brand hover:opacity-90 disabled:opacity-50 transition-opacity shadow-sm"
                @click="next"
            >
                <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
                <Sparkles v-else-if="currentStep === 3" class="w-4 h-4" />
                {{ ctaLabel }}
                <ArrowRight v-if="!form.processing && currentStep < 3" class="w-4 h-4" />
            </button>
        </div>

        <!-- Validation hint -->
        <p v-if="stepHint" class="text-xs text-neutral-400 text-center mt-2">
            {{ stepHint }}
        </p>

        <!-- Step counter -->
        <p class="text-xs text-neutral-400 text-center mt-4">
            Step {{ currentStep }} of {{ STEPS.length }}
        </p>

    </OnboardingLayout>
</template>

<style scoped>
/* Forward: enter from right, leave to left */
.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
    transition: all 0.2s ease;
}

.slide-left-enter-from  { opacity: 0; transform: translateX(28px); }
.slide-left-leave-to    { opacity: 0; transform: translateX(-28px); }

.slide-right-enter-from { opacity: 0; transform: translateX(-28px); }
.slide-right-leave-to   { opacity: 0; transform: translateX(28px); }
</style>
