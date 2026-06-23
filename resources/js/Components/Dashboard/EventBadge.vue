<script setup lang="ts">
import type { EventStatus, EventVisibility } from '@/types'
import { Globe, Building2, Users, MapPin } from 'lucide-vue-next'

const props = defineProps<{
    /** Pass status OR visibility — not both */
    status?:     EventStatus | string
    visibility?: EventVisibility | string
}>()

// ── Status config ──────────────────────────────────────────────────────────────

const statusConfig: Record<string, { label: string; classes: string; dot: string }> = {
    upcoming:  { label: 'Upcoming',  classes: 'bg-brand-50 text-brand-600',    dot: 'bg-brand-500'   },
    ongoing:   { label: 'Live now',  classes: 'bg-emerald-50 text-emerald-600', dot: 'bg-emerald-500 animate-pulse' },
    completed: { label: 'Ended',     classes: 'bg-neutral-100 text-neutral-500', dot: 'bg-neutral-400' },
    cancelled: { label: 'Cancelled', classes: 'bg-rose-50 text-rose-500',       dot: 'bg-rose-400'    },
}

// ── Visibility config ──────────────────────────────────────────────────────────

const visibilityConfig: Record<string, { label: string; classes: string }> = {
    public:          { label: 'Public',          classes: 'bg-emerald-50 text-emerald-600' },
    members_only:    { label: 'Members only',    classes: 'bg-blue-50 text-blue-600'       },
    department_only: { label: 'Department only', classes: 'bg-brand-50 text-brand-600'   },
}

function getStatus(s: string) {
    return statusConfig[s] ?? statusConfig.upcoming
}

function getVisibility(v: string) {
    return visibilityConfig[v] ?? visibilityConfig.public
}
</script>

<template>
    <!-- Status badge -->
    <span
        v-if="status"
        :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold', getStatus(status).classes]"
    >
        <span :class="['w-1.5 h-1.5 rounded-full', getStatus(status).dot]" />
        {{ getStatus(status).label }}
    </span>

    <!-- Visibility badge -->
    <span
        v-else-if="visibility"
        :class="['inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold', getVisibility(visibility).classes]"
    >
        <Globe v-if="visibility === 'public'"          class="w-2.5 h-2.5" />
        <Users v-else-if="visibility === 'members_only'" class="w-2.5 h-2.5" />
        <Building2 v-else                               class="w-2.5 h-2.5" />
        {{ getVisibility(visibility).label }}
    </span>
</template>
