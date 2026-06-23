<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import PriorityBadge from '@/Components/Dashboard/PriorityBadge.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { deptColor } from '@/composables/useDepartment'
import type { Announcement } from '@/types'
import { Pin, Eye, Building2, MoreHorizontal, Edit2, Trash2, Globe, Megaphone } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{
    announcement: Announcement
    canEdit?: boolean
    canDelete?: boolean
    canPin?: boolean
    canPublish?: boolean
}>()

const emit   = defineEmits<{ deleted: [id: number]; pinToggled: [id: number] }>()
const auth   = useAuthStore()
const toasts = useNotificationStore()
const menuOpen = ref(false)

// ── Display helpers ─────────────────────────────────────────────────────────────
// date_label is pre-formatted by AnnouncementResource — no new Date() needed.

const excerpt = computed(() => {
    const text = props.announcement.body.replace(/<[^>]+>/g, ' ').trim()
    return text.length > 140 ? text.slice(0, 140) + '…' : text
})

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

function confirmDelete() {
    menuOpen.value = false
    if (!confirm(`Delete "${props.announcement.title}"?`)) return
    router.delete(`/dashboard/announcements/${props.announcement.id}`, {
        onSuccess: () => { toasts.success('Announcement deleted.'); emit('deleted', props.announcement.id) },
        onError:   () => toasts.error('Could not delete.'),
    })
}

function togglePin() {
    menuOpen.value = false
    router.patch(`/dashboard/announcements/${props.announcement.id}/pin`, {}, {
        preserveScroll: true,
        onSuccess: () => emit('pinToggled', props.announcement.id),
    })
}

function togglePublish() {
    menuOpen.value = false
    router.patch(`/dashboard/announcements/${props.announcement.id}/publish`, {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <article
        class="group relative bg-white border border-neutral-100 rounded-xl overflow-hidden hover:shadow-sm hover:border-neutral-200 transition-all duration-150"
        :class="{ 'border-l-4 !border-l-amber-400': announcement.is_pinned }"
    >
        <div class="p-5">
            <!-- ── Top row: badges + date + menu ──────────────────────────── -->
            <div class="flex items-center gap-2 mb-3 flex-wrap">
                <PriorityBadge :priority="announcement.priority" />

                <!-- Draft / Scheduled badge -->
                <span
                    v-if="statusBadge.label"
                    :class="['inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold', statusBadge.classes]"
                >
                    {{ statusBadge.label }}
                </span>

                <!-- Pinned -->
                <span
                    v-if="announcement.is_pinned"
                    class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-600"
                >
                    <Pin class="w-2.5 h-2.5" /> Pinned
                </span>

                <!-- Department badge -->
                <span
                    v-if="announcement.department"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-neutral-100 text-neutral-600"
                    :style="{ background: deptColor(announcement.department.color) + '18', color: deptColor(announcement.department.color) }"
                >
                    <span>{{ announcement.department.icon ?? '🏛' }}</span>
                    {{ announcement.department.name }}
                </span>

                <!-- Visibility badge (when no department badge shown) -->
                <span
                    v-else-if="announcement.visibility === 'public' || announcement.is_church_wide"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-brand-50 text-brand-600"
                >
                    <Globe class="w-2.5 h-2.5" /> Public
                </span>
                <span
                    v-else-if="announcement.visibility === 'members_only'"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-neutral-100 text-neutral-600"
                >
                    Members
                </span>
                <span
                    v-else-if="announcement.visibility === 'private'"
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-neutral-100 text-neutral-500"
                >
                    Private
                </span>

                <!-- Date -->
                <span class="ml-auto text-[11px] text-neutral-400 shrink-0">
                    {{ announcement.date_label }}
                </span>

                <!-- Context menu -->
                <div
                    v-if="canEdit || canDelete || canPin || canPublish"
                    class="relative shrink-0"
                    @click.stop
                >
                    <button
                        class="p-1 rounded-lg hover:bg-neutral-100 text-neutral-300 group-hover:text-neutral-400 transition-colors"
                        @click="menuOpen = !menuOpen"
                    >
                        <MoreHorizontal class="w-4 h-4" />
                    </button>

                    <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="menuOpen"
                            class="absolute right-0 top-7 w-44 bg-white border border-neutral-100 rounded-xl shadow-xl z-20 overflow-hidden p-1"
                        >
                            <Link
                                v-if="canEdit"
                                :href="`/dashboard/announcements/${announcement.id}/edit`"
                                class="flex items-center gap-2 px-2.5 py-2 text-sm text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 rounded-lg transition-colors"
                                @click="menuOpen = false"
                            >
                                <Edit2 class="w-3.5 h-3.5" /> Edit
                            </Link>
                            <button
                                v-if="canPin"
                                class="w-full flex items-center gap-2 px-2.5 py-2 text-sm text-neutral-600 hover:bg-neutral-50 rounded-lg transition-colors"
                                @click="togglePin"
                            >
                                <Pin class="w-3.5 h-3.5" />
                                {{ announcement.is_pinned ? 'Unpin' : 'Pin' }}
                            </button>
                            <button
                                v-if="canPublish"
                                class="w-full flex items-center gap-2 px-2.5 py-2 text-sm text-neutral-600 hover:bg-neutral-50 rounded-lg transition-colors"
                                @click="togglePublish"
                            >
                                <Megaphone class="w-3.5 h-3.5" />
                                {{ announcement.status === 'published' ? 'Unpublish' : 'Publish' }}
                            </button>
                            <button
                                v-if="canDelete"
                                class="w-full flex items-center gap-2 px-2.5 py-2 text-sm text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                @click="confirmDelete"
                            >
                                <Trash2 class="w-3.5 h-3.5" /> Delete
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- ── Title ────────────────────────────────────────────────── -->
            <Link
                :href="`/dashboard/announcements/${announcement.id}`"
                class="block text-sm font-semibold text-neutral-900 hover:text-brand-700 transition-colors leading-snug mb-2"
            >
                {{ announcement.title }}
            </Link>

            <!-- ── Body excerpt ──────────────────────────────────────────── -->
            <p class="text-sm text-neutral-500 leading-relaxed line-clamp-2">
                {{ excerpt }}
            </p>

            <!-- ── Footer ───────────────────────────────────────────────── -->
            <div class="flex items-center gap-3 mt-4 pt-3 border-t border-neutral-50">
                <!-- Author -->
                <div v-if="announcement.creator" class="flex items-center gap-1.5 min-w-0">
                    <AppAvatar :name="announcement.creator.name" :src="announcement.creator.avatar" size="xs" />
                    <span class="text-[11px] text-neutral-500 truncate">{{ announcement.creator.name }}</span>
                </div>

                <!-- Read count -->
                <div v-if="(announcement.reads_count ?? 0) > 0" class="flex items-center gap-1 text-[11px] text-neutral-400 ml-auto shrink-0">
                    <Eye class="w-3 h-3" />
                    {{ announcement.reads_count }}
                </div>

                <!-- Read more -->
                <Link
                    :href="`/dashboard/announcements/${announcement.id}`"
                    class="text-[11px] font-medium text-brand-600 hover:text-brand-800 transition-colors shrink-0"
                    :class="{ 'ml-auto': !(announcement.reads_count ?? 0) }"
                >
                    Read more →
                </Link>
            </div>
        </div>
    </article>
</template>
