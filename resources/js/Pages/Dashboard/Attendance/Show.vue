<script setup lang="ts">
/**
 * Attendance Session Show page
 *
 * Displays session metadata, status lifecycle controls, real-time
 * stats, and the interactive attendance marking table.
 *
 * Save flow (batch):
 *   1. User toggles member statuses via AttendanceCheckInButton
 *   2. Changes tracked locally in `attendees` ref
 *   3. "Save attendance" → POST /{session}/save → JSON response
 *   4. Stats updated reactively without a full page reload
 */

import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import {
    ChevronLeft, Play, CheckCircle2, XCircle,
    Trash2, Building2, CalendarDays, MoreHorizontal, CalendarCheck2
} from 'lucide-vue-next'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AttendanceStats from '@/Components/Attendance/AttendanceStats.vue'
import AttendanceTable from '@/Components/Attendance/AttendanceTable.vue'
import AppBadge from '@/Components/UI/AppBadge.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import { useNotificationStore } from '@/stores/useNotificationStore'
import { useAuthStore } from '@/stores/useAuthStore'
import { useRealtimeAttendance } from '@/composables/useRealtimeAttendance'
import type { AttendanceSession, AttendeeRow } from '@/types'
import type { AttendanceUpdatedPayload } from '@/Services/realtime/channels'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    session: AttendanceSession
    attendees: AttendeeRow[]
    canManage: boolean
}>()

const notify = useNotificationStore()
const auth   = useAuthStore()

// ── Local state ────────────────────────────────────────────────────────────────

const localSession   = ref<AttendanceSession>({ ...props.session })
const localAttendees = ref<AttendeeRow[]>([...props.attendees])

// ── Real-time: listen for attendance saves by other users ─────────────────────
// When another coordinator saves attendance on the same session, our stats
// update instantly without a page reload.
useRealtimeAttendance(props.session.id as unknown as string, (payload: AttendanceUpdatedPayload) => {
    localSession.value = {
        ...localSession.value,
        attendances_count: payload.attendances_count,
        present_count:     payload.present_count,
        absent_count:      payload.absent_count,
        late_count:        payload.late_count,
        excused_count:     payload.excused_count,
        attendance_rate:   payload.attendance_rate,
    }
})
const saving        = ref(false)
const deletingModal = ref(false)
const statusLoading = ref(false)

// Readonly when session is completed/cancelled or user can't manage
const isReadonly = computed(() =>
    !props.canManage ||
    localSession.value.status === 'completed' ||
    localSession.value.status === 'cancelled'
)

// ── Status badges ──────────────────────────────────────────────────────────────

const statusConfig: Record<string, { label: string; classes: string }> = {
    planned:   { label: 'Planned',   classes: 'bg-neutral-100 text-neutral-600' },
    active:    { label: 'Active',    classes: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' },
    completed: { label: 'Completed', classes: 'bg-blue-50 text-blue-700' },
    cancelled: { label: 'Cancelled', classes: 'bg-rose-50 text-rose-600' },
}

// ── Type config ────────────────────────────────────────────────────────────────

const typeColors: Record<string, string> = {
    service:   'text-brand-600 bg-brand-50',
    meeting:   'text-blue-600 bg-blue-50',
    rehearsal: 'text-orange-600 bg-orange-50',
    outreach:  'text-emerald-600 bg-emerald-50',
    volunteer: 'text-teal-600 bg-teal-50',
    other:     'text-neutral-600 bg-neutral-100',
}

// ── Session lifecycle ──────────────────────────────────────────────────────────

async function changeStatus(newStatus: 'active' | 'completed' | 'cancelled') {
    if (statusLoading.value) return
    statusLoading.value = true

    try {
        const { data } = await axios.patch(`/dashboard/attendance/${localSession.value.id}/status`, {
            status: newStatus,
        })
        localSession.value = {
            ...localSession.value,
            status: data.status,
            check_in_enabled: data.check_in_enabled,
            check_in_token: data.check_in_token,
        }
        notify.success(`Session marked as ${data.status}.`)
    } catch {
        notify.error('Could not update session status. Please try again.')
    } finally {
        statusLoading.value = false
    }
}

// ── Save attendance ────────────────────────────────────────────────────────────

async function saveAttendance() {
    const toSave = localAttendees.value
        .filter(r => r.status !== null)
        .map(r => ({ user_id: r.user_id, status: r.status!, notes: r.notes ?? '' }))

    if (toSave.length === 0) {
        notify.warning('No attendance marked yet. Toggle member statuses before saving.')
        return
    }

    saving.value = true

    try {
        const { data } = await axios.post(`/dashboard/attendance/${localSession.value.id}/save`, {
            attendances: toSave,
        })

        // Update local session stats
        localSession.value = {
            ...localSession.value,
            attendances_count: data.attendances_count,
            present_count:     data.present_count,
            absent_count:      data.absent_count,
            late_count:        data.late_count,
            excused_count:     data.excused_count,
            attendance_rate:   data.attendance_rate,
        }

        notify.success(data.message ?? 'Attendance saved.')
    } catch {
        notify.error('Failed to save attendance. Please try again.')
    } finally {
        saving.value = false
    }
}

// ── Delete ─────────────────────────────────────────────────────────────────────

function confirmDelete() {
    deletingModal.value = false
    router.delete(`/dashboard/attendance/${localSession.value.id}`)
}
</script>

<template>
    <DashboardLayout
        :title="localSession.title"
        :breadcrumbs="[
            { label: 'Attendance', href: '/dashboard/attendance' },
            { label: localSession.title },
        ]"
    >
        <!-- Back -->
        <Link
            href="/dashboard/attendance"
            class="inline-flex items-center gap-1.5 text-sm text-neutral-500 hover:text-neutral-800 mb-6 transition-colors"
        >
            <ChevronLeft class="w-4 h-4" />
            All sessions
        </Link>

        <!-- Session header card -->
        <div class="bg-white border border-neutral-100 rounded-2xl p-6 mb-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <!-- Left: title + meta -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center flex-wrap gap-2 mb-1">
                        <h1 class="text-xl font-semibold text-neutral-900">{{ localSession.title }}</h1>
                        <span :class="['text-xs px-2.5 py-1 rounded-full font-medium', statusConfig[localSession.status]?.classes]">
                            {{ statusConfig[localSession.status]?.label }}
                        </span>
                        <span :class="['text-xs px-2 py-0.5 rounded-md font-medium', typeColors[localSession.type] ?? 'text-neutral-500 bg-neutral-100']">
                            {{ localSession.type_label }}
                        </span>
                    </div>

                    <div class="flex items-center flex-wrap gap-4 text-sm text-neutral-500 mt-2">
                        <span class="flex items-center gap-1.5">
                            <CalendarDays class="w-3.5 h-3.5" />
                            {{ localSession.scheduled_at_formatted }} · {{ localSession.scheduled_time }}
                        </span>
                        <span v-if="localSession.department" class="flex items-center gap-1.5">
                            <Building2 class="w-3.5 h-3.5" />
                            {{ localSession.department.name }}
                        </span>
                        <span v-if="localSession.event" class="flex items-center gap-1.5">
                            <CalendarDays class="w-3.5 h-3.5" />
                            {{ localSession.event.title }}
                        </span>
                        <a
                            v-if="localSession.service_plan"
                            :href="`/dashboard/scheduling/plans/${localSession.service_plan.id}`"
                            class="flex items-center gap-1.5 text-brand-600 hover:underline"
                        >
                            <CalendarCheck2 class="w-3.5 h-3.5" />
                            {{ localSession.service_plan.title }}
                        </a>
                    </div>

                    <p v-if="localSession.description" class="text-sm text-neutral-500 mt-2">
                        {{ localSession.description }}
                    </p>
                </div>

                <!-- Right: lifecycle actions -->
                <div v-if="canManage" class="flex items-center gap-2 shrink-0">
                    <!-- Activate -->
                    <button
                        v-if="localSession.status === 'planned'"
                        :disabled="statusLoading"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-colors disabled:opacity-60"
                        @click="changeStatus('active')"
                    >
                        <Play class="w-3.5 h-3.5" />
                        Start session
                    </button>

                    <!-- Complete -->
                    <button
                        v-if="localSession.status === 'active'"
                        :disabled="statusLoading"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg transition-colors disabled:opacity-60"
                        @click="changeStatus('completed')"
                    >
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        Complete
                    </button>

                    <!-- Cancel -->
                    <button
                        v-if="['planned','active'].includes(localSession.status)"
                        :disabled="statusLoading"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition-colors disabled:opacity-60"
                        @click="changeStatus('cancelled')"
                    >
                        <XCircle class="w-3.5 h-3.5" />
                        Cancel
                    </button>

                    <!-- Delete -->
                    <button
                        v-if="auth.can('attendance.delete')"
                        class="p-2 text-neutral-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                        title="Delete session"
                        @click="deletingModal = true"
                    >
                        <Trash2 class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="mb-5">
            <AttendanceStats :session="localSession" />
        </div>

        <!-- Attendance table -->
        <div class="bg-white border border-neutral-100 rounded-2xl p-5">
            <h2 class="text-sm font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                Attendance
                <span v-if="isReadonly" class="text-xs font-normal text-neutral-400">(read-only)</span>
            </h2>

            <div v-if="localAttendees.length === 0" class="py-8 text-center text-neutral-400 text-sm">
                No expected attendees found for this session.
                <span v-if="!localSession.department_id && !localSession.event_id">
                    Link a department or event to auto-populate the list.
                </span>
            </div>

            <AttendanceTable
                v-else
                v-model="localAttendees"
                :readonly="isReadonly"
                :saving="saving"
                @save="saveAttendance"
            />
        </div>

        <!-- Delete confirmation modal -->
        <AppModal
            :open="deletingModal"
            title="Delete session"
            @close="deletingModal = false"
        >
            <p class="text-sm text-neutral-600">
                Permanently delete <strong>{{ localSession.title }}</strong> and all its attendance records?
                This cannot be undone.
            </p>
            <template #footer>
                <button
                    class="px-4 py-2 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors"
                    @click="confirmDelete"
                >
                    Delete session
                </button>
                <button
                    class="px-4 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-800 transition-colors"
                    @click="deletingModal = false"
                >
                    Cancel
                </button>
            </template>
        </AppModal>
    </DashboardLayout>
</template>
