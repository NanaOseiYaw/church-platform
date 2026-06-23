<script setup lang="ts">
/**
 * AttendanceTable
 *
 * The main marking table used on the Session Show page.
 * Tracks local status changes and exposes them via v-model:attendees.
 *
 * Features:
 *  – Live search by name
 *  – "Mark All Present" bulk action
 *  – Per-row status toggle via AttendanceCheckInButton
 *  – Unsaved-changes badge + save button
 *  – Readonly mode when session is completed/cancelled or user lacks permission
 */

import { ref, computed } from 'vue'
import { Search, CheckCircle2, Save, Loader2, Users } from 'lucide-vue-next'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AttendanceCheckInButton from '@/Components/Attendance/AttendanceCheckInButton.vue'
import AttendanceStatusBadge from '@/Components/Attendance/AttendanceStatusBadge.vue'
import type { AttendeeRow, AttendanceStatus } from '@/types'

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = withDefaults(defineProps<{
    modelValue: AttendeeRow[]
    readonly?: boolean
    saving?: boolean
}>(), {
    readonly: false,
    saving: false,
})

const emit = defineEmits<{
    'update:modelValue': [rows: AttendeeRow[]]
    save: []
}>()

// ── Local state ────────────────────────────────────────────────────────────────

const search = ref('')

const filteredRows = computed(() =>
    props.modelValue.filter(r =>
        r.name.toLowerCase().includes(search.value.toLowerCase())
    )
)

const changedCount = computed(() =>
    props.modelValue.filter(r => r.status !== null).length
)

// ── Actions ────────────────────────────────────────────────────────────────────

function updateStatus(userId: number, status: AttendanceStatus | null) {
    const updated = props.modelValue.map(r =>
        r.user_id === userId ? { ...r, status } : r
    )
    emit('update:modelValue', updated)
}

function markAllPresent() {
    const updated = props.modelValue.map(r => ({ ...r, status: 'present' as AttendanceStatus }))
    emit('update:modelValue', updated)
}

function clearAll() {
    const updated = props.modelValue.map(r => ({ ...r, status: null }))
    emit('update:modelValue', updated)
}

// Quick stats bar
const presentCount = computed(() => props.modelValue.filter(r => r.status === 'present').length)
const lateCount    = computed(() => props.modelValue.filter(r => r.status === 'late').length)
const absentCount  = computed(() => props.modelValue.filter(r => r.status === 'absent').length)
const excusedCount = computed(() => props.modelValue.filter(r => r.status === 'excused').length)
const unmarkedCount = computed(() => props.modelValue.filter(r => r.status === null).length)
</script>

<template>
    <div class="space-y-3">

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Search -->
            <div class="relative flex-1 min-w-48">
                <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-neutral-400 pointer-events-none" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search members…"
                    class="w-full pl-9 pr-3 py-2 text-sm bg-white border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                />
            </div>

            <template v-if="!readonly">
                <!-- Mark All Present -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 rounded-lg transition-colors"
                    @click="markAllPresent"
                >
                    <CheckCircle2 class="w-3.5 h-3.5" />
                    Mark all present
                </button>

                <!-- Clear -->
                <button
                    type="button"
                    class="px-3 py-2 text-sm font-medium text-neutral-600 hover:text-neutral-800 hover:bg-neutral-50 border border-neutral-200 rounded-lg transition-colors"
                    @click="clearAll"
                >
                    Clear all
                </button>

                <!-- Save -->
                <button
                    type="button"
                    :disabled="saving"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-brand-600 hover:bg-brand-700 rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    @click="emit('save')"
                >
                    <Loader2 v-if="saving"  class="w-3.5 h-3.5 animate-spin" />
                    <Save    v-else         class="w-3.5 h-3.5" />
                    {{ saving ? 'Saving…' : 'Save attendance' }}
                </button>
            </template>
        </div>

        <!-- Mini stats bar -->
        <div class="flex items-center gap-4 text-xs text-neutral-500 px-1">
            <span class="font-semibold text-emerald-600">{{ presentCount }} present</span>
            <span v-if="lateCount"    class="text-amber-600">{{ lateCount }} late</span>
            <span v-if="excusedCount" class="text-blue-600">{{ excusedCount }} excused</span>
            <span v-if="absentCount"  class="text-rose-600">{{ absentCount }} absent</span>
            <span v-if="unmarkedCount" class="ml-auto text-neutral-400">{{ unmarkedCount }} unmarked</span>
        </div>

        <!-- Table -->
        <div class="bg-white border border-neutral-100 rounded-xl overflow-hidden">

            <!-- Empty search state -->
            <div v-if="filteredRows.length === 0" class="py-12 flex flex-col items-center gap-2 text-neutral-400">
                <Users class="w-8 h-8" />
                <p class="text-sm">No members match your search.</p>
            </div>

            <table v-else class="w-full text-sm">
                <thead class="bg-neutral-50 border-b border-neutral-100">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-neutral-500 text-xs uppercase tracking-wide">
                            Member
                        </th>
                        <th class="text-left px-4 py-3 font-medium text-neutral-500 text-xs uppercase tracking-wide hidden sm:table-cell">
                            Check-in
                        </th>
                        <th :class="['px-4 py-3 font-medium text-neutral-500 text-xs uppercase tracking-wide', readonly ? 'text-left' : 'text-right']">
                            Status
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-neutral-50">
                    <tr
                        v-for="row in filteredRows"
                        :key="row.user_id"
                        class="hover:bg-neutral-50/50 transition-colors"
                    >
                        <!-- Member -->
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <AppAvatar :name="row.name" :src="row.avatar" size="sm" />
                                <span class="font-medium text-neutral-800 truncate max-w-[160px]">{{ row.name }}</span>
                            </div>
                        </td>

                        <!-- Check-in time -->
                        <td class="px-4 py-3 text-neutral-400 text-xs hidden sm:table-cell">
                            {{ row.checked_in_at_formatted ?? '—' }}
                        </td>

                        <!-- Status -->
                        <td class="px-4 py-3">
                            <div :class="['flex items-center', readonly ? 'justify-start' : 'justify-end']">
                                <!-- Readonly: show badge -->
                                <AttendanceStatusBadge v-if="readonly" :status="row.status" size="sm" />

                                <!-- Editable: show toggle buttons -->
                                <AttendanceCheckInButton
                                    v-else
                                    :status="row.status"
                                    @update:status="val => updateStatus(row.user_id, val)"
                                />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Legend (edit mode) -->
        <p v-if="!readonly" class="text-xs text-neutral-400 px-1">
            <span class="font-medium">P</span> = Present ·
            <span class="font-medium">L</span> = Late ·
            <span class="font-medium">E</span> = Excused ·
            <span class="font-medium">A</span> = Absent ·
            Click the active button again to clear.
        </p>
    </div>
</template>
