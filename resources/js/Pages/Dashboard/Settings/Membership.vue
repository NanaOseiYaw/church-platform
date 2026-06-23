<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Settings {
    registration_enabled: boolean
    approval_required: boolean
    email_verification: boolean
    default_role: string
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    registration_enabled: props.settings.registration_enabled ?? true,
    approval_required:    props.settings.approval_required    ?? false,
    email_verification:   props.settings.email_verification   ?? true,
    default_role:         props.settings.default_role         ?? 'member',
})

function submit() {
    form.put('/dashboard/settings/membership')
}

const roleOptions = [
    { value: 'member',      label: 'Member' },
    { value: 'coordinator', label: 'Coordinator' },
]

interface ToggleRow {
    key: keyof typeof form.data
    label: string
    description: string
}
const toggles: ToggleRow[] = [
    {
        key:         'registration_enabled',
        label:       'Allow self-registration',
        description: 'Anyone can register an account through the login page.',
    },
    {
        key:         'approval_required',
        label:       'Require admin approval',
        description: 'New registrations are held in a pending state until approved.',
    },
    {
        key:         'email_verification',
        label:       'Require email verification',
        description: "New members must confirm their email before accessing the dashboard.",
    },
]
</script>

<template>
    <SettingsLayout section="membership">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Membership</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Control how new members join your church platform.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Registration toggles -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Registration</h3>
                </div>
                <div
                    v-for="toggle in toggles"
                    :key="toggle.key"
                    class="px-5 py-4 flex items-center justify-between"
                >
                    <div>
                        <p class="text-sm font-medium text-neutral-900">{{ toggle.label }}</p>
                        <p class="text-xs text-neutral-500 mt-0.5">{{ toggle.description }}</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="(form[toggle.key] as boolean)"
                        @click="(form[toggle.key] as boolean) = !(form[toggle.key] as boolean)"
                        :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', (form[toggle.key] as boolean) ? 'bg-brand-500' : 'bg-neutral-200']"
                    >
                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', (form[toggle.key] as boolean) ? 'translate-x-4' : 'translate-x-0']" />
                    </button>
                </div>
            </div>

            <!-- Default role -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Default role</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Role assigned to new members upon registration.</p>
                </div>
                <div class="p-5">
                    <AppSelect
                        label="Default member role"
                        v-model="form.default_role"
                        :options="roleOptions"
                        :error="form.errors.default_role"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save membership settings</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
