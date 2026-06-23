<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { ArrowLeft, Globe, Users, Building2, Lock, Calendar, Star } from 'lucide-vue-next'
import type { EventVisibility } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

interface Dept { id: number; name: string; icon: string | null; color: string | null }

const props = defineProps<{
    departments: Dept[]
}>()

// ── Form ───────────────────────────────────────────────────────────────────────

const form = useForm({
    title:         '',
    description:   '',
    location:      '',
    start_at:      '',
    end_at:        '',
    all_day:       false,
    visibility:    'public' as EventVisibility,
    published_at:  'now' as string,  // 'now' | ISO | '' (draft)
    is_featured:   false,
    department_id: null as number | null,
    category:      '',
    rsvp_enabled:  false,
    capacity:      '' as string | number,
    is_recurring:  false,
})

// Publish mode (mirrors announcement pattern)
const publishMode  = ref<'draft' | 'now' | 'schedule'>('now')
const scheduledAt  = ref('')

watch(publishMode, (v) => {
    if (v === 'draft')    form.published_at = ''
    else if (v === 'now') form.published_at = 'now'
    else                  form.published_at = scheduledAt.value
})
watch(scheduledAt, (v) => {
    if (publishMode.value === 'schedule') form.published_at = v
})

// Visibility options
const visibilityOptions = [
    {
        value:       'public' as EventVisibility,
        label:       'Public',
        description: 'Shown on website & to all members',
        icon:        Globe,
    },
    {
        value:       'members_only' as EventVisibility,
        label:       'Members only',
        description: 'All authenticated church members',
        icon:        Users,
    },
    {
        value:       'department_only' as EventVisibility,
        label:       'Department',
        description: 'Only members of the selected department',
        icon:        Building2,
    },
    {
        value:       'private' as EventVisibility,
        label:       'Private',
        description: 'Only you and admins can see this',
        icon:        Lock,
    },
]

// When switching away from department_only, clear department
watch(() => form.visibility, (v) => {
    if (v !== 'department_only') form.department_id = null
})

// Department options
const deptOptions = computed(() => [
    { value: '', label: 'Select department…' },
    ...props.departments.map(d => ({ value: d.id, label: `${d.icon ?? '🏛'} ${d.name}` })),
])

function submit() {
    form.post('/dashboard/events')
}
</script>

<template>
    <DashboardLayout
        title="New Event"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Events',    href: '/dashboard/events' },
            { label: 'New' },
        ]"
    >
        <PageHeader title="New Event" description="Schedule a church or department event.">
            <template #actions>
                <AppButton href="/dashboard/events" variant="outline" size="sm">
                    <ArrowLeft class="w-4 h-4" /> Back
                </AppButton>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-50">

                <!-- ── Section 1: Content ─────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Event details</h3>

                    <AppInput
                        label="Title"
                        v-model="form.title"
                        placeholder="e.g. Sunday Worship Service"
                        :error="form.errors.title"
                        required
                    />

                    <AppInput
                        label="Location"
                        v-model="form.location"
                        placeholder="e.g. Main Sanctuary, Zoom, etc."
                        :error="form.errors.location"
                    />

                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Description <span class="font-normal text-neutral-400">(optional)</span>
                        </label>
                        <AppTextarea
                            v-model="form.description"
                            placeholder="What is this event about?"
                            :rows="5"
                            :error="form.errors.description"
                        />
                    </div>
                </div>

                <!-- ── Section 2: Date & Time ─────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Date & time</h3>

                    <!-- All day toggle -->
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.all_day"
                            class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                        />
                        <span class="text-sm text-neutral-700 flex items-center gap-1.5">
                            <Calendar class="w-3.5 h-3.5 text-neutral-400" />
                            All-day event
                        </span>
                    </label>

                    <!-- Start -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Start <span class="text-rose-500 ml-0.5">*</span>
                        </label>
                        <input
                            :type="form.all_day ? 'date' : 'datetime-local'"
                            v-model="form.start_at"
                            required
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 transition-colors focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                            :class="{ 'border-rose-400': form.errors.start_at }"
                        />
                        <p v-if="form.errors.start_at" class="mt-1 text-xs text-rose-500">{{ form.errors.start_at }}</p>
                    </div>

                    <!-- End -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            End <span class="font-normal text-neutral-400">(optional)</span>
                        </label>
                        <input
                            :type="form.all_day ? 'date' : 'datetime-local'"
                            v-model="form.end_at"
                            :min="form.start_at"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 transition-colors focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                            :class="{ 'border-rose-400': form.errors.end_at }"
                        />
                        <p v-if="form.errors.end_at" class="mt-1 text-xs text-rose-500">{{ form.errors.end_at }}</p>
                    </div>
                </div>

                <!-- ── Section 3: Audience ────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Audience & visibility</h3>

                    <div class="space-y-2">
                        <label
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                            :class="form.visibility === opt.value
                                ? 'border-brand-400 bg-brand-50'
                                : 'border-neutral-200 hover:border-neutral-300'"
                        >
                            <input
                                type="radio"
                                v-model="form.visibility"
                                :value="opt.value"
                                class="mt-0.5 text-brand-600 focus:ring-brand-500"
                            />
                            <div class="flex-1">
                                <p class="text-sm font-medium text-neutral-900 flex items-center gap-1.5">
                                    <component :is="opt.icon" class="w-3.5 h-3.5 text-neutral-400" />
                                    {{ opt.label }}
                                </p>
                                <p class="text-xs text-neutral-500 mt-0.5">{{ opt.description }}</p>
                            </div>
                        </label>
                    </div>

                    <!-- Department selector -->
                    <div v-if="form.visibility === 'department_only'">
                        <AppSelect
                            label="Department"
                            v-model="form.department_id"
                            :options="deptOptions"
                            :error="form.errors.department_id"
                            required
                        />
                    </div>
                </div>

                <!-- ── Section 4: Publishing ──────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Publishing</h3>

                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-2">When to publish</label>
                        <div class="space-y-2">
                            <label
                                v-for="opt in [
                                    { value: 'now',      label: 'Publish now',   desc: 'Live as soon as you save' },
                                    { value: 'schedule', label: 'Schedule',      desc: 'Go live at a specific date and time' },
                                    { value: 'draft',    label: 'Save as draft', desc: 'Not visible to anyone yet' },
                                ]"
                                :key="opt.value"
                                class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                                :class="publishMode === opt.value
                                    ? 'border-brand-400 bg-brand-50'
                                    : 'border-neutral-200 hover:border-neutral-300'"
                            >
                                <input
                                    type="radio"
                                    v-model="publishMode"
                                    :value="opt.value"
                                    class="mt-0.5 text-brand-600 focus:ring-brand-500"
                                />
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ opt.label }}</p>
                                    <p class="text-xs text-neutral-500">{{ opt.desc }}</p>
                                </div>
                            </label>
                        </div>

                        <div v-if="publishMode === 'schedule'" class="mt-3">
                            <label class="block text-sm font-medium text-neutral-700 mb-1.5">Scheduled date & time</label>
                            <input
                                type="datetime-local"
                                v-model="scheduledAt"
                                class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 transition-colors focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                            />
                        </div>

                        <p v-if="form.errors.published_at" class="mt-1.5 text-xs text-rose-500">{{ form.errors.published_at }}</p>
                    </div>

                    <!-- Feature on public homepage (only when visibility = 'public') -->
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.is_featured"
                            :disabled="form.visibility !== 'public'"
                            class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500 disabled:opacity-40"
                        />
                        <span class="flex items-center gap-1.5 text-sm"
                            :class="form.visibility !== 'public' ? 'text-neutral-400' : 'text-neutral-700'"
                        >
                            <Star class="w-3.5 h-3.5 text-amber-400" />
                            Feature on public homepage
                            <span v-if="form.visibility !== 'public'" class="text-xs text-neutral-400">(requires Public visibility)</span>
                        </span>
                    </label>
                </div>

                <!-- ── Section 5: Settings ────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Settings</h3>

                    <AppInput
                        label="Category (optional)"
                        v-model="form.category"
                        placeholder="e.g. Worship, Youth, Outreach…"
                        :error="form.errors.category"
                    />

                    <!-- RSVP -->
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.rsvp_enabled"
                            class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                        />
                        <span class="text-sm text-neutral-700">Enable RSVP (let members mark attendance)</span>
                    </label>

                    <!-- Capacity — only when RSVP is on -->
                    <div v-if="form.rsvp_enabled">
                        <AppInput
                            label="Capacity (optional)"
                            type="number"
                            v-model="form.capacity"
                            placeholder="Leave blank for unlimited"
                            :error="form.errors.capacity"
                        />
                    </div>
                </div>

                <!-- ── Actions ────────────────────────────────────────────── -->
                <div class="px-6 py-4 flex items-center justify-end gap-3">
                    <Link
                        href="/dashboard/events"
                        class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors"
                    >
                        Cancel
                    </Link>
                    <AppButton type="submit" :loading="form.processing">
                        {{ publishMode === 'draft' ? 'Save draft' : publishMode === 'schedule' ? 'Schedule event' : 'Create event' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
