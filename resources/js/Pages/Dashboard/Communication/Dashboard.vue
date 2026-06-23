<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Radio, Send, Users, FileText, Calendar, TrendingUp, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import type { Broadcast } from '@/types'

const props = defineProps<{
    stats: {
        sent_this_month: number
        delivery_rate: number
        scheduled_count: number
    }
    upcoming: Broadcast[]
    recent: Broadcast[]
    canSend: boolean
}>()

const statusColor = (status: string) => ({
    sent:      'bg-emerald-100 text-emerald-700',
    sending:   'bg-blue-100 text-blue-700',
    failed:    'bg-red-100 text-red-700',
    scheduled: 'bg-amber-100 text-amber-700',
    draft:     'bg-neutral-100 text-neutral-600',
})[status] ?? 'bg-neutral-100 text-neutral-600'

function fmtDate(iso: string | null): string {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
    <DashboardLayout title="Communication">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 space-y-8 py-6">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-brand-50 rounded-lg">
                        <Radio class="w-6 h-6 text-brand-600" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-neutral-900">Communication</h1>
                        <p class="text-sm text-neutral-500">Send targeted messages to your congregation</p>
                    </div>
                </div>
                <Link
                    v-if="canSend"
                    href="/dashboard/communication/broadcasts/create"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition"
                >
                    <Send class="w-4 h-4" />
                    New Broadcast
                </Link>
            </div>

            <!-- Quick Actions -->
            <div v-if="canSend" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <Link
                    href="/dashboard/communication/broadcasts/create"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <Send class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">New Broadcast</span>
                </Link>
                <Link
                    href="/dashboard/communication/templates"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <FileText class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">Templates</span>
                </Link>
                <Link
                    href="/dashboard/communication/audiences"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <Users class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">Audiences</span>
                </Link>
                <Link
                    href="/dashboard/communication/broadcasts"
                    class="flex flex-col items-center gap-2 p-4 bg-white border border-neutral-200 rounded-xl hover:border-brand-400 hover:bg-brand-50 transition text-center"
                >
                    <Radio class="w-5 h-5 text-brand-600" />
                    <span class="text-sm font-medium text-neutral-700">All Broadcasts</span>
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <Send class="w-4 h-4 text-neutral-400" />
                        <span class="text-xs text-neutral-500 uppercase tracking-wide font-medium">Sent This Month</span>
                    </div>
                    <p class="text-3xl font-bold text-neutral-900">{{ stats.sent_this_month }}</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <TrendingUp class="w-4 h-4 text-neutral-400" />
                        <span class="text-xs text-neutral-500 uppercase tracking-wide font-medium">Delivery Rate</span>
                    </div>
                    <p class="text-3xl font-bold text-neutral-900">{{ stats.delivery_rate }}%</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <div class="flex items-center gap-2 mb-1">
                        <Calendar class="w-4 h-4 text-neutral-400" />
                        <span class="text-xs text-neutral-500 uppercase tracking-wide font-medium">Scheduled</span>
                    </div>
                    <p class="text-3xl font-bold text-neutral-900">{{ stats.scheduled_count }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Upcoming Broadcasts -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-neutral-700 mb-4 flex items-center gap-2">
                        <Calendar class="w-4 h-4 text-amber-500" /> Upcoming Scheduled
                    </h2>
                    <div v-if="upcoming.length === 0" class="text-sm text-neutral-400 text-center py-6">
                        No scheduled broadcasts
                    </div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="b in upcoming"
                            :key="b.id"
                            :href="`/dashboard/communication/broadcasts/${b.id}`"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-neutral-50 transition"
                        >
                            <div>
                                <p class="text-sm font-medium text-neutral-800">{{ b.title }}</p>
                                <p class="text-xs text-neutral-500">{{ fmtDate(b.scheduled_at) }} · {{ b.recipient_count }} recipients</p>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">Scheduled</span>
                        </Link>
                    </div>
                </div>

                <!-- Recent Broadcasts -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5">
                    <h2 class="text-sm font-semibold text-neutral-700 mb-4 flex items-center gap-2">
                        <CheckCircle2 class="w-4 h-4 text-emerald-500" /> Recent Sent
                    </h2>
                    <div v-if="recent.length === 0" class="text-sm text-neutral-400 text-center py-6">
                        No broadcasts sent yet
                    </div>
                    <div v-else class="space-y-3">
                        <Link
                            v-for="b in recent"
                            :key="b.id"
                            :href="`/dashboard/communication/broadcasts/${b.id}`"
                            class="flex items-center justify-between p-3 rounded-lg hover:bg-neutral-50 transition"
                        >
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-neutral-800 truncate">{{ b.title }}</p>
                                <p class="text-xs text-neutral-500">{{ fmtDate(b.sent_at) }} · {{ b.delivered_count }}/{{ b.recipient_count }} delivered</p>
                            </div>
                            <span :class="['text-xs px-2 py-0.5 rounded-full ml-2 shrink-0', statusColor(b.status)]">
                                {{ b.status_label }}
                            </span>
                        </Link>
                    </div>
                </div>
            </div>

        </div>
    </DashboardLayout>
</template>
