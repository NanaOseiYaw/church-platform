<script setup lang="ts">
import { computed } from 'vue'
import FlyerImage from '@/Components/UI/FlyerImage.vue'
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import PriorityBadge from '@/Components/Dashboard/PriorityBadge.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AttachmentList from '@/Components/Media/AttachmentList.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { deptColor } from '@/composables/useDepartment'
import type { Announcement } from '@/types'
import {
    ArrowLeft, Edit2, Trash2, Pin, Globe, Building2,
    Eye, Megaphone, Calendar, Clock,
} from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    announcement: Announcement & { status: string }
    isRead:       boolean
    canEdit:      boolean
    canDelete:    boolean
    canPublish:   boolean
    canPin:       boolean
    canUpload:    boolean
}>()

const toasts = useNotificationStore()

// ── Display helpers ─────────────────────────────────────────────────────────────
// Dates are pre-formatted by AnnouncementResource — no new Date() needed.

const statusBadge = computed(() => {
    const map: Record<string, { label: string; classes: string }> = {
        draft:     { label: 'Draft',     classes: 'bg-neutral-100 text-neutral-500' },
        scheduled: { label: 'Scheduled', classes: 'bg-brand-50 text-brand-600' },
        published: { label: '',          classes: '' },
        expired:   { label: 'Expired',   classes: 'bg-neutral-100 text-neutral-400' },
    }
    return map[props.announcement.status] ?? map.draft
})

// ── Actions ────────────────────────────────────────────────────────────────────

function togglePublish() {
    router.patch(`/dashboard/announcements/${props.announcement.id}/publish`, {}, {
        preserveScroll: true,
    })
}

function togglePin() {
    router.patch(`/dashboard/announcements/${props.announcement.id}/pin`, {}, {
        preserveScroll: true,
    })
}

function confirmDelete() {
    if (!confirm(`Delete "${props.announcement.title}"?`)) return
    router.delete(`/dashboard/announcements/${props.announcement.id}`, {
        onSuccess: () => toasts.success('Announcement deleted.'),
        onError:   () => toasts.error('Could not delete.'),
    })
}
</script>

<template>
    <DashboardLayout
        :title="announcement.title"
        :breadcrumbs="[
            { label: 'Dashboard',      href: '/dashboard' },
            { label: 'Announcements',  href: '/dashboard/announcements' },
            { label: announcement.title },
        ]"
    >
        <PageHeader :title="announcement.title" description="">
            <template #actions>
                <div class="flex items-center gap-2 flex-wrap">
                    <AppButton href="/dashboard/announcements" variant="outline" size="sm">
                        <ArrowLeft class="w-4 h-4" /> Back
                    </AppButton>

                    <!-- Pin: amber toggle — no matching AppButton variant, keep native -->
                    <button
                        v-if="canPin"
                        @click="togglePin"
                        :class="[
                            'inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-medium rounded-lg border transition-colors',
                            announcement.is_pinned
                                ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100'
                                : 'bg-white text-neutral-600 border-neutral-200 hover:bg-neutral-50',
                        ]"
                    >
                        <Pin class="w-4 h-4" />
                        {{ announcement.is_pinned ? 'Unpin' : 'Pin' }}
                    </button>

                    <AppButton
                        v-if="canPublish"
                        :variant="announcement.status === 'published' ? 'outline' : 'primary'"
                        size="sm"
                        @click="togglePublish"
                    >
                        <Megaphone class="w-4 h-4" />
                        {{ announcement.status === 'published' ? 'Unpublish' : 'Publish' }}
                    </AppButton>

                    <AppButton
                        v-if="canEdit"
                        :href="`/dashboard/announcements/${announcement.id}/edit`"
                        variant="outline"
                        size="sm"
                    >
                        <Edit2 class="w-4 h-4" /> Edit
                    </AppButton>

                    <AppButton
                        v-if="canDelete"
                        variant="danger"
                        size="sm"
                        @click="confirmDelete"
                    >
                        <Trash2 class="w-4 h-4" /> Delete
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <div class="max-w-3xl space-y-4">
            <article class="bg-white border border-neutral-100 rounded-xl overflow-hidden">

                <!-- Header band -->
                <div
                    class="h-1.5 w-full"
                    :class="{
                        'bg-rose-500':    announcement.priority === 'urgent',
                        'bg-amber-400':   announcement.priority === 'high',
                        'bg-blue-400':    announcement.priority === 'medium',
                        'bg-neutral-300': announcement.priority === 'low',
                    }"
                />

                <div class="p-6 lg:p-8">
                    <!-- Badges row -->
                    <div class="flex items-center gap-2 flex-wrap mb-4">
                        <PriorityBadge :priority="announcement.priority" />

                        <span
                            v-if="statusBadge.label"
                            :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold', statusBadge.classes]"
                        >
                            {{ statusBadge.label }}
                        </span>

                        <span
                            v-if="announcement.is_pinned"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-600"
                        >
                            <Pin class="w-2.5 h-2.5" /> Pinned
                        </span>

                        <!-- Department -->
                        <span
                            v-if="announcement.department"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium"
                            :style="{
                                background: deptColor(announcement.department.color) + '18',
                                color:      deptColor(announcement.department.color),
                            }"
                        >
                            <Building2 class="w-2.5 h-2.5" />
                            {{ announcement.department.icon ?? '🏛' }}
                            {{ announcement.department.name }}
                        </span>

                        <span
                            v-else-if="announcement.visibility === 'public' || announcement.is_church_wide"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-brand-50 text-brand-600"
                        >
                            <Globe class="w-2.5 h-2.5" /> Public
                        </span>
                        <span
                            v-else-if="announcement.visibility === 'members_only'"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-neutral-100 text-neutral-600"
                        >
                            Members only
                        </span>
                        <span
                            v-else-if="announcement.visibility === 'private'"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-neutral-100 text-neutral-500"
                        >
                            Private
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="text-xl lg:text-2xl font-bold text-neutral-900 leading-snug mb-5">
                        {{ announcement.title }}
                    </h1>

                    <!-- Meta -->
                    <div class="flex items-center gap-4 flex-wrap pb-5 mb-6 border-b border-neutral-100">
                        <!-- Author -->
                        <div v-if="announcement.creator" class="flex items-center gap-2 min-w-0">
                            <AppAvatar :name="announcement.creator.name" :src="announcement.creator.avatar" size="sm" />
                            <div>
                                <p class="text-xs font-semibold text-neutral-800">{{ announcement.creator.name }}</p>
                                <p class="text-[10px] text-neutral-400">Author</p>
                            </div>
                        </div>

                        <!-- Published date -->
                        <div v-if="announcement.published_at_formatted" class="flex items-center gap-1.5 text-xs text-neutral-500">
                            <Calendar class="w-3.5 h-3.5 text-neutral-400" />
                            {{ announcement.published_at_formatted }}
                        </div>

                        <!-- Expiry -->
                        <div v-if="announcement.expires_at_formatted" class="flex items-center gap-1.5 text-xs text-neutral-500">
                            <Clock class="w-3.5 h-3.5 text-neutral-400" />
                            Expires {{ announcement.expires_at_formatted }}
                        </div>

                        <!-- Read count -->
                        <div v-if="(announcement.reads_count ?? 0) > 0" class="flex items-center gap-1 text-xs text-neutral-400 ml-auto shrink-0">
                            <Eye class="w-3.5 h-3.5" />
                            {{ announcement.reads_count }}
                            {{ announcement.reads_count === 1 ? 'read' : 'reads' }}
                        </div>
                    </div>

                    <FlyerImage
                        v-if="announcement.cover_image"
                        :src="announcement.cover_image"
                        :alt="`Image for ${announcement.title}`"
                        :href="announcement.cover_image"
                        class="h-72 rounded-xl mb-6"
                    />

                    <!-- Body -->
                    <div
                        class="text-sm text-neutral-700 leading-relaxed space-y-4 announcement-body"
                        v-html="announcement.body"
                    />
                </div>
            </article>

            <!-- Attachments -->
            <AttachmentList
                :files="announcement.files ?? []"
                attachable-type="announcement"
                :attachable-id="announcement.id"
                :can-upload="canUpload"
            />
        </div>
    </DashboardLayout>
</template>

<style scoped>
.announcement-body :deep(p)        { margin-bottom: 0.75rem; }
.announcement-body :deep(h1),
.announcement-body :deep(h2),
.announcement-body :deep(h3)      { font-weight: 600; margin-bottom: 0.5rem; color: #171717; }
.announcement-body :deep(h1)      { font-size: 1.125rem; }
.announcement-body :deep(h2)      { font-size: 1rem; }
.announcement-body :deep(ul),
.announcement-body :deep(ol)      { padding-left: 1.25rem; margin-bottom: 0.75rem; }
.announcement-body :deep(li)      { margin-bottom: 0.25rem; }
.announcement-body :deep(ul)      { list-style-type: disc; }
.announcement-body :deep(ol)      { list-style-type: decimal; }
.announcement-body :deep(strong)  { font-weight: 600; color: #171717; }
.announcement-body :deep(a)       { color: #4f46e5; text-decoration: underline; }
.announcement-body :deep(blockquote) {
    border-left: 3px solid #e5e7eb;
    padding-left: 1rem;
    color: #6b7280;
    font-style: italic;
}
</style>
