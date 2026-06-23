<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface Settings {
    email_enabled: boolean
    in_app_enabled: boolean
    task_reminders: boolean
    event_reminders: boolean
    attendance_reminders: boolean
    welcome_email: boolean
    announcement_alerts: boolean
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    email_enabled:        props.settings.email_enabled        ?? true,
    in_app_enabled:       props.settings.in_app_enabled       ?? true,
    task_reminders:       props.settings.task_reminders       ?? true,
    event_reminders:      props.settings.event_reminders      ?? true,
    attendance_reminders: props.settings.attendance_reminders ?? false,
    welcome_email:        props.settings.welcome_email        ?? true,
    announcement_alerts:  props.settings.announcement_alerts  ?? true,
})

function submit() {
    form.put('/dashboard/settings/notifications')
}

interface Row {
    key: keyof typeof form.data
    label: string
    description: string
    group: string
    comingSoon?: boolean
}

const rows: Row[] = [
    {
        group: 'Channels',
        key: 'email_enabled',
        label: 'Email notifications',
        description: 'Email delivery is coming soon — members currently receive in-app notifications only.',
        comingSoon: true,
    },
    {
        group: 'Channels',
        key: 'in_app_enabled',
        label: 'In-app notifications',
        description: 'Show notification bell alerts inside the dashboard.',
    },
    {
        group: 'Triggers',
        key: 'task_reminders',
        label: 'Task reminders',
        description: 'Notify members when tasks are approaching their due date.',
    },
    {
        group: 'Triggers',
        key: 'event_reminders',
        label: 'Event reminders',
        description: 'Notify RSVPed members before upcoming events.',
    },
    {
        group: 'Triggers',
        key: 'attendance_reminders',
        label: 'Attendance reminders',
        description: 'Remind coordinators to record attendance after sessions.',
    },
    {
        group: 'Triggers',
        key: 'welcome_email',
        label: 'Welcome email',
        description: 'Coming soon — depends on email delivery, which is not active yet.',
        comingSoon: true,
    },
    {
        group: 'Triggers',
        key: 'announcement_alerts',
        label: 'Announcement alerts',
        description: 'Notify members when a new announcement is published.',
    },
]

const channels  = rows.filter(r => r.group === 'Channels')
const triggers  = rows.filter(r => r.group === 'Triggers')
</script>

<template>
    <SettingsLayout section="notifications">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Notifications</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Configure which notification channels are active and which events trigger them.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Channels -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Channels</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Enable or disable entire delivery channels globally.</p>
                </div>
                <div v-for="row in channels" :key="row.key" class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">{{ row.label }}</p>
                        <p class="text-xs text-neutral-500 mt-0.5">{{ row.description }}</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span
                            v-if="row.comingSoon"
                            class="text-[10px] font-semibold uppercase tracking-wide text-amber-600 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5"
                        >Soon</span>
                        <button
                            type="button"
                            role="switch"
                            :disabled="row.comingSoon"
                            :aria-checked="row.comingSoon ? false : (form[row.key] as boolean)"
                            @click="!row.comingSoon && ((form[row.key] as boolean) = !(form[row.key] as boolean))"
                            :class="['relative inline-flex h-5 w-9 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', row.comingSoon ? 'cursor-not-allowed opacity-40 bg-neutral-200' : ((form[row.key] as boolean) ? 'cursor-pointer bg-brand-500' : 'cursor-pointer bg-neutral-200')]"
                        >
                            <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', (!row.comingSoon && (form[row.key] as boolean)) ? 'translate-x-4' : 'translate-x-0']" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Triggers -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Event triggers</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Choose which events generate notifications.</p>
                </div>
                <div v-for="row in triggers" :key="row.key" class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">{{ row.label }}</p>
                        <p class="text-xs text-neutral-500 mt-0.5">{{ row.description }}</p>
                    </div>
                    <div class="flex items-center gap-2.5">
                        <span
                            v-if="row.comingSoon"
                            class="text-[10px] font-semibold uppercase tracking-wide text-amber-600 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5"
                        >Soon</span>
                        <button
                            type="button"
                            role="switch"
                            :disabled="row.comingSoon"
                            :aria-checked="row.comingSoon ? false : (form[row.key] as boolean)"
                            @click="!row.comingSoon && ((form[row.key] as boolean) = !(form[row.key] as boolean))"
                            :class="['relative inline-flex h-5 w-9 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', row.comingSoon ? 'cursor-not-allowed opacity-40 bg-neutral-200' : ((form[row.key] as boolean) ? 'cursor-pointer bg-brand-500' : 'cursor-pointer bg-neutral-200')]"
                        >
                            <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', (!row.comingSoon && (form[row.key] as boolean)) ? 'translate-x-4' : 'translate-x-0']" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save preferences</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
