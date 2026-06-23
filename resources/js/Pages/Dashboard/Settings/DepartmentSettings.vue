<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Settings {
    members_can_create: boolean
    coordinators_can_create: boolean
    default_visibility: string
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    members_can_create:      props.settings.members_can_create      ?? false,
    coordinators_can_create: props.settings.coordinators_can_create ?? false,
    default_visibility:      props.settings.default_visibility      ?? 'public',
})

function submit() {
    form.put('/dashboard/settings/depts')
}

const visibilityOptions = [
    { value: 'public',       label: 'Public — visible to everyone' },
    { value: 'members_only', label: 'Members only — authenticated users' },
]
</script>

<template>
    <SettingsLayout section="depts">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Departments</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Control who can create departments and the default visibility for new ones.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Creation permissions -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Creation permissions</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Admins can always create departments. Use these toggles to extend that right to lower roles.
                    </p>
                </div>

                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">Coordinators can create</p>
                        <p class="text-xs text-neutral-500 mt-0.5">Allow coordinators to create new department listings.</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.coordinators_can_create"
                        @click="form.coordinators_can_create = !form.coordinators_can_create"
                        :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', form.coordinators_can_create ? 'bg-brand-500' : 'bg-neutral-200']"
                    >
                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', form.coordinators_can_create ? 'translate-x-4' : 'translate-x-0']" />
                    </button>
                </div>

                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">Members can create</p>
                        <p class="text-xs text-neutral-500 mt-0.5">Allow regular members to create departments (subject to admin approval).</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.members_can_create"
                        @click="form.members_can_create = !form.members_can_create"
                        :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', form.members_can_create ? 'bg-brand-500' : 'bg-neutral-200']"
                    >
                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', form.members_can_create ? 'translate-x-4' : 'translate-x-0']" />
                    </button>
                </div>
            </div>

            <!-- Default visibility -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Default visibility</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Applied when a new department is created without specifying visibility.</p>
                </div>
                <div class="p-5">
                    <AppSelect
                        label="Default department visibility"
                        v-model="form.default_visibility"
                        :options="visibilityOptions"
                        :error="form.errors.default_visibility"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save department settings</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
