<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Check, Minus, X, Loader2 } from 'lucide-vue-next'
import type { RsvpStatus } from '@/types'

const props = defineProps<{
    eventId:  number
    current:  RsvpStatus | null    // current user's RSVP, null = not RSVPed
    canRsvp:  boolean
    /** Compact mode: icon-only buttons */
    compact?: boolean
}>()

const loading = ref<RsvpStatus | null>(null)

const options: { value: RsvpStatus; label: string; icon: typeof Check; active: string; inactive: string }[] = [
    {
        value:    'going',
        label:    'Going',
        icon:     Check,
        active:   'bg-emerald-500 text-white border-emerald-500',
        inactive: 'bg-white text-neutral-600 border-neutral-200 hover:border-emerald-300 hover:text-emerald-600',
    },
    {
        value:    'maybe',
        label:    'Maybe',
        icon:     Minus,
        active:   'bg-amber-400 text-white border-amber-400',
        inactive: 'bg-white text-neutral-600 border-neutral-200 hover:border-amber-300 hover:text-amber-600',
    },
    {
        value:    'not_going',
        label:    'Not going',
        icon:     X,
        active:   'bg-rose-500 text-white border-rose-500',
        inactive: 'bg-white text-neutral-600 border-neutral-200 hover:border-rose-300 hover:text-rose-500',
    },
]

function submit(status: RsvpStatus) {
    if (! props.canRsvp || loading.value) return

    loading.value = status

    // Clicking the active status cancels via toggle (backend handles this)
    router.post(`/dashboard/events/${props.eventId}/rsvp`, { status }, {
        preserveScroll: true,
        onFinish:       () => { loading.value = null },
    })
}
</script>

<template>
    <div v-if="canRsvp" :class="['flex items-center gap-2', compact ? 'flex-row' : 'flex-wrap']">
        <button
            v-for="opt in options"
            :key="opt.value"
            type="button"
            :disabled="loading !== null"
            :class="[
                'inline-flex items-center gap-1.5 border rounded-lg transition-all font-medium disabled:opacity-60',
                compact ? 'px-2.5 py-1.5 text-xs' : 'px-3 py-2 text-sm',
                current === opt.value ? opt.active : opt.inactive,
            ]"
            @click="submit(opt.value)"
        >
            <Loader2 v-if="loading === opt.value" class="w-3.5 h-3.5 animate-spin" />
            <component :is="opt.icon" v-else class="w-3.5 h-3.5" />
            <span v-if="!compact">{{ opt.label }}</span>
        </button>
    </div>
</template>
