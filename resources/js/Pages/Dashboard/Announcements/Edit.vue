<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import CoverImageField from '@/Components/Dashboard/CoverImageField.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import { ArrowLeft, Globe, Users, Building2, Lock, Pin, Star } from 'lucide-vue-next'
import type { Announcement, ContentVisibility } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

interface Dept { id: number; name: string; icon: string | null; color: string | null }

const props = defineProps<{
    announcement: Announcement & { status: string }
    departments:  Dept[]
}>()

// ── Derive initial publish mode from existing data ─────────────────────────────

function derivePublishMode(): 'draft' | 'now' | 'schedule' {
    if (!props.announcement.published_at) return 'draft'
    const pub = new Date(props.announcement.published_at)
    if (pub <= new Date()) return 'now'         // already in the past → treat as "publish now"
    return 'schedule'                            // future → scheduled
}

const publishMode = ref<'draft' | 'now' | 'schedule'>(derivePublishMode())

// Scheduled datetime: pre-fill if it's a future scheduled date
const scheduledAt = ref(
    props.announcement.published_at && publishMode.value === 'schedule'
        ? props.announcement.published_at.slice(0, 16)   // trim to 'YYYY-MM-DDTHH:MM'
        : ''
)

// ── Form ───────────────────────────────────────────────────────────────────────

const form = useForm({
    title:         props.announcement.title,
    body:          props.announcement.body,
    priority:      props.announcement.priority,
    visibility:    (props.announcement.visibility ?? (props.announcement.is_church_wide ? 'public' : (props.announcement.department_id ? 'department_only' : 'members_only'))) as ContentVisibility,
    is_featured:   props.announcement.is_featured ?? false,
    department_id: props.announcement.department_id,
    category:      props.announcement.category ?? '',
    is_pinned:     props.announcement.is_pinned,
    published_at:  ((): string => {
        if (!props.announcement.published_at) return ''
        const pub = new Date(props.announcement.published_at)
        if (pub <= new Date()) return 'now'
        return props.announcement.published_at
    })(),
    expires_at:    props.announcement.expires_at
        ? props.announcement.expires_at.slice(0, 16)
        : '',
    cover_image:        null as File | null,
    remove_cover_image: false,
})

// Clear department when switching away from department_only
watch(() => form.visibility, (v) => {
    if (v !== 'department_only') form.department_id = null
})

// Visibility options
const visibilityOptions = [
    {
        value:       'public' as ContentVisibility,
        label:       'Public',
        description: 'Shown on the website and to all members',
        icon:        Globe,
    },
    {
        value:       'members_only' as ContentVisibility,
        label:       'Members only',
        description: 'All authenticated church members',
        icon:        Users,
    },
    {
        value:       'department_only' as ContentVisibility,
        label:       'Department',
        description: 'Only members of the selected department',
        icon:        Building2,
    },
    {
        value:       'private' as ContentVisibility,
        label:       'Private',
        description: 'Only you and admins can see this',
        icon:        Lock,
    },
]

watch(publishMode, (v) => {
    if (v === 'draft')    form.published_at = ''
    else if (v === 'now') form.published_at = 'now'
    else                  form.published_at = scheduledAt.value
})

watch(scheduledAt, (v) => {
    if (publishMode.value === 'schedule') form.published_at = v
})

// Priority config
const priorities = [
    { value: 'low',    label: 'Low',    dot: 'bg-neutral-400', ring: 'ring-neutral-300' },
    { value: 'medium', label: 'Normal', dot: 'bg-blue-400',    ring: 'ring-blue-300'    },
    { value: 'high',   label: 'High',   dot: 'bg-amber-400',   ring: 'ring-amber-300'   },
    { value: 'urgent', label: 'Urgent', dot: 'bg-rose-500',    ring: 'ring-rose-300'    },
] as const

// Department options for select
const deptOptions = computed(() => [
    { value: '', label: 'Select department…' },
    ...props.departments.map(d => ({
        value: d.id,
        label: `${d.icon ?? '🏛'} ${d.name}`,
    })),
])

function submit() {
    // A file cannot travel in a PUT: PHP only parses multipart bodies on POST.
    // With a new image attached, send POST and let Laravel read it as PUT.
    if (form.cover_image) {
        form.transform(data => ({ ...data, _method: 'put' })).post(`/dashboard/announcements/${props.announcement.id}`)
        return
    }
    form.transform(data => data).put(`/dashboard/announcements/${props.announcement.id}`)
}
</script>

<template>
    <DashboardLayout
        :title="`Edit: ${announcement.title}`"
        :breadcrumbs="[
            { label: 'Dashboard',          href: '/dashboard' },
            { label: 'Announcements',      href: '/dashboard/announcements' },
            { label: announcement.title,   href: `/dashboard/announcements/${announcement.id}` },
            { label: 'Edit' },
        ]"
    >
        <PageHeader :title="`Edit Announcement`" description="Update the content and settings for this announcement.">
            <template #actions>
                <AppButton :href="`/dashboard/announcements/${announcement.id}`" variant="outline" size="sm">
                    <ArrowLeft class="w-4 h-4" /> Back
                </AppButton>
            </template>
        </PageHeader>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-50">

                <!-- ── Section 1: Content ───────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Content</h3>

                    <AppInput
                        label="Title"
                        v-model="form.title"
                        placeholder="e.g. Sunday Service Update"
                        :error="form.errors.title"
                        required
                    />

                    <AppTextarea
                        label="Body"
                        v-model="form.body"
                        placeholder="Write your announcement here…"
                        :rows="8"
                        :error="form.errors.body"
                        required
                    />

                    <CoverImageField
                        v-model:file="form.cover_image"
                        v-model:remove="form.remove_cover_image"
                        :current="props.announcement.cover_image"
                        :error="form.errors.cover_image"
                    />
                </div>

                <!-- ── Section 2: Audience & Visibility ────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Audience & visibility</h3>

                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            type="button"
                            @click="form.visibility = opt.value"
                            :class="[
                                'flex items-center gap-2.5 p-3 rounded-lg border text-sm font-medium transition-colors text-left',
                                form.visibility === opt.value
                                    ? 'border-brand-400 bg-brand-50 text-brand-700'
                                    : 'border-neutral-200 text-neutral-600 hover:border-neutral-300',
                            ]"
                        >
                            <component :is="opt.icon" class="w-4 h-4 shrink-0" />
                            <div>
                                <p class="font-medium leading-none mb-0.5">{{ opt.label }}</p>
                                <p class="text-[11px] font-normal text-neutral-500">{{ opt.description }}</p>
                            </div>
                        </button>
                    </div>

                    <div v-if="form.visibility === 'department_only'">
                        <AppSelect
                            label="Select department"
                            v-model="form.department_id"
                            :options="deptOptions"
                            :error="form.errors.department_id"
                            placeholder="Select department…"
                            required
                        />
                    </div>

                    <p v-if="form.errors.visibility" class="text-xs text-rose-500">{{ form.errors.visibility }}</p>
                </div>

                <!-- ── Section 3: Settings ──────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Settings</h3>

                    <!-- Priority -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-2">Priority</label>
                        <div class="flex gap-2 flex-wrap">
                            <button
                                v-for="p in priorities"
                                :key="p.value"
                                type="button"
                                @click="form.priority = p.value"
                                :class="[
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all',
                                    form.priority === p.value
                                        ? `ring-2 ${p.ring} border-transparent bg-white`
                                        : 'border-neutral-200 text-neutral-500 hover:border-neutral-300',
                                ]"
                            >
                                <span :class="['w-2 h-2 rounded-full', p.dot]" />
                                {{ p.label }}
                            </button>
                        </div>
                        <p v-if="form.errors.priority" class="mt-1 text-xs text-rose-500">{{ form.errors.priority }}</p>
                    </div>

                    <!-- Category -->
                    <AppInput
                        label="Category (optional)"
                        v-model="form.category"
                        placeholder="e.g. Worship, Youth, Admin…"
                        :error="form.errors.category"
                    />
                </div>

                <!-- ── Section 4: Publishing ────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Publishing</h3>

                    <!-- Publish mode -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-2">When to publish</label>
                        <div class="space-y-2">
                            <label
                                v-for="opt in [
                                    { value: 'draft',    label: 'Save as draft',  desc: 'Not visible to members yet' },
                                    { value: 'now',      label: 'Publish now',    desc: 'Immediately visible to all members' },
                                    { value: 'schedule', label: 'Schedule',       desc: 'Go live at a specific date and time' },
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

                    <!-- Expires at -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">
                            Expiry date
                            <span class="font-normal text-neutral-400">(optional)</span>
                        </label>
                        <input
                            type="datetime-local"
                            v-model="form.expires_at"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 transition-colors focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                        />
                        <p class="mt-1 text-xs text-neutral-400">After this time, the announcement will be marked as expired.</p>
                        <p v-if="form.errors.expires_at" class="mt-1 text-xs text-rose-500">{{ form.errors.expires_at }}</p>
                    </div>

                    <!-- Pin -->
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.is_pinned"
                            class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                        />
                        <span class="flex items-center gap-1.5 text-sm text-neutral-700">
                            <Pin class="w-3.5 h-3.5 text-neutral-400" />
                            Pin this announcement to the top of the feed
                        </span>
                    </label>

                    <!-- Feature on public homepage (only meaningful if visibility = 'public') -->
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

                <!-- ── Actions ──────────────────────────────────────────────── -->
                <div class="px-6 py-4 flex items-center justify-end gap-3">
                    <Link
                        :href="`/dashboard/announcements/${announcement.id}`"
                        class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors"
                    >
                        Cancel
                    </Link>
                    <AppButton type="submit" :loading="form.processing">
                        Save changes
                    </AppButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
