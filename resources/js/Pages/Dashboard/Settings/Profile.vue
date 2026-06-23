<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Church {
    id: number
    name: string
    display_name: string | null
    tagline: string | null
    description: string | null
    mission: string | null
    vision: string | null
    founded_year: number | null
    registration_number: string | null
    address: string | null
    phone: string | null
    email: string | null
    timezone: string | null
    language: string | null
}

const props = defineProps<{ church: Church }>()

const form = useForm({
    name:                props.church.name,
    display_name:        props.church.display_name        ?? '',
    tagline:             props.church.tagline             ?? '',
    description:         props.church.description         ?? '',
    mission:             props.church.mission             ?? '',
    vision:              props.church.vision              ?? '',
    founded_year:        props.church.founded_year        ? String(props.church.founded_year) : '',
    registration_number: props.church.registration_number ?? '',
    address:             props.church.address             ?? '',
    phone:               props.church.phone               ?? '',
    email:               props.church.email               ?? '',
    timezone:            props.church.timezone            ?? 'UTC',
    language:            props.church.language            ?? 'en',
})

function submit() {
    form.put('/dashboard/settings/profile')
}

const timezones = [
    { value: 'UTC',                    label: 'UTC' },
    { value: 'Africa/Accra',           label: 'Africa / Accra (GMT+0)' },
    { value: 'Africa/Lagos',           label: 'Africa / Lagos (GMT+1)' },
    { value: 'Africa/Nairobi',         label: 'Africa / Nairobi (GMT+3)' },
    { value: 'Europe/London',          label: 'Europe / London' },
    { value: 'Europe/Amsterdam',       label: 'Europe / Amsterdam' },
    { value: 'America/New_York',       label: 'America / New York' },
    { value: 'America/Chicago',        label: 'America / Chicago' },
    { value: 'America/Los_Angeles',    label: 'America / Los Angeles' },
    { value: 'Australia/Sydney',       label: 'Australia / Sydney' },
    { value: 'Asia/Kolkata',           label: 'Asia / Kolkata' },
]

const languages = [
    { value: 'en',   label: 'English' },
    { value: 'fr',   label: 'French' },
    { value: 'de',   label: 'German' },
    { value: 'es',   label: 'Spanish' },
    { value: 'pt',   label: 'Portuguese' },
    { value: 'nl',   label: 'Dutch' },
    { value: 'tw',   label: 'Twi' },
    { value: 'yo',   label: 'Yoruba' },
    { value: 'sw',   label: 'Swahili' },
]
</script>

<template>
    <SettingsLayout section="profile">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Church Profile</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Your church's identity — name, mission, contact information, and locale settings.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl space-y-5">

            <!-- Identity -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Identity</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">How your church is named across the platform.</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <AppInput
                            label="Official name"
                            v-model="form.name"
                            :error="form.errors.name"
                            required
                            placeholder="Grace Community Church"
                        />
                    </div>
                    <AppInput
                        label="Display name"
                        v-model="form.display_name"
                        :error="form.errors.display_name"
                        placeholder="Grace Church (shorter name)"
                    />
                    <AppInput
                        label="Tagline"
                        v-model="form.tagline"
                        :error="form.errors.tagline"
                        placeholder="A Place to Belong"
                    />
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Description
                            <span class="text-neutral-400 font-normal ml-1">optional</span>
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="A brief description of your church for the public website..."
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 resize-none"
                            :class="{ 'border-rose-400': form.errors.description }"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-rose-500">{{ form.errors.description }}</p>
                    </div>
                </div>
            </div>

            <!-- Mission & Vision -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Mission &amp; Vision</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Define your church's purpose and direction.</p>
                </div>
                <div class="p-5 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Mission statement</label>
                        <textarea
                            v-model="form.mission"
                            rows="3"
                            placeholder="What is the mission of your church..."
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 resize-none"
                            :class="{ 'border-rose-400': form.errors.mission }"
                        />
                        <p v-if="form.errors.mission" class="mt-1 text-xs text-rose-500">{{ form.errors.mission }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Vision statement</label>
                        <textarea
                            v-model="form.vision"
                            rows="3"
                            placeholder="What is the vision your church is working toward..."
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 resize-none"
                            :class="{ 'border-rose-400': form.errors.vision }"
                        />
                        <p v-if="form.errors.vision" class="mt-1 text-xs text-rose-500">{{ form.errors.vision }}</p>
                    </div>
                </div>
            </div>

            <!-- Contact information -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Contact information</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Shown on the public website contact page and footer.</p>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        label="Physical address"
                        v-model="form.address"
                        :error="form.errors.address"
                        placeholder="123 Church Street, City, Country"
                    />
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <AppInput
                            label="Phone number"
                            v-model="form.phone"
                            :error="form.errors.phone"
                            placeholder="+233 24 000 0000"
                        />
                        <AppInput
                            label="Email address"
                            type="email"
                            v-model="form.email"
                            :error="form.errors.email"
                            placeholder="info@yourchurch.com"
                        />
                    </div>
                </div>
            </div>

            <!-- Details -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Details</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Official registration and founding information.</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <AppInput
                        label="Year founded"
                        v-model="form.founded_year"
                        :error="form.errors.founded_year"
                        placeholder="1995"
                        type="number"
                    />
                    <AppInput
                        label="Registration number"
                        v-model="form.registration_number"
                        :error="form.errors.registration_number"
                        placeholder="GH-0000-2001"
                    />
                </div>
            </div>

            <!-- Localisation -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Localisation</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Timezone and language used across the platform.</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <AppSelect
                        label="Timezone"
                        v-model="form.timezone"
                        :options="timezones"
                        :error="form.errors.timezone"
                    />
                    <AppSelect
                        label="Language"
                        v-model="form.language"
                        :options="languages"
                        :error="form.errors.language"
                    />
                </div>
            </div>

            <!-- Footer actions -->
            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">
                    ✓ Saved successfully
                </p>
                <p v-else-if="Object.keys(form.errors).length" class="text-xs text-rose-500">
                    Please fix the errors above.
                </p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">
                    Save profile
                </AppButton>
            </div>

        </form>
    </SettingsLayout>
</template>
