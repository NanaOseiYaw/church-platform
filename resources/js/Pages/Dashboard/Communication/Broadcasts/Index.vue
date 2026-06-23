<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import EmptyState from '@/Components/Dashboard/EmptyState.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppBadge from '@/Components/UI/AppBadge.vue'
import { Send, Plus, Trash2 } from 'lucide-vue-next'
import type { Broadcast } from '@/types'

const props = defineProps<{
    broadcasts: {
        data: Broadcast[]
        links: any[]
        current_page: number
        last_page: number
    }
    filters: { status?: string }
    canSend: boolean
    canDelete: boolean
}>()

const statuses = ['', 'draft', 'scheduled', 'sending', 'sent', 'failed']
const statusLabel = (s: string) => s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All'

function filterStatus(s: string) {
    router.get('/dashboard/communication/broadcasts', { status: s || undefined }, { preserveState: true, replace: true })
}

type BadgeColor = 'success' | 'info' | 'error' | 'warning' | 'neutral'
const statusColor = (status: string): BadgeColor => (({
    sent:      'success',
    sending:   'info',
    failed:    'error',
    scheduled: 'warning',
    draft:     'neutral',
}) as Record<string, BadgeColor>)[status] ?? 'neutral'

function fmtDate(iso: string | null) {
    if (!iso) return '—'
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

function deleteBroadcast(b: Broadcast) {
    if (!confirm(`Delete "${b.title}"?`)) return
    router.delete(`/dashboard/communication/broadcasts/${b.id}`)
}
</script>

<template>
    <DashboardLayout title="Broadcasts">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6">

            <PageHeader title="Broadcasts" description="Send announcements and messages to your members.">
                <template #actions>
                    <AppButton v-if="canSend" href="/dashboard/communication/broadcasts/create" size="sm">
                        <Plus class="w-4 h-4" /> New Broadcast
                    </AppButton>
                </template>
            </PageHeader>

            <!-- Status filter tabs -->
            <div class="flex gap-2 flex-wrap mb-5">
                <AppButton
                    v-for="s in statuses"
                    :key="s"
                    size="xs"
                    :variant="(filters.status ?? '') === s ? 'primary' : 'outline'"
                    @click="filterStatus(s)"
                >
                    {{ statusLabel(s) }}
                </AppButton>
            </div>

            <!-- Table -->
            <div class="bg-white border border-neutral-200 rounded-xl overflow-hidden">
                <table v-if="broadcasts.data.length > 0" class="w-full text-sm">
                    <thead class="bg-neutral-50 border-b border-neutral-200">
                        <tr>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Title</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Audience</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Status</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Recipients</th>
                            <th class="text-left px-4 py-3 font-medium text-neutral-500">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="b in broadcasts.data" :key="b.id" class="hover:bg-neutral-50 transition">
                            <td class="px-4 py-3">
                                <Link :href="`/dashboard/communication/broadcasts/${b.id}`" class="font-medium text-neutral-800 hover:text-brand-600">
                                    {{ b.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 capitalize">{{ b.audience_type.replace('_', ' ') }}</td>
                            <td class="px-4 py-3">
                                <AppBadge :color="statusColor(b.status)">{{ b.status_label }}</AppBadge>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ b.recipient_count }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ fmtDate(b.sent_at ?? b.created_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <AppButton
                                    v-if="canDelete"
                                    square
                                    size="sm"
                                    variant="ghost"
                                    class="!text-neutral-400 hover:!text-rose-600 hover:!bg-rose-50"
                                    @click="deleteBroadcast(b)"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </AppButton>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-else
                    :icon="Send"
                    title="No broadcasts yet"
                    :description="canSend ? 'Create your first broadcast to reach your members.' : 'Broadcasts your church sends will appear here.'"
                >
                    <template #action>
                        <AppButton v-if="canSend" href="/dashboard/communication/broadcasts/create" size="sm">
                            <Plus class="w-4 h-4" /> New Broadcast
                        </AppButton>
                    </template>
                </EmptyState>
            </div>

            <!-- Pagination -->
            <div v-if="broadcasts.last_page > 1" class="flex gap-2 justify-center flex-wrap mt-5">
                <Link
                    v-for="link in broadcasts.links"
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
