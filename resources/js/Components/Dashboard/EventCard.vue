<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import EventBadge from '@/Components/Dashboard/EventBadge.vue'
import RSVPButtons from '@/Components/Dashboard/RSVPButtons.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { deptColor } from '@/composables/useDepartment'
import type { Event, RsvpStatus } from '@/types'
import {
    MapPin, Clock, Users, Building2, Globe,
    MoreHorizontal, Edit2, Trash2, Calendar,
} from 'lucide-vue-next'

const props = defineProps<{
    event:     Event
    myRsvp?:   RsvpStatus | null
    canEdit?:  boolean
    canDelete?: boolean
    canRsvp?:  boolean
}>()

const emit    = defineEmits<{ deleted: [id: number] }>()
const toasts  = useNotificationStore()
const menuOpen = ref(false)

// ── Display helpers (pre-formatted fields from EventResource) ──────────────────
// No new Date() — all formatting is done server-side in EventResource.

const excerpt = computed(() => {
    if (! props.event.description) return ''
    const text = props.event.description.replace(/<[^>]+>/g, ' ').trim()
    return text.length > 120 ? text.slice(0, 120) + '…' : text
})

// ── Actions ────────────────────────────────────────────────────────────────────

function confirmDelete() {
    menuOpen.value = false
    if (! confirm(`Delete "${props.event.title}"?`)) return
    router.delete(`/dashboard/events/${props.event.id}`, {
        onSuccess: () => { toasts.success('Event deleted.'); emit('deleted', props.event.id) },
        onError:   () => toasts.error('Could not delete.'),
    })
}
</script>

<template>
    <article
        class="group relative bg-white border border-neutral-100 rounded-xl overflow-hidden hover:shadow-sm hover:border-neutral-200 transition-all duration-150"
        :class="{ 'opacity-60': event.status === 'cancelled' }"
    >
        <div class="flex gap-0">
            <!-- Date column (uses pre-formatted fields from EventResource) -->
            <div class="w-16 shrink-0 flex flex-col items-center justify-start pt-5 pb-4 bg-neutral-50 border-r border-neutral-100">
                <span class="text-[10px] font-bold uppercase tracking-wider text-neutral-400 leading-none">{{ event.start_month }}</span>
                <span class="text-2xl font-bold text-neutral-900 leading-none mt-0.5">{{ event.start_day }}</span>
                <span class="text-[10px] text-neutral-400 mt-1">{{ event.start_weekday }}</span>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 p-4">
                <!-- Top row: badges + menu -->
                <div class="flex items-center gap-2 mb-2 flex-wrap">
                    <EventBadge :status="event.status" />

                    <!-- Department badge -->
                    <span
                        v-if="event.department"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium"
                        :style="{
                            background: deptColor(event.department.color) + '18',
                            color:      deptColor(event.department.color),
                        }"
                    >
                        <span>{{ event.department.icon ?? '🏛' }}</span>
                        {{ event.department.name }}
                    </span>

                    <!-- Church-wide badge -->
                    <span
                        v-else-if="event.visibility === 'public' || event.visibility === 'members_only'"
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-brand-50 text-brand-600"
                    >
                        <Globe class="w-2.5 h-2.5" /> Church-wide
                    </span>

                    <!-- Category -->
                    <span v-if="event.category" class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-neutral-100 text-neutral-500">
                        {{ event.category }}
                    </span>

                    <!-- Context menu -->
                    <div
                        v-if="canEdit || canDelete"
                        class="relative ml-auto shrink-0"
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
                                class="absolute right-0 top-7 w-40 bg-white border border-neutral-100 rounded-xl shadow-xl z-20 overflow-hidden p-1"
                            >
                                <Link
                                    v-if="canEdit"
                                    :href="`/dashboard/events/${event.id}/edit`"
                                    class="flex items-center gap-2 px-2.5 py-2 text-sm text-neutral-600 hover:bg-neutral-50 rounded-lg transition-colors"
                                    @click="menuOpen = false"
                                >
                                    <Edit2 class="w-3.5 h-3.5" /> Edit
                                </Link>
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

                <!-- Title -->
                <Link
                    :href="`/dashboard/events/${event.id}`"
                    class="block text-sm font-semibold text-neutral-900 hover:text-brand-700 transition-colors leading-snug mb-1.5"
                    :class="{ 'line-through opacity-60': event.status === 'cancelled' }"
                >
                    {{ event.title }}
                </Link>

                <!-- Meta: time + location -->
                <div class="flex items-center gap-3 text-xs text-neutral-500 mb-2 flex-wrap">
                    <span class="flex items-center gap-1">
                        <Clock class="w-3 h-3 text-neutral-400" />
                        {{ event.time_range }}
                    </span>
                    <span v-if="event.location" class="flex items-center gap-1">
                        <MapPin class="w-3 h-3 text-neutral-400" />
                        {{ event.location }}
                    </span>
                </div>

                <!-- Excerpt -->
                <p v-if="excerpt" class="text-xs text-neutral-400 leading-relaxed mb-3">
                    {{ excerpt }}
                </p>

                <!-- Footer: RSVP counts + buttons -->
                <div class="flex items-center justify-between gap-3 pt-2 border-t border-neutral-50">
                    <!-- RSVP counts -->
                    <div v-if="event.rsvp_enabled" class="flex items-center gap-3 text-[11px] text-neutral-400">
                        <span v-if="(event.going_count ?? 0) > 0" class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                            {{ event.going_count }} going
                        </span>
                        <span v-if="(event.maybe_count ?? 0) > 0" class="flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400" />
                            {{ event.maybe_count }} maybe
                        </span>
                    </div>

                    <!-- RSVP compact buttons -->
                    <RSVPButtons
                        v-if="event.rsvp_enabled && canRsvp && event.status !== 'cancelled' && event.status !== 'completed'"
                        :event-id="event.id"
                        :current="myRsvp ?? null"
                        :can-rsvp="!!canRsvp"
                        compact
                    />

                    <!-- Read more -->
                    <Link
                        :href="`/dashboard/events/${event.id}`"
                        class="text-[11px] font-medium text-brand-600 hover:text-brand-800 transition-colors shrink-0 ml-auto"
                    >
                        Details →
                    </Link>
                </div>
            </div>
        </div>
    </article>
</template>
