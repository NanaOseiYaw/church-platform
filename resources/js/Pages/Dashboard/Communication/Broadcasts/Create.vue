<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import { Users, ChevronDown, ArrowLeft, Eye, Send, Calendar, Save } from 'lucide-vue-next'
import AppButton from '@/Components/UI/AppButton.vue'
import AppBadge from '@/Components/UI/AppBadge.vue'
import AppModal from '@/Components/UI/AppModal.vue'
import type { BroadcastTemplate, BroadcastAudience } from '@/types'

const props = defineProps<{
    templates: BroadcastTemplate[]
    audiences: BroadcastAudience[]
    departments: { id: number; name: string; icon: string | null; color: string | null }[]
    canSendAll: boolean
}>()

// ── Form ───────────────────────────────────────────────────────────────────────

const form = useForm({
    title:           '',
    subject:         '',
    body:            '',
    audience_type:   'all_members' as string,
    audience_config: null as Record<string, any> | null,
    template_id:     null as number | null,
    scheduled_at:    '',
    action:          'draft' as 'draft' | 'send' | 'schedule',
})

// ── Audience picker ────────────────────────────────────────────────────────────

const audienceOptions = computed(() => {
    const opts = [
        { value: 'all_members',    label: 'All Members',      desc: 'Everyone in your church' },
        { value: 'department',     label: 'Department',       desc: 'Members of a specific department' },
        { value: 'role',           label: 'By Role',          desc: 'Coordinators, assistants…' },
        { value: 'event_attendees',label: 'Event Attendees',  desc: 'People who RSVPed to an event' },
        { value: 'volunteers',     label: 'Volunteers',       desc: 'People with serving assignments' },
    ]
    if (props.canSendAll) {
        opts.push({ value: 'saved_audience', label: 'Saved Audience', desc: 'A reusable preset group' })
    }
    // If coordinator-only, filter to department only
    if (!props.canSendAll) {
        return opts.filter(o => o.value === 'department')
    }
    return opts
})

// Live recipient count
const recipientCount = ref<number | null>(null)
const countLoading   = ref(false)

async function fetchCount() {
    if (!form.audience_type) return
    countLoading.value = true
    try {
        const params = new URLSearchParams({ audience_type: form.audience_type })
        if (form.audience_config) {
            Object.entries(form.audience_config).forEach(([k, v]) =>
                params.append(`audience_config[${k}]`, String(v))
            )
        }
        const resp = await window.axios.get(`/dashboard/communication/audiences/resolve-count?${params}`)
        recipientCount.value = resp.data.count
    } catch {
        recipientCount.value = null
    } finally {
        countLoading.value = false
    }
}

watch([() => form.audience_type, () => form.audience_config], fetchCount, { deep: true, immediate: true })

// ── Template picker ────────────────────────────────────────────────────────────

function applyTemplate(t: BroadcastTemplate) {
    form.subject     = t.subject
    form.body        = t.body
    form.template_id = t.id
    if (!form.title) form.title = t.name
}

// ── Variable chips ─────────────────────────────────────────────────────────────

const bodyRef = ref<HTMLTextAreaElement | null>(null)
const variables = ['{{member_name}}', '{{church_name}}', '{{department_name}}']

function insertVariable(v: string) {
    const ta = bodyRef.value
    if (!ta) { form.body += v; return }
    const start = ta.selectionStart ?? form.body.length
    const end   = ta.selectionEnd   ?? form.body.length
    form.body = form.body.slice(0, start) + v + form.body.slice(end)
    // Restore cursor after the inserted variable
    setTimeout(() => {
        ta.focus()
        ta.setSelectionRange(start + v.length, start + v.length)
    }, 0)
}

// ── Preview ────────────────────────────────────────────────────────────────────

const preview     = ref('')
const showPreview = ref(false)

async function loadPreview() {
    try {
        const resp = await window.axios.post('/dashboard/communication/broadcasts/preview-anonymous', {
            body: form.body,
            audience_type: form.audience_type,
        })
        // Fallback: just show the raw body if endpoint not yet available
        preview.value = resp.data?.preview ?? form.body
    } catch {
        preview.value = form.body
    }
    showPreview.value = true
}

// ── Submit ─────────────────────────────────────────────────────────────────────

function submit(action: 'draft' | 'send' | 'schedule') {
    form.action = action
    form.post('/dashboard/communication/broadcasts')
}

const submitLabel = computed(() => {
    if (recipientCount.value !== null && recipientCount.value > 0) {
        return `Send to ${recipientCount.value} recipient${recipientCount.value !== 1 ? 's' : ''}`
    }
    return 'Send'
})
</script>

<template>
    <DashboardLayout title="New Broadcast">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 space-y-4">

            <!-- Top bar -->
            <div class="flex items-center gap-3">
                <a href="/dashboard/communication/broadcasts" class="p-2 text-neutral-400 hover:text-neutral-700 rounded-lg hover:bg-neutral-100 transition">
                    <ArrowLeft class="w-5 h-5" />
                </a>
                <h1 class="text-xl font-bold text-neutral-900 flex-1">New Broadcast</h1>
                <div class="flex items-center gap-2">
                    <AppButton type="button" variant="outline" size="sm" @click="loadPreview">
                        <Eye class="w-4 h-4" /> Preview
                    </AppButton>
                </div>
            </div>

            <!-- Split panel -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                <!-- LEFT: Audience picker -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5 space-y-5">
                    <h2 class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
                        <Users class="w-4 h-4" /> Audience
                    </h2>

                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Broadcast Title</label>
                        <input
                            v-model="form.title"
                            type="text"
                            placeholder="e.g. Sunday Service Reminder"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        />
                        <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                    </div>

                    <!-- Audience type cards -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-2">Send To</label>
                        <div class="space-y-2">
                            <button
                                v-for="opt in audienceOptions"
                                :key="opt.value"
                                type="button"
                                @click="form.audience_type = opt.value; form.audience_config = null"
                                :class="[
                                    'w-full text-left px-3 py-2.5 rounded-lg border transition text-sm',
                                    form.audience_type === opt.value
                                        ? 'border-brand-500 bg-brand-50 text-brand-800'
                                        : 'border-neutral-200 hover:border-neutral-300 text-neutral-700'
                                ]"
                            >
                                <span class="font-medium">{{ opt.label }}</span>
                                <span class="block text-xs text-neutral-500 mt-0.5">{{ opt.desc }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Sub-picker: department -->
                    <div v-if="form.audience_type === 'department'">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Select Department</label>
                        <select
                            @change="form.audience_config = { department_id: parseInt(($event.target as HTMLSelectElement).value) }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">— choose —</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <!-- Sub-picker: role -->
                    <div v-if="form.audience_type === 'role'">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Select Role</label>
                        <select
                            @change="form.audience_config = { role: ($event.target as HTMLSelectElement).value }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">— choose —</option>
                            <option value="church_admin">Church Admin</option>
                            <option value="coordinator">Coordinator</option>
                            <option value="assistant_coordinator">Assistant Coordinator</option>
                            <option value="member">Member</option>
                        </select>
                    </div>

                    <!-- Sub-picker: saved audience -->
                    <div v-if="form.audience_type === 'saved_audience'">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Select Saved Audience</label>
                        <select
                            @change="form.audience_config = { audience_id: parseInt(($event.target as HTMLSelectElement).value) }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">— choose —</option>
                            <option v-for="a in audiences" :key="a.id" :value="a.id">{{ a.name }} ({{ a.member_count }})</option>
                        </select>
                    </div>

                    <!-- Live count badge -->
                    <div class="flex items-center gap-2 pt-1">
                        <div class="h-px flex-1 bg-neutral-100"></div>
                        <span v-if="countLoading" class="text-xs text-neutral-400">Counting…</span>
                        <AppBadge v-else-if="recipientCount !== null" color="brand">
                            {{ recipientCount }} recipient{{ recipientCount !== 1 ? 's' : '' }}
                        </AppBadge>
                        <span v-else class="text-xs text-neutral-400">—</span>
                        <div class="h-px flex-1 bg-neutral-100"></div>
                    </div>
                </div>

                <!-- RIGHT: Message composer -->
                <div class="bg-white border border-neutral-200 rounded-xl p-5 space-y-4">
                    <h2 class="text-sm font-semibold text-neutral-700 flex items-center gap-2">
                        <Send class="w-4 h-4" /> Message
                    </h2>

                    <!-- Template picker -->
                    <div v-if="templates.length > 0">
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Use Template (optional)</label>
                        <select
                            @change="(e) => { const id = parseInt((e.target as HTMLSelectElement).value); const t = templates.find(x => x.id === id); if (t) applyTemplate(t); }"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        >
                            <option value="">— select a template —</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>

                    <!-- Subject -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-1">Subject</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            placeholder="Notification subject line"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        />
                        <p v-if="form.errors.subject" class="text-xs text-red-500 mt-1">{{ form.errors.subject }}</p>
                    </div>

                    <!-- Body + variable chips -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="text-xs font-medium text-neutral-600">Message</label>
                            <div class="flex gap-1">
                                <button
                                    v-for="v in variables"
                                    :key="v"
                                    type="button"
                                    @click="insertVariable(v)"
                                    class="text-xs px-2 py-0.5 rounded-full bg-brand-50 text-brand-700 hover:bg-brand-100 transition font-mono"
                                >
                                    {{ v }}
                                </button>
                            </div>
                        </div>
                        <textarea
                            ref="bodyRef"
                            v-model="form.body"
                            rows="8"
                            placeholder="Write your message here. Use the variable chips above to personalise."
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400 resize-none"
                        />
                        <p v-if="form.errors.body" class="text-xs text-red-500 mt-1">{{ form.errors.body }}</p>
                    </div>

                    <!-- Schedule field (optional) -->
                    <div>
                        <label class="block text-xs font-medium text-neutral-600 mb-1">
                            <Calendar class="w-3.5 h-3.5 inline mr-1" />Schedule For (optional)
                        </label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            class="w-full border border-neutral-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                        />
                    </div>

                    <!-- Action buttons -->
                    <div class="flex gap-2 pt-2">
                        <AppButton type="button" variant="outline" class="flex-1" :loading="form.processing" @click="submit('draft')">
                            <Save class="w-4 h-4" /> Save Draft
                        </AppButton>
                        <AppButton
                            v-if="form.scheduled_at"
                            type="button"
                            class="flex-1 !bg-amber-500 hover:!bg-amber-600"
                            :disabled="form.processing || recipientCount === 0"
                            @click="submit('schedule')"
                        >
                            <Calendar class="w-4 h-4" /> Schedule
                        </AppButton>
                        <AppButton
                            v-else
                            type="button"
                            class="flex-1"
                            :disabled="form.processing || recipientCount === 0"
                            @click="submit('send')"
                        >
                            <Send class="w-4 h-4" /> {{ submitLabel }}
                        </AppButton>
                    </div>

                    <p v-if="form.errors.action" class="text-xs text-red-500">{{ form.errors.action }}</p>
                </div>
            </div>
        </div>

        <!-- Preview modal -->
        <AppModal :open="showPreview" title="Message Preview" @close="showPreview = false">
            <div class="space-y-4">
                <div class="bg-neutral-50 rounded-lg p-4 text-sm text-neutral-700 whitespace-pre-wrap">{{ preview }}</div>
                <p class="text-xs text-neutral-400">Variables shown as-is — they are replaced per recipient when delivered.</p>
            </div>
            <template #footer>
                <AppButton class="w-full" @click="showPreview = false">Close</AppButton>
            </template>
        </AppModal>
    </DashboardLayout>
</template>
