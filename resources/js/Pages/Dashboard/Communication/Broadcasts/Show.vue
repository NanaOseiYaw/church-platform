<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppBadge from '@/Components/UI/AppBadge.vue'
import { ArrowLeft, Send, Users, CheckCircle2, XCircle, Clock, Trash2, Pencil } from 'lucide-vue-next'
import type { Broadcast, BroadcastRecipient } from '@/types'

const props = defineProps<{
    broadcast: Broadcast
    recipients: {
        data: BroadcastRecipient[]
        links: any[]
        current_page: number
        last_page: number
    }
    filters: { status?: string }
    canDelete: boolean
    canEdit: boolean
}>()

type BadgeColor = 'success' | 'info' | 'error' | 'warning' | 'neutral'

const statusColor = (s: string): BadgeColor => (({
    sent:    'success',
    failed:  'error',
    pending: 'warning',
}) as Record<string, BadgeColor>)[s] ?? 'neutral'

const broadcastStatusColor = (s: string): BadgeColor => (({
    sent:      'success',
    sending:   'info',
    failed:    'error',
    scheduled: 'warning',
    draft:     'neutral',
}) as Record<string, BadgeColor>)[s] ?? 'neutral'

function filterStatus(s: string) {
    router.get(`/dashboard/communication/broadcasts/${props.broadcast.id}`, { status: s || undefined }, { preserveState: true, replace: true })
}

function fmtDate(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function deleteBroadcast() {
    if (!confirm(`Delete "${props.broadcast.title}"?`)) return
    router.delete(`/dashboard/communication/broadcasts/${props.broadcast.id}`)
}
</script>

<template>
    <DashboardLayout :title="broadcast.title">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 space-y-6 py-6">

            <!-- Back + header -->
            <div class="flex items-center gap-4">
                <Link href="/dashboard/communication/broadcasts" class="p-2 text-neutral-400 hover:text-neutral-700 rounded-lg hover:bg-neutral-100 transition">
                    <ArrowLeft class="w-5 h-5" />
                </Link>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-neutral-900">{{ broadcast.title }}</h1>
                    <p class="text-sm text-neutral-500">Subject: {{ broadcast.subject }}</p>
                </div>
                <AppBadge :color="broadcastStatusColor(broadcast.status)">{{ broadcast.status_label }}</AppBadge>
                <AppButton
                    v-if="canEdit && ['draft','failed'].includes(broadcast.status)"
                    :href="`/dashboard/communication/broadcasts/${broadcast.id}/edit`"
                    variant="outline"
                    size="sm"
                >
                    <Pencil class="w-4 h-4" /> Edit
                </AppButton>
                <AppButton
                    v-if="canDelete"
                    square
                    size="sm"
                    variant="ghost"
                    class="!text-neutral-400 hover:!text-rose-600 hover:!bg-rose-50"
                    @click="deleteBroadcast"
                >
                    <Trash2 class="w-5 h-5" />
                </AppButton>
            </div>

            <!-- Stats strip -->
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-neutral-900">{{ broadcast.recipient_count }}</p>
                    <p class="text-xs text-neutral-500 mt-1 flex items-center justify-center gap-1"><Users class="w-3 h-3" /> Recipients</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-emerald-600">{{ broadcast.delivered_count }}</p>
                    <p class="text-xs text-neutral-500 mt-1 flex items-center justify-center gap-1"><CheckCircle2 class="w-3 h-3" /> Delivered</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-red-500">{{ broadcast.failed_count }}</p>
                    <p class="text-xs text-neutral-500 mt-1 flex items-center justify-center gap-1"><XCircle class="w-3 h-3" /> Failed</p>
                </div>
                <div class="bg-white border border-neutral-200 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-neutral-900">
                        {{ broadcast.recipient_count > 0 ? Math.round((broadcast.delivered_count / broadcast.recipient_count) * 100) : 0 }}%
                    </p>
                    <p class="text-xs text-neutral-500 mt-1">Delivery Rate</p>
                </div>
            </div>

            <!-- Body preview -->
            <div class="bg-white border border-neutral-200 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-neutral-700 mb-3">Message Body</h2>
                <p class="text-sm text-neutral-700 whitespace-pre-wrap">{{ broadcast.body }}</p>
            </div>

            <!-- Recipient table -->
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-neutral-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-700">Recipients</h2>
                    <div class="flex gap-2">
                        <AppButton
                            v-for="s in ['', 'sent', 'failed', 'pending']"
                            :key="s"
                            size="xs"
                            :variant="(filters.status ?? '') === s ? 'primary' : 'ghost'"
                            @click="filterStatus(s)"
                        >
                            {{ s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All' }}
                        </AppButton>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-neutral-50 border-b border-neutral-100">
                        <tr>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Member</th>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Status</th>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Delivered At</th>
                            <th class="text-left px-4 py-2.5 font-medium text-neutral-500">Failure Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-50">
                        <tr v-if="recipients.data.length === 0">
                            <td colspan="4" class="text-center text-neutral-400 py-8">No recipients</td>
                        </tr>
                        <tr v-for="r in recipients.data" :key="r.id" class="hover:bg-neutral-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <AppAvatar :user="r.user" size="sm" />
                                    <span class="text-neutral-800">{{ r.user?.name ?? 'Unknown' }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <AppBadge :color="statusColor(r.status)" size="sm" class="capitalize">{{ r.status }}</AppBadge>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 text-xs">{{ fmtDate(r.sent_at) }}</td>
                            <td class="px-4 py-3 text-red-500 text-xs">{{ r.failure_reason ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="recipients.last_page > 1" class="flex gap-2 justify-center flex-wrap">
                <Link
                    v-for="link in recipients.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-lg border transition',
                        link.active ? 'bg-brand-600 text-white border-brand-600' : 'bg-white border-neutral-200 text-neutral-600 hover:bg-neutral-50',
                        !link.url ? 'opacity-40 pointer-events-none' : '',
                    ]"
                />
            </div>
        </div>
    </DashboardLayout>
</template>
