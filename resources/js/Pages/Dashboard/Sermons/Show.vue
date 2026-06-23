<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import {
    ArrowLeft, Mic2, Play, Headphones, Clock,
    Calendar, BookOpen, Globe, Lock, ChevronRight,
    Pencil, Star, Eye, EyeOff, Trash2,
} from 'lucide-vue-next'
import { useNotificationStore } from '@/stores/useNotificationStore'
import type { DashboardSermon } from '@/types'
import axios from 'axios'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    sermon:    DashboardSermon
    related:   DashboardSermon[]
    canManage: boolean
    canDelete: boolean
}>()

// ── Local reactive copy for optimistic UI ─────────────────────────────────────

const sermon = ref({ ...props.sermon })

// ── Admin actions ──────────────────────────────────────────────────────────────

const notify  = useNotificationStore()
const loading = ref(false)

const visBadge: Record<string, { label: string; cls: string }> = {
    public:       { label: 'Public',       cls: 'bg-green-50 text-green-700 border-green-200' },
    members_only: { label: 'Members only', cls: 'bg-blue-50 text-blue-700 border-blue-200'   },
    unlisted:     { label: 'Unlisted',     cls: 'bg-neutral-100 text-neutral-500 border-neutral-200' },
}

function vBadge() {
    return visBadge[sermon.value.visibility ?? 'public'] ?? visBadge.public
}

async function toggleFeature() {
    loading.value = true
    try {
        const { data } = await axios.patch(`/dashboard/sermons/${sermon.value.id}/feature`)
        sermon.value.is_featured = data.is_featured
        notify.success(data.message)
    } catch {
        notify.error('Could not update sermon.')
    } finally {
        loading.value = false
    }
}

async function cycleVisibility() {
    loading.value = true
    try {
        const { data } = await axios.patch(`/dashboard/sermons/${sermon.value.id}/visibility`)
        sermon.value.visibility = data.visibility
    } catch {
        notify.error('Could not update visibility.')
    } finally {
        loading.value = false
    }
}

function confirmDelete() {
    if (!confirm(`Remove "${sermon.value.title}"? This cannot be undone.`)) return
    router.delete(`/dashboard/sermons/${sermon.value.id}`, {
        onSuccess: () => router.visit('/dashboard/sermons'),
    })
}

// ── Video / audio embedding ────────────────────────────────────────────────────

/** Detects YouTube or Vimeo and returns an embed URL; null = direct file or unsupported. */
function toEmbedUrl(url: string): string | null {
    // YouTube: watch?v=ID | youtu.be/ID | already /embed/
    const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/)
    if (yt) return `https://www.youtube.com/embed/${yt[1]}?rel=0`

    // Vimeo: vimeo.com/ID | player.vimeo.com/video/ID
    const vm = url.match(/(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/)
    if (vm) return `https://player.vimeo.com/video/${vm[1]}`

    return null
}

const videoEmbed = computed(() =>
    sermon.value.video_url ? toEmbedUrl(sermon.value.video_url) : null,
)

/** True when video_url is a direct file (.mp4 / .webm / .ogv), not an iframe embed. */
const isDirectVideo = computed(() =>
    !!sermon.value.video_url && !videoEmbed.value,
)

/** True when audio_url is present and no video overrides it (no duplication). */
const showAudio = computed(() =>
    !!sermon.value.audio_url && !sermon.value.video_url,
)
</script>

<template>
    <DashboardLayout
        :title="sermon.title"
        :breadcrumbs="[
            { label: 'Dashboard',  href: '/dashboard' },
            { label: 'Sermons',    href: '/dashboard/sermons' },
            { label: sermon.title },
        ]"
    >
        <!-- ── Back / actions ──────────────────────────────────────────────── -->
        <div class="flex items-start justify-between mb-6 gap-3 flex-wrap">
            <AppButton href="/dashboard/sermons" variant="outline" size="sm">
                <ArrowLeft class="w-4 h-4" />
                All Sermons
            </AppButton>

            <div v-if="canManage" class="flex items-center gap-2 flex-wrap">
                <!-- Edit -->
                <AppButton
                    :href="`/dashboard/sermons/${sermon.id}/edit`"
                    variant="outline"
                    size="sm"
                >
                    <Pencil class="w-3.5 h-3.5" />
                    Edit
                </AppButton>

                <!-- Visibility cycle -->
                <button
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors',
                        vBadge().cls,
                    ]"
                    :disabled="loading"
                    title="Click to cycle visibility"
                    @click="cycleVisibility"
                >
                    <Eye   v-if="sermon.visibility === 'public'"        class="w-3.5 h-3.5" />
                    <Lock  v-else-if="sermon.visibility === 'members_only'" class="w-3.5 h-3.5" />
                    <EyeOff v-else                                        class="w-3.5 h-3.5" />
                    {{ vBadge().label }}
                </button>

                <!-- Feature toggle -->
                <button
                    type="button"
                    :title="sermon.is_featured ? 'Unfeature' : 'Feature on homepage'"
                    :class="[
                        'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors',
                        sermon.is_featured
                            ? 'bg-amber-50 text-amber-600 border-amber-200'
                            : 'bg-white text-neutral-500 border-neutral-200 hover:border-amber-300 hover:text-amber-600',
                    ]"
                    :disabled="loading"
                    @click="toggleFeature"
                >
                    <Star class="w-3.5 h-3.5" :class="sermon.is_featured ? 'fill-current' : ''" />
                    {{ sermon.is_featured ? 'Featured' : 'Feature' }}
                </button>

                <!-- Delete -->
                <button
                    v-if="canDelete"
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-neutral-200 text-xs font-semibold text-neutral-500 hover:border-rose-300 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                    @click="confirmDelete"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                    Delete
                </button>
            </div>
        </div>

        <!-- ── Main layout: player + sidebar ──────────────────────────────── -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Left: media + description ───────────────────────────────── -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Series label -->
                <p v-if="sermon.series" class="text-xs font-semibold uppercase tracking-wider text-brand-600">
                    {{ sermon.series }}
                </p>

                <!-- Title -->
                <h1 class="text-2xl font-bold text-neutral-900 leading-snug -mt-2">
                    {{ sermon.title }}
                </h1>

                <!-- Speaker / date row -->
                <div class="flex items-center gap-4 text-sm text-neutral-500 flex-wrap">
                    <span v-if="sermon.speaker" class="font-medium text-neutral-700">{{ sermon.speaker }}</span>
                    <span v-if="sermon.preached_at_formatted" class="flex items-center gap-1.5">
                        <Calendar class="w-3.5 h-3.5 text-neutral-400" />
                        {{ sermon.preached_at_formatted }}
                    </span>
                    <span v-if="sermon.duration" class="flex items-center gap-1.5">
                        <Clock class="w-3.5 h-3.5 text-neutral-400" />
                        {{ sermon.duration }}
                    </span>
                    <span
                        :class="[
                            'inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-medium',
                            sermon.is_public
                                ? 'bg-green-50 text-green-700'
                                : 'bg-neutral-100 text-neutral-500',
                        ]"
                    >
                        <Globe v-if="sermon.is_public" class="w-3 h-3" />
                        <Lock v-else                   class="w-3 h-3" />
                        {{ sermon.is_public ? 'Public' : 'Members only' }}
                    </span>
                </div>

                <!-- ── Video player ─────────────────────────────────────── -->

                <!-- iframe embed (YouTube / Vimeo) -->
                <div
                    v-if="videoEmbed"
                    class="relative w-full rounded-xl overflow-hidden bg-black"
                    style="padding-top: 56.25%;"
                >
                    <iframe
                        :src="videoEmbed"
                        class="absolute inset-0 w-full h-full"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                        :title="sermon.title"
                    />
                </div>

                <!-- Direct video file -->
                <video
                    v-else-if="isDirectVideo"
                    :src="sermon.video_url!"
                    controls
                    class="w-full rounded-xl bg-black"
                    :poster="sermon.thumbnail ?? undefined"
                />

                <!-- Thumbnail fallback (no video) -->
                <div
                    v-else-if="sermon.thumbnail"
                    class="relative w-full rounded-xl overflow-hidden"
                    style="padding-top: 56.25%;"
                >
                    <img
                        :src="sermon.thumbnail"
                        :alt="sermon.title"
                        class="absolute inset-0 w-full h-full object-cover"
                    />
                </div>

                <!-- Brand placeholder (no media at all) -->
                <div
                    v-else-if="!showAudio"
                    class="w-full h-48 rounded-xl bg-gradient-to-br from-brand-50 to-brand-100 flex items-center justify-center"
                >
                    <div class="w-16 h-16 bg-white/60 rounded-2xl flex items-center justify-center">
                        <Mic2 class="w-8 h-8 text-brand-400" />
                    </div>
                </div>

                <!-- ── Audio player ─────────────────────────────────────── -->
                <div
                    v-if="showAudio"
                    class="bg-white border border-neutral-100 rounded-xl p-5"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-9 h-9 bg-amber-50 rounded-lg flex items-center justify-center shrink-0">
                            <Headphones class="w-4.5 h-4.5 text-amber-600" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-neutral-800">Audio Recording</p>
                            <p v-if="sermon.duration" class="text-xs text-neutral-400">{{ sermon.duration }}</p>
                        </div>
                    </div>
                    <audio
                        :src="sermon.audio_url!"
                        controls
                        class="w-full"
                        preload="metadata"
                    />
                </div>

                <!-- ── Description ──────────────────────────────────────── -->
                <div
                    v-if="sermon.description"
                    class="bg-white border border-neutral-100 rounded-xl p-5 lg:p-6"
                >
                    <h2 class="text-sm font-semibold text-neutral-900 mb-3 flex items-center gap-2">
                        <BookOpen class="w-4 h-4 text-neutral-400" />
                        About this sermon
                    </h2>
                    <p class="text-sm text-neutral-600 leading-relaxed whitespace-pre-line">
                        {{ sermon.description }}
                    </p>
                </div>
            </div>

            <!-- ── Right: metadata + related ────────────────────────────────── -->
            <div class="space-y-5">

                <!-- Metadata card -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5 space-y-4">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-400">Details</h3>

                    <dl class="space-y-3">
                        <div v-if="sermon.speaker">
                            <dt class="text-xs text-neutral-400 mb-0.5">Speaker</dt>
                            <dd class="text-sm font-medium text-neutral-800">{{ sermon.speaker }}</dd>
                        </div>
                        <div v-if="sermon.series">
                            <dt class="text-xs text-neutral-400 mb-0.5">Series</dt>
                            <dd class="text-sm font-medium text-brand-700">{{ sermon.series }}</dd>
                        </div>
                        <div v-if="sermon.preached_at_long">
                            <dt class="text-xs text-neutral-400 mb-0.5">Preached</dt>
                            <dd class="text-sm text-neutral-700">{{ sermon.preached_at_long }}</dd>
                        </div>
                        <div v-if="sermon.duration">
                            <dt class="text-xs text-neutral-400 mb-0.5">Duration</dt>
                            <dd class="text-sm text-neutral-700">{{ sermon.duration }}</dd>
                        </div>
                    </dl>

                    <!-- Media availability -->
                    <div class="pt-2 border-t border-neutral-100 flex items-center gap-3">
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-medium',
                                sermon.video_url ? 'bg-blue-50 text-blue-700' : 'bg-neutral-100 text-neutral-400',
                            ]"
                        >
                            <Play class="w-3 h-3" :class="sermon.video_url ? 'fill-blue-600' : ''" />
                            Video
                        </span>
                        <span
                            :class="[
                                'inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-medium',
                                sermon.audio_url ? 'bg-amber-50 text-amber-700' : 'bg-neutral-100 text-neutral-400',
                            ]"
                        >
                            <Headphones class="w-3 h-3" />
                            Audio
                        </span>
                    </div>

                    <!-- Uploader -->
                    <div v-if="sermon.uploader" class="pt-2 border-t border-neutral-100">
                        <p class="text-xs text-neutral-400 mb-1.5">Uploaded by</p>
                        <div class="flex items-center gap-2">
                            <AppAvatar
                                :name="sermon.uploader.name"
                                :src="sermon.uploader.avatar"
                                size="sm"
                            />
                            <span class="text-sm font-medium text-neutral-700">{{ sermon.uploader.name }}</span>
                        </div>
                    </div>
                </div>

                <!-- Related sermons -->
                <div v-if="related.length" class="bg-white border border-neutral-100 rounded-xl p-5">
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-neutral-400 mb-3">
                        {{ sermon.series ? `More from ${sermon.series}` : 'More sermons' }}
                    </h3>
                    <div class="space-y-2">
                        <a
                            v-for="r in related"
                            :key="r.id"
                            :href="`/dashboard/sermons/${r.id}`"
                            class="group flex items-start gap-3 p-2 rounded-lg hover:bg-neutral-50 transition-colors"
                        >
                            <!-- Thumbnail / icon -->
                            <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0 bg-brand-50 flex items-center justify-center">
                                <img
                                    v-if="r.thumbnail"
                                    :src="r.thumbnail"
                                    :alt="r.title"
                                    class="w-full h-full object-cover"
                                />
                                <Mic2 v-else class="w-4 h-4 text-brand-400" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-neutral-800 line-clamp-2 leading-snug group-hover:text-brand-700 transition-colors">
                                    {{ r.title }}
                                </p>
                                <p class="text-[10px] text-neutral-400 mt-0.5">{{ r.preached_at_formatted ?? r.speaker }}</p>
                            </div>
                            <ChevronRight class="w-3.5 h-3.5 text-neutral-300 group-hover:text-brand-500 shrink-0 mt-1 transition-colors" />
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
