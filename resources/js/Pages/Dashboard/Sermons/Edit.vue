<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { useAuthStore } from '@/stores/useAuthStore'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import {
    ArrowLeft, Mic2, Play, Headphones, Star, Globe, Lock, EyeOff,
} from 'lucide-vue-next'
import type { DashboardSermon, SermonSeries } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    sermon:     DashboardSermon
    seriesList: SermonSeries[]
    speakers:   string[]
}>()

const auth = useAuthStore()

// ── Form (pre-populated) ──────────────────────────────────────────────────────

const form = useForm({
    title:         props.sermon.title         ?? '',
    speaker:       props.sermon.speaker       ?? '',
    description:   props.sermon.description   ?? '',
    series_id:     props.sermon.series_id     ?? ('' as string | number),
    series:        props.sermon.series        ?? '',
    video_url:     props.sermon.video_url     ?? '',
    audio_url:     props.sermon.audio_url     ?? '',
    thumbnail_url: props.sermon.thumbnail     ?? '',   // thumbnail is the resolved URL from resource
    preached_at:   props.sermon.preached_at
                     ? props.sermon.preached_at.slice(0, 10)  // ISO → YYYY-MM-DD for <input type="date">
                     : '',
    visibility:    props.sermon.visibility    ?? 'public',
    is_featured:   props.sermon.is_featured   ?? false,
})

// ── Visibility options ─────────────────────────────────────────────────────────

const visibilityOptions = [
    { value: 'public'       as const, label: 'Public',       desc: 'Shown on the public website',    icon: Globe   },
    { value: 'members_only' as const, label: 'Members only', desc: 'Authenticated church members',   icon: Lock    },
    { value: 'unlisted'     as const, label: 'Unlisted',     desc: 'Only accessible via direct link', icon: EyeOff },
]

// ── Submit ─────────────────────────────────────────────────────────────────────

function submit() {
    form
        .transform(data => ({
            ...data,
            series_id:     data.series_id     ? Number(data.series_id) : null,
            speaker:       data.speaker       || null,
            description:   data.description   || null,
            series:        data.series        || null,
            video_url:     data.video_url     || null,
            audio_url:     data.audio_url     || null,
            thumbnail_url: data.thumbnail_url || null,
            preached_at:   data.preached_at   || null,
        }))
        .patch(`/dashboard/sermons/${props.sermon.id}`)
}
</script>

<template>
    <DashboardLayout
        :title="`Edit — ${sermon.title}`"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Sermons',   href: '/dashboard/sermons' },
            { label: sermon.title, href: `/dashboard/sermons/${sermon.id}` },
            { label: 'Edit' },
        ]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">Edit Sermon</h1>
                <p class="text-sm text-neutral-500 mt-0.5 line-clamp-1">{{ sermon.title }}</p>
            </div>
            <AppButton :href="`/dashboard/sermons/${sermon.id}`" variant="outline" size="sm">
                <ArrowLeft class="w-4 h-4" /> Cancel
            </AppButton>
        </div>

        <!-- YouTube-synced banner -->
        <div
            v-if="sermon.provider !== 'manual'"
            class="mb-5 flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800"
        >
            <span class="shrink-0 mt-0.5 font-semibold">Note:</span>
            This sermon was synced from YouTube. Your edits will be overwritten the next time
            the channel syncs unless you first disconnect the channel.
        </div>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-50">

                <!-- ── Section 1: Core content ──────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900 flex items-center gap-2">
                        <Mic2 class="w-4 h-4 text-neutral-400" />
                        Sermon details
                    </h3>

                    <AppInput
                        label="Title"
                        v-model="form.title"
                        placeholder="e.g. Walking by Faith"
                        :error="form.errors.title"
                        required
                    />

                    <!-- Speaker with datalist autocomplete -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-neutral-700">Speaker</label>
                        <input
                            v-model="form.speaker"
                            type="text"
                            list="speaker-suggestions"
                            placeholder="e.g. Pastor John Smith"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10"
                            :class="{ 'border-rose-400': form.errors.speaker }"
                        />
                        <datalist id="speaker-suggestions">
                            <option v-for="s in speakers" :key="s" :value="s" />
                        </datalist>
                        <p v-if="form.errors.speaker" class="text-xs text-rose-500">{{ form.errors.speaker }}</p>
                    </div>

                    <AppTextarea
                        label="Description"
                        v-model="form.description"
                        placeholder="Brief summary of the sermon…"
                        :rows="4"
                        :error="form.errors.description"
                    />
                </div>

                <!-- ── Section 2: Organisation ──────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Organisation</h3>

                    <!-- Series -->
                    <div class="flex flex-col gap-1.5">
                        <!-- The link is here because this dropdown is where people get stuck:
                             it only lists series that already exist. Same permission as
                             the series page itself, so it never leads to a 403. -->
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-neutral-700">Series</label>
                            <a
                                v-if="auth.can('sermons.edit')"
                                href="/dashboard/sermons/series"
                                class="text-xs font-medium text-brand-600 hover:text-brand-700"
                            >
                                Manage series
                            </a>
                        </div>
                        <select
                            v-model="form.series_id"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10"
                        >
                            <option value="">— No series —</option>
                            <option v-for="s in seriesList" :key="s.id" :value="s.id">
                                {{ s.title }}
                            </option>
                        </select>
                        <p v-if="form.errors.series_id" class="text-xs text-rose-500">{{ form.errors.series_id }}</p>
                    </div>

                    <!-- Preached At -->
                    <div class="flex flex-col gap-1.5">
                        <label class="text-sm font-medium text-neutral-700">Date preached</label>
                        <input
                            v-model="form.preached_at"
                            type="date"
                            class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10"
                        />
                        <p v-if="form.errors.preached_at" class="text-xs text-rose-500">{{ form.errors.preached_at }}</p>
                    </div>
                </div>

                <!-- ── Section 3: Media ─────────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900 flex items-center gap-2">
                        <Play class="w-4 h-4 text-neutral-400" />
                        Media
                    </h3>

                    <AppInput
                        label="Video URL"
                        v-model="form.video_url"
                        type="url"
                        placeholder="https://youtube.com/watch?v=… or direct .mp4"
                        :error="form.errors.video_url"
                    />

                    <AppInput
                        label="Audio URL"
                        v-model="form.audio_url"
                        type="url"
                        placeholder="https://… .mp3"
                        :error="form.errors.audio_url"
                    />

                    <AppInput
                        label="Thumbnail URL"
                        v-model="form.thumbnail_url"
                        type="url"
                        placeholder="https://… .jpg"
                        :error="form.errors.thumbnail_url"
                    />

                    <!-- Thumbnail preview -->
                    <div v-if="form.thumbnail_url" class="rounded-lg overflow-hidden w-32 h-20 bg-neutral-100">
                        <img
                            :src="form.thumbnail_url"
                            alt="Thumbnail preview"
                            class="w-full h-full object-cover"
                            @error="($event.target as HTMLImageElement).style.display = 'none'"
                        />
                    </div>
                </div>

                <!-- ── Section 4: Visibility ────────────────────────────────── -->
                <div class="p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Visibility</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <button
                            v-for="opt in visibilityOptions"
                            :key="opt.value"
                            type="button"
                            @click="form.visibility = opt.value"
                            :class="[
                                'flex items-start gap-2.5 p-3 rounded-lg border text-left transition-colors',
                                form.visibility === opt.value
                                    ? 'border-brand-400 bg-brand-50'
                                    : 'border-neutral-200 hover:border-neutral-300',
                            ]"
                        >
                            <component :is="opt.icon" class="w-4 h-4 mt-0.5 shrink-0"
                                :class="form.visibility === opt.value ? 'text-brand-600' : 'text-neutral-400'" />
                            <div>
                                <p class="text-sm font-medium leading-none mb-0.5"
                                   :class="form.visibility === opt.value ? 'text-brand-700' : 'text-neutral-700'">
                                    {{ opt.label }}
                                </p>
                                <p class="text-[11px] text-neutral-500">{{ opt.desc }}</p>
                            </div>
                        </button>
                    </div>

                    <!-- Feature on homepage -->
                    <label class="flex items-center gap-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="form.is_featured"
                            :disabled="form.visibility !== 'public'"
                            class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500 disabled:opacity-40"
                        />
                        <span class="flex items-center gap-1.5 text-sm"
                            :class="form.visibility !== 'public' ? 'text-neutral-400' : 'text-neutral-700'">
                            <Star class="w-3.5 h-3.5 text-amber-400" />
                            Feature on public homepage
                            <span v-if="form.visibility !== 'public'" class="text-xs text-neutral-400">(requires Public)</span>
                        </span>
                    </label>
                    <p v-if="form.errors.visibility" class="text-xs text-rose-500">{{ form.errors.visibility }}</p>
                </div>

                <!-- ── Actions ──────────────────────────────────────────────── -->
                <div class="px-6 py-4 flex items-center justify-end gap-3">
                    <a
                        :href="`/dashboard/sermons/${sermon.id}`"
                        class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-900 transition-colors"
                    >
                        Cancel
                    </a>
                    <AppButton type="submit" :loading="form.processing">
                        Save changes
                    </AppButton>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
