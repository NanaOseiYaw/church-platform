<script setup lang="ts">
/**
 * AttendanceTimeline
 *
 * A vertical list of past attendance records grouped by month.
 * Used on the Member history page and as a widget on session show pages.
 */
import { CalendarDays, Building2 } from 'lucide-vue-next'
import AttendanceStatusBadge from '@/Components/Attendance/AttendanceStatusBadge.vue'
import type { AttendanceRecord } from '@/types'

defineProps<{
    records: AttendanceRecord[]
}>()

/** Group records by "Month Year" for section headers. */
function groupByMonth(records: AttendanceRecord[]): { label: string; items: AttendanceRecord[] }[] {
    const map = new Map<string, AttendanceRecord[]>()

    for (const record of records) {
        // Use session date if available, else the record's created_at
        const raw = record.session?.scheduled_at ?? record.created_at ?? ''
        const d = raw ? new Date(raw) : new Date()
        const key = d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })

        if (!map.has(key)) map.set(key, [])
        map.get(key)!.push(record)
    }

    return Array.from(map.entries()).map(([label, items]) => ({ label, items }))
}
</script>

<template>
    <div class="space-y-6">
        <div v-if="records.length === 0" class="py-8 text-center text-neutral-400 text-sm">
            No attendance history yet.
        </div>

        <template v-else>
            <div
                v-for="group in groupByMonth(records)"
                :key="group.label"
                class="space-y-1"
            >
                <!-- Month header -->
                <p class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-2 px-1">
                    {{ group.label }}
                </p>

                <!-- Records -->
                <div
                    v-for="record in group.items"
                    :key="record.id"
                    class="flex items-center gap-3 p-3 bg-white border border-neutral-100 rounded-xl hover:border-neutral-200 transition-colors"
                >
                    <!-- Icon chip -->
                    <div class="w-9 h-9 rounded-lg bg-neutral-50 border border-neutral-100 flex items-center justify-center shrink-0">
                        <CalendarDays class="w-4 h-4 text-neutral-400" />
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-neutral-800 truncate">
                            {{ record.session?.title ?? 'Session' }}
                        </p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-neutral-400">
                                {{ record.session?.scheduled_date_short ?? record.created_at_formatted ?? '—' }}
                            </span>
                            <span v-if="record.session?.type_label" class="text-xs text-neutral-300">·</span>
                            <span v-if="record.session?.type_label" class="text-xs text-neutral-400">
                                {{ record.session.type_label }}
                            </span>
                            <template v-if="record.session?.department">
                                <span class="text-xs text-neutral-300">·</span>
                                <span class="inline-flex items-center gap-1 text-xs text-neutral-400">
                                    <Building2 class="w-3 h-3" />
                                    <!-- department name not in this shape of session, but defensively checking -->
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Check-in time -->
                    <span v-if="record.checked_in_at_formatted" class="text-xs text-neutral-400 shrink-0 hidden sm:block">
                        {{ record.checked_in_at_formatted }}
                    </span>

                    <!-- Status -->
                    <AttendanceStatusBadge :status="record.status" size="sm" />
                </div>
            </div>
        </template>
    </div>
</template>
