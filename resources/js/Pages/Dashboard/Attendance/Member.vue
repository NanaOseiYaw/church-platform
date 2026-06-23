<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { ChevronLeft } from 'lucide-vue-next'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import MemberAttendanceCard from '@/Components/Attendance/MemberAttendanceCard.vue'
import AttendanceTimeline from '@/Components/Attendance/AttendanceTimeline.vue'
import AppPagination from '@/Components/UI/AppPagination.vue'
import type { AttendanceRecord, MemberAttendanceStats } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    member: {
        id: number
        name: string
        avatar: string | null
        email: string
        roles: string[]
    }
    stats: MemberAttendanceStats
    history: {
        data: AttendanceRecord[]
        links: any
        meta: any
    }
}>()
</script>

<template>
    <DashboardLayout
        :title="`${member.name} — Attendance`"
        :breadcrumbs="[
            { label: 'Attendance', href: '/dashboard/attendance' },
            { label: member.name },
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

        <!-- Member summary card -->
        <div class="mb-6">
            <MemberAttendanceCard
                :name="member.name"
                :avatar="member.avatar"
                :email="member.email"
                :stats="stats"
            />
        </div>

        <!-- History timeline -->
        <div class="bg-white border border-neutral-100 rounded-2xl p-5">
            <h2 class="text-sm font-semibold text-neutral-900 mb-4">Attendance History</h2>

            <AttendanceTimeline :records="history.data" />

            <!-- Pagination -->
            <div v-if="history.meta?.last_page > 1" class="mt-6">
                <AppPagination :links="history.meta?.links ?? []" />
            </div>
        </div>
    </DashboardLayout>
</template>
