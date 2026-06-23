<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import EventBadge from '@/Components/Dashboard/EventBadge.vue'
import RSVPButtons from '@/Components/Dashboard/RSVPButtons.vue'
import AttachmentList from '@/Components/Media/AttachmentList.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { deptColor } from '@/composables/useDepartment'
import type { Event, RsvpStatus } from '@/types'
import {
    ArrowLeft, Edit2, Trash2, Globe, Building2,
    MapPin, Clock, Calendar, Users, Check, Minus, X, AlertTriangle,
} from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    event:     Event & { status: string }
    myRsvp:    RsvpStatus | null
    canEdit:   boolean
    canDelete: boolean
    canRsvp:   boolean
    canUpload: boolean
}>()

const toasts = useNotificationStore()

// ── Display fields — all pre-formatted by EventResource, no new Date() needed ─

// ── Actions ────────────────────────────────────────────────────────────────────

function confirmDelete() {
    if (! confirm(`Delete "${props.event.title}"?`)) return
    router.delete(`/dashboard/events/${props.event.id}`, {
        onSuccess: () => toasts.success('Event deleted.'),
        onError:   () => toasts.error('Could not delete.'),
    })
}
</script>

<template>
    <DashboardLayout
        :title="event.title"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Events',    href: '/dashboard/events' },
            { label: event.title },
        ]"
    >
        <PageHeader :title="event.title" description="">
            <template #actions>
                <div class="flex items-center gap-2 flex-wrap">
                    <AppButton href="/dashboard/events" variant="outline" size="sm">
                        <ArrowLeft class="w-4 h-4" /> Back
                    </AppButton>
                    <AppButton
                        v-if="canEdit"
                        :href="`/dashboard/events/${event.id}/edit`"
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

            <!-- Cancelled banner -->
            <div
                v-if="event.status === 'cancelled'"
                class="flex items-center gap-3 px-4 py-3 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-700"
            >
                <AlertTriangle class="w-4 h-4 shrink-0" />
                This event has been cancelled.
            </div>

            <!-- Main card -->
            <article class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <!-- Colour band by status -->
                <div
                    class="h-1.5 w-full"
                    :class="{
                        'bg-brand-500':   event.status === 'upcoming',
                        'bg-emerald-500': event.status === 'ongoing',
                        'bg-neutral-300': event.status === 'completed',
                        'bg-rose-400':    event.status === 'cancelled',
                    }"
                />

                <div class="p-6 lg:p-8">
                    <!-- Badges row -->
                    <div class="flex items-center gap-2 flex-wrap mb-5">
                        <EventBadge :status="event.status" />

                        <!-- Department badge -->
                        <span
                            v-if="event.department"
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium"
                            :style="{
                                background: deptColor(event.department.color) + '18',
                                color:      deptColor(event.department.color),
                            }"
                        >
                            <Building2 class="w-2.5 h-2.5" />
                            {{ event.department.icon ?? '🏛' }} {{ event.department.name }}
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-brand-50 text-brand-600"
                        >
                            <Globe class="w-2.5 h-2.5" /> Church-wide
                        </span>

                        <EventBadge :visibility="event.visibility" />

                        <span v-if="event.category" class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-neutral-100 text-neutral-500">
                            {{ event.category }}
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="text-xl lg:text-2xl font-bold text-neutral-900 leading-snug mb-6"
                        :class="{ 'line-through opacity-60': event.status === 'cancelled' }">
                        {{ event.title }}
                    </h1>

                    <!-- Event metadata grid -->
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-neutral-50 rounded-xl mb-6">
                        <div class="flex items-start gap-3">
                            <Calendar class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 mb-0.5">Date</dt>
                                <dd class="text-sm font-medium text-neutral-800">{{ event.date_range }}</dd>
                            </div>
                        </div>

                        <div class="flex items-start gap-3">
                            <Clock class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 mb-0.5">Time</dt>
                                <dd class="text-sm font-medium text-neutral-800">{{ event.time_range }}</dd>
                            </div>
                        </div>

                        <div v-if="event.location" class="flex items-start gap-3">
                            <MapPin class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 mb-0.5">Location</dt>
                                <dd class="text-sm font-medium text-neutral-800">{{ event.location }}</dd>
                            </div>
                        </div>

                        <div v-if="event.capacity" class="flex items-start gap-3">
                            <Users class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-neutral-400 mb-0.5">Capacity</dt>
                                <dd class="text-sm font-medium text-neutral-800">{{ event.capacity }} people</dd>
                            </div>
                        </div>
                    </dl>

                    <!-- Description -->
                    <div
                        v-if="event.description"
                        class="text-sm text-neutral-700 leading-relaxed event-body"
                        v-html="event.description"
                    />

                    <!-- Creator -->
                    <div v-if="event.creator" class="flex items-center gap-2 mt-6 pt-4 border-t border-neutral-50">
                        <AppAvatar :name="event.creator.name" :src="event.creator.avatar" size="xs" />
                        <span class="text-xs text-neutral-500">Created by <span class="font-medium text-neutral-700">{{ event.creator.name }}</span></span>
                    </div>
                </div>
            </article>

            <!-- RSVP card -->
            <div
                v-if="event.rsvp_enabled"
                class="bg-white border border-neutral-100 rounded-xl p-6"
            >
                <h2 class="text-sm font-semibold text-neutral-900 mb-4">RSVP</h2>

                <!-- Counts -->
                <div class="flex items-center gap-4 mb-5">
                    <div class="flex items-center gap-1.5 text-sm">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 flex items-center justify-center">
                            <Check class="w-3 h-3 text-emerald-600" />
                        </span>
                        <span class="font-semibold text-neutral-900">{{ event.going_count ?? 0 }}</span>
                        <span class="text-neutral-400">going</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-sm">
                        <span class="w-5 h-5 rounded-full bg-amber-100 flex items-center justify-center">
                            <Minus class="w-3 h-3 text-amber-600" />
                        </span>
                        <span class="font-semibold text-neutral-900">{{ event.maybe_count ?? 0 }}</span>
                        <span class="text-neutral-400">maybe</span>
                    </div>
                </div>

                <!-- Buttons or closed message -->
                <div v-if="canRsvp && event.status !== 'cancelled' && event.status !== 'completed'">
                    <p class="text-xs text-neutral-500 mb-3">Will you attend?</p>
                    <RSVPButtons
                        :event-id="event.id"
                        :current="myRsvp"
                        :can-rsvp="canRsvp"
                    />
                    <p v-if="myRsvp" class="mt-2 text-xs text-neutral-400">
                        Click your current response to remove it.
                    </p>
                </div>
                <p v-else class="text-xs text-neutral-400 italic">
                    {{ event.status === 'completed' ? 'This event has ended.' : 'RSVP is not available.' }}
                </p>
            </div>

            <!-- Attachments -->
            <AttachmentList
                :files="event.files ?? []"
                attachable-type="event"
                :attachable-id="event.id"
                :can-upload="canUpload"
            />
        </div>
    </DashboardLayout>
</template>

<style scoped>
.event-body :deep(p)       { margin-bottom: 0.75rem; }
.event-body :deep(h2),
.event-body :deep(h3)     { font-weight: 600; margin-bottom: 0.5rem; color: #171717; }
.event-body :deep(ul)     { list-style-type: disc; padding-left: 1.25rem; margin-bottom: 0.75rem; }
.event-body :deep(ol)     { list-style-type: decimal; padding-left: 1.25rem; margin-bottom: 0.75rem; }
.event-body :deep(strong) { font-weight: 600; color: #171717; }
</style>
