<script setup lang="ts">
/**
 * MemberAttendanceCard
 *
 * Compact summary card for a member's attendance stats.
 * Used on the Member history page header and in member profile views.
 */
import { TrendingUp } from 'lucide-vue-next'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import type { MemberAttendanceStats } from '@/types'

defineProps<{
    name: string
    avatar: string | null
    email?: string
    stats: MemberAttendanceStats
}>()
</script>

<template>
    <div class="bg-white border border-neutral-100 rounded-2xl p-6">
        <div class="flex items-start gap-4">
            <AppAvatar :name="name" :src="avatar" size="lg" />

            <div class="flex-1 min-w-0">
                <h2 class="text-lg font-semibold text-neutral-900 truncate">{{ name }}</h2>
                <p v-if="email" class="text-sm text-neutral-400 truncate">{{ email }}</p>

                <!-- Rate bar -->
                <div v-if="stats.rate !== null" class="mt-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs text-neutral-500">Attendance rate</span>
                        <span class="text-sm font-semibold" :class="stats.rate >= 80 ? 'text-emerald-600' : stats.rate >= 60 ? 'text-amber-600' : 'text-rose-600'">
                            {{ stats.rate }}%
                        </span>
                    </div>
                    <div class="h-1.5 bg-neutral-100 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="stats.rate >= 80 ? 'bg-emerald-500' : stats.rate >= 60 ? 'bg-amber-500' : 'bg-rose-500'"
                            :style="{ width: `${stats.rate}%` }"
                        />
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1 text-emerald-500 bg-emerald-50 rounded-lg px-2 py-1">
                <TrendingUp class="w-3.5 h-3.5" />
                <span class="text-xs font-semibold">{{ stats.rate ?? '—' }}%</span>
            </div>
        </div>

        <!-- Stat pills -->
        <div class="grid grid-cols-4 gap-3 mt-5 pt-5 border-t border-neutral-50">
            <div class="text-center">
                <p class="text-lg font-semibold text-emerald-600">{{ stats.present }}</p>
                <p class="text-xs text-neutral-400 mt-0.5">Present</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-amber-600">{{ stats.late }}</p>
                <p class="text-xs text-neutral-400 mt-0.5">Late</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-blue-600">{{ stats.excused }}</p>
                <p class="text-xs text-neutral-400 mt-0.5">Excused</p>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-rose-500">{{ stats.absent }}</p>
                <p class="text-xs text-neutral-400 mt-0.5">Absent</p>
            </div>
        </div>
    </div>
</template>
