<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps<{
    timezones:     Array<{ value: string; label: string }>
    denominations: string[]
}>()

const form = useForm({
    church_name:    '',
    admin_name:     '',
    admin_email:    '',
    admin_password: '',
    tagline:        '',
    timezone:       'UTC',
    denomination:   '',
})

function submit() {
    form.post(route('super-admin.churches.store'))
}
</script>

<template>
    <SuperAdminLayout title="Create Church">

        <!-- ── Page header ─────────────────────────────────────────────────── -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-neutral-900">Create Church</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Provision a new church workspace and admin account.
            </p>
        </div>

        <!-- ── Form card ──────────────────────────────────────────────────── -->
        <form class="max-w-2xl space-y-8" @submit.prevent="submit">

            <!-- Church details section -->
            <div class="bg-white rounded-xl border border-neutral-200 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide">
                    Church Details
                </h2>

                <!-- Church name -->
                <div>
                    <label for="church_name" class="block text-sm font-medium text-neutral-700 mb-1">
                        Church Name <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="church_name"
                        v-model="form.church_name"
                        type="text"
                        maxlength="100"
                        placeholder="Grace Baptist Church"
                        required
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition"
                        :class="{ 'border-rose-500 ring-2 ring-rose-500/20': form.errors.church_name }"
                    />
                    <p v-if="form.errors.church_name" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.church_name }}
                    </p>
                </div>

                <!-- Tagline -->
                <div>
                    <label for="tagline" class="block text-sm font-medium text-neutral-700 mb-1">
                        Tagline <span class="text-neutral-400 font-normal">(optional)</span>
                    </label>
                    <input
                        id="tagline"
                        v-model="form.tagline"
                        type="text"
                        maxlength="160"
                        placeholder="Where faith meets community"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition"
                    />
                    <p v-if="form.errors.tagline" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.tagline }}
                    </p>
                </div>

                <!-- Timezone + Denomination row -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-neutral-700 mb-1">
                            Timezone
                        </label>
                        <select
                            id="timezone"
                            v-model="form.timezone"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition bg-white"
                        >
                            <option
                                v-for="tz in props.timezones"
                                :key="tz.value"
                                :value="tz.value"
                            >
                                {{ tz.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.timezone" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.timezone }}
                        </p>
                    </div>

                    <div>
                        <label for="denomination" class="block text-sm font-medium text-neutral-700 mb-1">
                            Denomination <span class="text-neutral-400 font-normal">(optional)</span>
                        </label>
                        <select
                            id="denomination"
                            v-model="form.denomination"
                            class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition bg-white"
                        >
                            <option value="">None / Non-denominational</option>
                            <option
                                v-for="d in props.denominations"
                                :key="d"
                                :value="d"
                            >
                                {{ d }}
                            </option>
                        </select>
                        <p v-if="form.errors.denomination" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.denomination }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Admin account section -->
            <div class="bg-white rounded-xl border border-neutral-200 p-6 space-y-5">
                <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide">
                    Admin Account
                </h2>

                <!-- Admin name -->
                <div>
                    <label for="admin_name" class="block text-sm font-medium text-neutral-700 mb-1">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="admin_name"
                        v-model="form.admin_name"
                        type="text"
                        maxlength="100"
                        placeholder="John Smith"
                        required
                        autocomplete="name"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition"
                        :class="{ 'border-rose-500 ring-2 ring-rose-500/20': form.errors.admin_name }"
                    />
                    <p v-if="form.errors.admin_name" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.admin_name }}
                    </p>
                </div>

                <!-- Admin email -->
                <div>
                    <label for="admin_email" class="block text-sm font-medium text-neutral-700 mb-1">
                        Email Address <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="admin_email"
                        v-model="form.admin_email"
                        type="email"
                        placeholder="pastor@gracechurch.org"
                        required
                        autocomplete="email"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition"
                        :class="{ 'border-rose-500 ring-2 ring-rose-500/20': form.errors.admin_email }"
                    />
                    <p v-if="form.errors.admin_email" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.admin_email }}
                    </p>
                </div>

                <!-- Admin password -->
                <div>
                    <label for="admin_password" class="block text-sm font-medium text-neutral-700 mb-1">
                        Password <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="admin_password"
                        v-model="form.admin_password"
                        type="password"
                        placeholder="Min. 8 characters"
                        required
                        autocomplete="new-password"
                        class="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm text-neutral-900 placeholder:text-neutral-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 outline-none transition"
                        :class="{ 'border-rose-500 ring-2 ring-rose-500/20': form.errors.admin_password }"
                    />
                    <p v-if="form.errors.admin_password" class="mt-1 text-xs text-rose-600">
                        {{ form.errors.admin_password }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-400">
                        The church admin can change this after first login.
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    :aria-busy="form.processing"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                >
                    {{ form.processing ? 'Creating…' : 'Create Church' }}
                </button>
                <a
                    :href="route('super-admin.index')"
                    class="px-5 py-2 text-sm text-neutral-500 hover:text-neutral-700 transition-colors"
                >
                    Cancel
                </a>
            </div>

        </form>

    </SuperAdminLayout>
</template>