<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import ChannelConnectionCard from '@/Components/Sermons/ChannelConnectionCard.vue'
import { Youtube, Link2, ArrowLeft, AlertCircle, Info } from 'lucide-vue-next'
import type { ChannelConnection } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    connections: ChannelConnection[]
    stats: {
        total_sermons:  number
        synced_sermons: number
        featured:       number
    }
}>()

// ── Connect form ───────────────────────────────────────────────────────────────

const form = useForm({
    provider:             'youtube',
    channel_input:        '',
    sync_frequency_hours: 24,
    custom_api_key:       '',
})

const showAdvanced = ref(false)

function connect() {
    form.post('/dashboard/sermons/channel', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            showAdvanced.value = false
        },
    })
}
</script>

<template>
    <DashboardLayout
        title="YouTube Channel"
        :breadcrumbs="[
            { label: 'Sermons',  href: '/dashboard/sermons' },
            { label: 'Channel Connection' },
        ]"
    >
        <!-- Header -->
        <div class="flex items-center justify-between mb-8 gap-4 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">Channel Connection</h1>
                <p class="text-sm text-neutral-500 mt-0.5">
                    Connect your YouTube channel to automatically sync sermons.
                </p>
            </div>
            <AppButton href="/dashboard/sermons" variant="outline" size="sm">
                <ArrowLeft class="w-4 h-4" /> Back to Sermons
            </AppButton>
        </div>

        <!-- Stats row -->
        <div class="grid grid-cols-3 gap-3 mb-8">
            <div class="bg-white border border-neutral-100 rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-neutral-900 tabular-nums">{{ stats.total_sermons }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">Total sermons</p>
            </div>
            <div class="bg-white border border-neutral-100 rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-brand-600 tabular-nums">{{ stats.synced_sermons }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">Auto-synced</p>
            </div>
            <div class="bg-white border border-neutral-100 rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-amber-500 tabular-nums">{{ stats.featured }}</p>
                <p class="text-xs text-neutral-500 mt-0.5">Featured</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <!-- ── Left: connect form ─────────────────────────────────────── -->
            <div class="lg:col-span-2">
                <div class="bg-white border border-neutral-100 rounded-2xl p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-9 h-9 bg-red-50 rounded-lg flex items-center justify-center shrink-0">
                            <Youtube class="w-5 h-5 text-red-500" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-neutral-900">Connect YouTube Channel</h2>
                            <p class="text-xs text-neutral-500">Paste a URL, @handle, or channel ID</p>
                        </div>
                    </div>

                    <form @submit.prevent="connect" class="space-y-4">
                        <!-- Channel input -->
                        <div>
                            <label class="block text-xs font-semibold text-neutral-600 mb-1.5">
                                Channel URL or ID
                            </label>
                            <div class="relative">
                                <Link2 class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400 pointer-events-none" />
                                <input
                                    v-model="form.channel_input"
                                    type="text"
                                    placeholder="https://youtube.com/@YourChurch"
                                    required
                                    class="w-full h-10 pl-9 pr-3 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                />
                            </div>
                            <p
                                v-if="form.errors.channel_input"
                                class="mt-1 text-xs text-rose-600 flex items-center gap-1"
                            >
                                <AlertCircle class="w-3 h-3 shrink-0" />
                                {{ form.errors.channel_input }}
                            </p>
                            <p class="text-[10px] text-neutral-400 mt-1">
                                Accepts: @handle, channel URL, or UC… channel ID
                            </p>
                        </div>

                        <!-- Provider select (YouTube only for now) -->
                        <div>
                            <label class="block text-xs font-semibold text-neutral-600 mb-1.5">Provider</label>
                            <select
                                v-model="form.provider"
                                class="w-full h-10 pl-3 pr-8 text-sm border border-neutral-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                            >
                                <option value="youtube">YouTube</option>
                            </select>
                        </div>

                        <!-- Advanced settings toggle -->
                        <button
                            type="button"
                            class="text-xs text-brand-600 hover:text-brand-700 font-medium flex items-center gap-1"
                            @click="showAdvanced = !showAdvanced"
                        >
                            <Info class="w-3.5 h-3.5" />
                            {{ showAdvanced ? 'Hide' : 'Show' }} advanced settings
                        </button>

                        <template v-if="showAdvanced">
                            <!-- Sync frequency -->
                            <div>
                                <label class="block text-xs font-semibold text-neutral-600 mb-1.5">
                                    Sync every (hours)
                                </label>
                                <input
                                    v-model.number="form.sync_frequency_hours"
                                    type="number"
                                    min="1"
                                    max="168"
                                    class="w-full h-10 px-3 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500"
                                />
                            </div>

                            <!-- Custom API key -->
                            <div>
                                <label class="block text-xs font-semibold text-neutral-600 mb-1.5">
                                    Custom API key <span class="font-normal text-neutral-400">(optional)</span>
                                </label>
                                <input
                                    v-model="form.custom_api_key"
                                    type="password"
                                    autocomplete="off"
                                    placeholder="AIza…"
                                    class="w-full h-10 px-3 text-sm border border-neutral-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-500"
                                />
                                <p class="text-[10px] text-neutral-400 mt-1">
                                    Use your own Google Cloud API key for higher quota
                                </p>
                            </div>
                        </template>

                        <AppButton
                            type="submit"
                            variant="primary"
                            size="md"
                            class="w-full"
                            :loading="form.processing"
                            :disabled="form.processing || !form.channel_input"
                        >
                            <Youtube class="w-4 h-4" />
                            Connect &amp; Sync
                        </AppButton>
                    </form>
                </div>
            </div>

            <!-- ── Right: connected channels ──────────────────────────────── -->
            <div class="lg:col-span-3 space-y-4">
                <h2 class="text-sm font-semibold text-neutral-900 mb-3">Connected Channels</h2>

                <div v-if="connections.length" class="space-y-3">
                    <ChannelConnectionCard
                        v-for="c in connections"
                        :key="c.id"
                        :connection="c"
                        :can-manage="true"
                    />
                </div>

                <div
                    v-else
                    class="bg-neutral-50 border-2 border-dashed border-neutral-200 rounded-2xl p-10 text-center"
                >
                    <Youtube class="w-8 h-8 text-neutral-300 mx-auto mb-3" />
                    <p class="text-sm font-medium text-neutral-500">No channels connected yet</p>
                    <p class="text-xs text-neutral-400 mt-1">
                        Connect your YouTube channel to start syncing sermons automatically.
                    </p>
                </div>

                <!-- How it works note -->
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-xs text-blue-700 space-y-1">
                    <p class="font-semibold">How automatic sync works</p>
                    <ul class="list-disc list-inside space-y-0.5 text-blue-600">
                        <li>Sermons sync from your YouTube channel automatically every 24 hours</li>
                        <li>New videos appear in your sermon library within minutes of syncing</li>
                        <li>Use the sync button to pull updates immediately</li>
                        <li>Deleting a sermon here only hides it — it stays on YouTube</li>
                    </ul>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
