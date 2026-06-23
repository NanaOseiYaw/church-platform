<script setup lang="ts">
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { useAuthStore } from '@/stores/useAuthStore'
import type { Event, Announcement, Task, DashboardStats, AttendanceSession } from '@/types'
import {
    CalendarDays, Megaphone, CheckSquare, Users,
    Building2, Clock, AlertCircle, ArrowRight, TrendingUp,
    CalendarCheck2
} from 'lucide-vue-next'
import AttendanceStatusBadge from '@/Components/Attendance/AttendanceStatusBadge.vue'
import RecentActivityWidget from '@/Components/Dashboard/RecentActivityWidget.vue'
import UpcomingAssignmentsWidget from '@/Components/Dashboard/UpcomingAssignmentsWidget.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps<{
    upcomingEvents:      Event[]
    recentAnnouncements: Announcement[]
    myTasks:             Task[]
    stats:               DashboardStats | null
    recentSessions:      AttendanceSession[]
    recentActivity:      { id: number; action: string; target_name: string | null; created_at: string; actor: { id: number; name: string; avatar: string | null } | null }[]
    myUpcomingAssignments: {
        id: number
        status: string
        plan_title: string
        plan_status: string
        scheduled_at_formatted: string
        position: string | null
    }[]
}>()

const auth = useAuthStore()

function greeting() {
    const h = new Date().getHours()   // current time — intentional
    if (h < 12) return 'Good morning'
    if (h < 17) return 'Good afternoon'
    return 'Good evening'
}

/** Strip HTML tags for plain-text previews (announcement body is rich HTML). */
function stripHtml(html: string): string {
    return html.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
}

const priorityColors: Record<string, string> = {
    urgent: 'text-rose-600 bg-rose-50',
    high:   'text-orange-600 bg-orange-50',
    medium: 'text-amber-600 bg-amber-50',
    low:    'text-neutral-500 bg-neutral-100',
}

const statusColors: Record<string, string> = {
    pending:     'bg-neutral-100 text-neutral-600',
    in_progress: 'bg-blue-50 text-blue-600',
    completed:   'bg-emerald-50 text-emerald-600',
    overdue:     'bg-rose-50 text-rose-600',
}

const statCards = [
    { label: 'Total Members',       key: 'total_members',      icon: Users,        color: 'text-brand-600 bg-brand-50' },
    { label: 'Upcoming Events',     key: 'upcoming_events',    icon: CalendarDays, color: 'text-brand-600 bg-brand-50' },
    { label: 'Pending Tasks',       key: 'pending_tasks',      icon: CheckSquare,  color: 'text-amber-600 bg-amber-50' },
    { label: 'Active Departments',  key: 'active_departments', icon: Building2,    color: 'text-emerald-600 bg-emerald-50' },
]
</script>

<template>
    <DashboardLayout title="Dashboard">
        <!-- Greeting -->
        <div class="mb-8">
            <h2 class="text-2xl font-semibold text-neutral-900">
                {{ greeting() }}, {{ auth.user?.name?.split(' ')[0] }}. 👋
            </h2>
            <p class="text-neutral-500 text-sm mt-1">Here's what's happening in your church today.</p>
        </div>

        <!-- Admin stats grid -->
        <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div
                v-for="card in statCards"
                :key="card.key"
                class="bg-white border border-neutral-100 rounded-2xl p-5"
            >
                <div class="flex items-center justify-between mb-3">
                    <div :class="['w-9 h-9 rounded-xl flex items-center justify-center', card.color]">
                        <component :is="card.icon" class="w-4.5 h-4.5 w-[18px] h-[18px]" />
                    </div>
                    <TrendingUp class="w-3.5 h-3.5 text-emerald-400" />
                </div>
                <p class="text-2xl font-semibold text-neutral-900">
                    {{ stats[card.key as keyof DashboardStats]?.toLocaleString() ?? '—' }}
                </p>
                <p class="text-xs text-neutral-500 mt-0.5">{{ card.label }}</p>
            </div>
        </div>

        <!-- Three-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Upcoming events -->
            <div class="bg-white border border-neutral-100 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-semibold text-neutral-900 text-sm flex items-center gap-2">
                        <CalendarDays class="w-4 h-4 text-brand-500" />
                        Upcoming Events
                    </h3>
                    <Link href="/dashboard/events" class="text-xs text-brand-600 hover:text-brand-700 font-medium flex items-center gap-1">
                        View all <ArrowRight class="w-3 h-3" />
                    </Link>
                </div>

                <div v-if="upcomingEvents.length" class="space-y-3">
                    <div
                        v-for="event in upcomingEvents"
                        :key="event.id"
                        class="flex items-start gap-3 p-3 rounded-xl hover:bg-neutral-50 transition-colors cursor-pointer"
                    >
                        <div class="w-10 h-10 rounded-xl bg-brand-50 flex flex-col items-center justify-center shrink-0">
                            <span class="text-xs font-bold text-brand-700 leading-none">
                                {{ event.start_month }}
                            </span>
                            <span class="text-sm font-bold text-brand-700 leading-none">
                                {{ event.start_day }}
                            </span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-neutral-900 truncate">{{ event.title }}</p>
                            <p class="text-xs text-neutral-400 mt-0.5">
                                {{ event.start_time_formatted }}
                                <span v-if="event.location"> · {{ event.location }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div v-else class="text-center py-8">
                    <CalendarDays class="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                    <p class="text-xs text-neutral-400">No upcoming events</p>
                </div>
            </div>

            <!-- My tasks -->
            <div class="bg-white border border-neutral-100 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-semibold text-neutral-900 text-sm flex items-center gap-2">
                        <CheckSquare class="w-4 h-4 text-amber-500" />
                        My Tasks
                    </h3>
                    <Link href="/dashboard/tasks" class="text-xs text-brand-600 hover:text-brand-700 font-medium flex items-center gap-1">
                        View all <ArrowRight class="w-3 h-3" />
                    </Link>
                </div>

                <div v-if="myTasks.length" class="space-y-2">
                    <div
                        v-for="task in myTasks"
                        :key="task.id"
                        class="flex items-center gap-3 p-3 rounded-xl hover:bg-neutral-50 transition-colors cursor-pointer"
                    >
                        <div :class="['w-2 h-2 rounded-full shrink-0', task.is_overdue ? 'bg-rose-500' : task.status === 'in_progress' ? 'bg-blue-500' : 'bg-neutral-300']" />
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-neutral-900 truncate">{{ task.title }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span :class="['text-xs px-1.5 py-0.5 rounded-md font-medium', priorityColors[task.priority]]">
                                    {{ task.priority }}
                                </span>
                                <span v-if="task.due_at" class="text-xs text-neutral-400 flex items-center gap-1">
                                    <Clock class="w-2.5 h-2.5" />
                                    {{ task.due_at_short }}
                                </span>
                            </div>
                        </div>
                        <AlertCircle v-if="task.is_overdue" class="w-4 h-4 text-rose-500 shrink-0" />
                    </div>
                </div>

                <div v-else class="text-center py-8">
                    <CheckSquare class="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                    <p class="text-xs text-neutral-400">You're all caught up!</p>
                </div>
            </div>

            <!-- Announcements -->
            <div class="bg-white border border-neutral-100 rounded-2xl p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="font-semibold text-neutral-900 text-sm flex items-center gap-2">
                        <Megaphone class="w-4 h-4 text-brand-500" />
                        Announcements
                    </h3>
                    <Link href="/dashboard/announcements" class="text-xs text-brand-600 hover:text-brand-700 font-medium flex items-center gap-1">
                        View all <ArrowRight class="w-3 h-3" />
                    </Link>
                </div>

                <div v-if="recentAnnouncements.length" class="space-y-3">
                    <div
                        v-for="ann in recentAnnouncements"
                        :key="ann.id"
                        class="p-3 rounded-xl hover:bg-neutral-50 transition-colors cursor-pointer"
                    >
                        <div class="flex items-start gap-2 mb-1">
                            <span v-if="ann.is_pinned" class="text-[9px] font-bold uppercase tracking-widest text-brand-600 bg-brand-50 px-1.5 py-0.5 rounded shrink-0 mt-0.5">
                                Pinned
                            </span>
                            <p class="text-sm font-medium text-neutral-900 line-clamp-1">{{ ann.title }}</p>
                        </div>
                        <p class="text-xs text-neutral-400 line-clamp-2">{{ stripHtml(ann.body) }}</p>
                        <p class="text-xs text-neutral-300 mt-1.5">{{ ann.date_label }}</p>
                    </div>
                </div>

                <div v-else class="text-center py-8">
                    <Megaphone class="w-8 h-8 text-neutral-200 mx-auto mb-2" />
                    <p class="text-xs text-neutral-400">No announcements yet</p>
                </div>
            </div>
        </div>

        <!-- Attendance widget (admin/coordinator only) -->
        <div
            v-if="auth.can('attendance.view') && recentSessions?.length"
            class="mt-6 bg-white border border-neutral-100 rounded-2xl p-6"
        >
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-neutral-900 text-sm flex items-center gap-2">
                    <CalendarCheck2 class="w-4 h-4 text-brand-500" />
                    Recent Attendance
                </h3>
                <Link
                    href="/dashboard/attendance"
                    class="text-xs text-brand-600 hover:text-brand-700 font-medium flex items-center gap-1"
                >
                    View all <ArrowRight class="w-3 h-3" />
                </Link>
            </div>

            <div class="space-y-2">
                <Link
                    v-for="session in recentSessions"
                    :key="session.id"
                    :href="`/dashboard/attendance/${session.id}`"
                    class="flex items-center gap-4 p-3 rounded-xl hover:bg-neutral-50 transition-colors"
                >
                    <!-- Date chip -->
                    <div class="w-10 h-10 rounded-xl bg-neutral-50 border border-neutral-100 flex flex-col items-center justify-center shrink-0">
                        <span class="text-[9px] font-semibold text-neutral-500 uppercase leading-none">{{ session.scheduled_month }}</span>
                        <span class="text-sm font-bold text-neutral-700 leading-none mt-0.5">{{ session.scheduled_day }}</span>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-neutral-800 truncate">{{ session.title }}</p>
                        <p class="text-xs text-neutral-400 mt-0.5">{{ session.type_label }}</p>
                    </div>

                    <!-- Rate -->
                    <div class="text-right shrink-0">
                        <p
                            v-if="session.attendance_rate != null"
                            :class="[
                                'text-sm font-semibold',
                                session.attendance_rate >= 80 ? 'text-emerald-600' :
                                session.attendance_rate >= 60 ? 'text-amber-600' : 'text-rose-600'
                            ]"
                        >{{ session.attendance_rate }}%</p>
                        <p class="text-xs text-neutral-400">
                            {{ session.present_count ?? 0 }}/{{ session.attendances_count ?? 0 }}
                        </p>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Upcoming assignments widget — shown to all users who have assignments -->
        <UpcomingAssignmentsWidget
            v-if="myUpcomingAssignments.length > 0"
            :assignments="myUpcomingAssignments"
            class="mt-6"
        />

        <!-- Recent activity widget (audit.view permission required) -->
        <RecentActivityWidget
            v-if="auth.can('audit.view') && recentActivity !== undefined"
            :activities="recentActivity"
            class="mt-6"
        />
    </DashboardLayout>
</template>
