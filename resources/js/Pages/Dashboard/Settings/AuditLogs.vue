<script setup lang="ts">
import { computed, ref } from 'vue'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { ScrollText, RefreshCw } from 'lucide-vue-next'

interface Actor {
    id: number
    name: string
}
interface LogEntry {
    id: number
    action: string
    model_type: string | null
    model_id: number | null
    actor: Actor | null
    ip_address: string | null
    created_at: string | null
}

const props = defineProps<{ logs: LogEntry[] }>()

const search = ref('')

const filtered = computed(() => {
    const q = search.value.toLowerCase()
    if (!q) return props.logs
    return props.logs.filter(l =>
        l.action.toLowerCase().includes(q) ||
        (l.actor?.name ?? '').toLowerCase().includes(q),
    )
})

function formatAction(action: string): string {
    const parts = action.split('.')
    return parts.map(p => p.replace(/_/g, ' ')).join(' › ')
}

function formatDate(iso: string | null): string {
    if (!iso) return '–'
    return new Date(iso).toLocaleString('en-GB', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

function actionColor(action: string): string {
    if (action.includes('delete') || action.includes('destroy')) return 'bg-rose-50 text-rose-600 border-rose-200'
    if (action.includes('create') || action.includes('store'))   return 'bg-emerald-50 text-emerald-600 border-emerald-200'
    if (action.includes('security'))                              return 'bg-amber-50 text-amber-600 border-amber-200'
    return 'bg-neutral-50 text-neutral-600 border-neutral-200'
}
</script>

<template>
    <SettingsLayout section="audit">

        <div class="mb-7 flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Audit Logs</h1>
                <p class="text-sm text-neutral-500 mt-0.5">
                    A record of admin actions performed on your church's settings.
                </p>
            </div>
            <!-- Search -->
            <input
                v-model="search"
                type="search"
                placeholder="Filter by action or actor…"
                class="h-8 px-3 text-xs bg-white border border-neutral-200 rounded-lg placeholder:text-neutral-400 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 w-56"
            />
        </div>

        <div class="max-w-3xl">

            <!-- Empty state -->
            <div v-if="!logs.length" class="bg-white border border-neutral-100 rounded-xl p-12 flex flex-col items-center text-center">
                <ScrollText class="w-8 h-8 text-neutral-200 mb-3" />
                <p class="text-sm font-medium text-neutral-600">No activity logged yet</p>
                <p class="text-xs text-neutral-400 mt-1">
                    Admin actions on settings, roles, and members will appear here automatically.
                </p>
            </div>

            <!-- Log table -->
            <div v-else class="bg-white border border-neutral-100 rounded-xl overflow-hidden">
                <div v-if="!filtered.length" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No results for "{{ search }}"</p>
                </div>
                <div v-else class="divide-y divide-neutral-50">
                    <div
                        v-for="entry in filtered"
                        :key="entry.id"
                        class="flex items-start gap-3 px-5 py-3.5 hover:bg-neutral-50/50 transition-colors"
                    >
                        <!-- Action badge -->
                        <div class="shrink-0 pt-0.5">
                            <span :class="['inline-flex px-2 py-0.5 rounded-md border text-[10px] font-medium', actionColor(entry.action)]">
                                {{ entry.action.split('.').pop() }}
                            </span>
                        </div>
                        <!-- Details -->
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-neutral-800 capitalize">{{ formatAction(entry.action) }}</p>
                            <p class="text-[11px] text-neutral-400 mt-0.5">
                                by {{ entry.actor?.name ?? 'System' }}
                                <span v-if="entry.ip_address" class="text-neutral-300">· {{ entry.ip_address }}</span>
                            </p>
                        </div>
                        <!-- Timestamp -->
                        <p class="text-[11px] text-neutral-400 shrink-0 pt-0.5 whitespace-nowrap">
                            {{ formatDate(entry.created_at) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Note about future logging -->
            <div v-if="logs.length" class="mt-3 text-xs text-neutral-400 text-center">
                Showing the most recent {{ logs.length }} entries.
            </div>
        </div>

    </SettingsLayout>
</template>
