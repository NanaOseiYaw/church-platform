<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Plus, Trash2, Clock } from 'lucide-vue-next'

interface ServiceTime {
    name: string
    day: string
    time: string
    type: string
    location: string
    description: string
}

// Named 'serviceTimes' (not 'church') to avoid overwriting the shared TenantStore prop.
const props = defineProps<{ serviceTimes: ServiceTime[] | null }>()

const form = useForm({
    service_times: (props.serviceTimes ?? []).map(s => ({ ...s })) as ServiceTime[],
})

function addService() {
    form.service_times = [
        ...form.service_times,
        { name: '', day: 'Sunday', time: '09:00', type: 'main', location: '', description: '' },
    ]
}

function removeService(index: number) {
    form.service_times = form.service_times.filter((_, i) => i !== index)
}

function submit() {
    form.put('/dashboard/settings/services')
}

const days  = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const types = [
    { value: 'main',    label: 'Main service' },
    { value: 'midweek', label: 'Midweek service' },
    { value: 'prayer',  label: 'Prayer meeting' },
    { value: 'youth',   label: 'Youth meeting' },
    { value: 'other',   label: 'Other' },
]

const inputCls = 'w-full px-3 py-2 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-colors'
const selectCls = 'w-full px-3 py-2 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 appearance-none focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 transition-colors'
</script>

<template>
    <SettingsLayout section="services">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Service Times</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Manage your church's regular services and meetings. Displayed automatically on the public website.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl">

            <!-- Empty state -->
            <div
                v-if="!form.service_times.length"
                class="bg-white border border-neutral-100 rounded-xl p-10 flex flex-col items-center text-center mb-4"
            >
                <Clock class="w-8 h-8 text-neutral-200 mb-3" />
                <p class="text-sm font-medium text-neutral-600">No services added yet</p>
                <p class="text-xs text-neutral-400 mt-0.5 mb-4">Add your first service time to display it on the website.</p>
                <AppButton type="button" variant="outline" size="sm" @click="addService">
                    <Plus class="w-3.5 h-3.5" /> Add service
                </AppButton>
            </div>

            <!-- Service rows -->
            <div v-else class="space-y-3 mb-4">
                <div
                    v-for="(service, index) in form.service_times"
                    :key="index"
                    class="bg-white border border-neutral-100 rounded-xl overflow-hidden"
                >
                    <!-- Row header -->
                    <div class="flex items-center justify-between px-4 py-3 bg-neutral-50 border-b border-neutral-100">
                        <p class="text-xs font-semibold text-neutral-600 uppercase tracking-wide">
                            Service {{ index + 1 }}
                        </p>
                        <button
                            type="button"
                            @click="removeService(index)"
                            class="p-1 rounded-md hover:bg-rose-50 hover:text-rose-500 text-neutral-400 transition-colors"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Fields -->
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Service name *</label>
                            <input v-model="service.name" :class="inputCls" placeholder="Sunday Morning Service" required />
                            <p v-if="(form.errors as any)[`service_times.${index}.name`]" class="mt-0.5 text-xs text-rose-500">
                                {{ (form.errors as any)[`service_times.${index}.name`] }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Day *</label>
                            <select v-model="service.day" :class="selectCls">
                                <option v-for="d in days" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Time *</label>
                            <input v-model="service.time" type="time" :class="inputCls" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Type</label>
                            <select v-model="service.type" :class="selectCls">
                                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Location / Room</label>
                            <input v-model="service.location" :class="inputCls" placeholder="Main Auditorium" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-neutral-600 mb-1">Notes</label>
                            <input v-model="service.description" :class="inputCls" placeholder="Optional notes shown on the website" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add + Save row -->
            <div class="flex items-center justify-between">
                <AppButton type="button" variant="outline" size="sm" @click="addService">
                    <Plus class="w-3.5 h-3.5" /> Add service
                </AppButton>
                <div class="flex items-center gap-3">
                    <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                    <AppButton type="submit" :loading="form.processing">Save service times</AppButton>
                </div>
            </div>

        </form>
    </SettingsLayout>
</template>
