<script setup lang="ts">
import type { SearchResultItem } from '@/types'
import {
    User, Building2, Megaphone, CalendarDays, CheckSquare,
    File, FileText, ImageIcon, Music, Video, CalendarCheck2,
    ArrowUpRight,
} from 'lucide-vue-next'
import type { Component } from 'vue'

const props = defineProps<{
    result:   SearchResultItem
    selected: boolean
}>()

defineEmits<{
    click: []
    mouseenter: []
}>()

// ── Icon map ──────────────────────────────────────────────────────────────────
const iconMap: Record<string, Component> = {
    'user':           User,
    'building':       Building2,
    'megaphone':      Megaphone,
    'calendar':       CalendarDays,
    'check-square':   CheckSquare,
    'file':           File,
    'file-text':      FileText,
    'image':          ImageIcon,
    'music':          Music,
    'video':          Video,
    'calendar-check': CalendarCheck2,
}

const resolvedIcon = (key: string): Component => iconMap[key] ?? File

// ── Badge color map → Tailwind utility classes ────────────────────────────────
const badgeClasses: Record<string, string> = {
    brand:   'bg-brand-50 text-brand-700',
    violet:  'bg-brand-50 text-brand-700',
    blue:    'bg-blue-50 text-blue-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber:   'bg-amber-50 text-amber-700',
    orange:  'bg-orange-50 text-orange-700',
    rose:    'bg-rose-50 text-rose-700',
    neutral: 'bg-neutral-100 text-neutral-600',
}

const badgeClass = (color: string | null): string =>
    badgeClasses[color ?? 'neutral'] ?? badgeClasses.neutral

// ── Type background for icon chip ─────────────────────────────────────────────
const typeColors: Record<string, string> = {
    member:       'bg-brand-50 text-brand-600',
    department:   'bg-emerald-50 text-emerald-600',
    announcement: 'bg-brand-50 text-brand-600',
    event:        'bg-amber-50 text-amber-600',
    task:         'bg-orange-50 text-orange-600',
    media:        'bg-sky-50 text-sky-600',
    attendance:   'bg-teal-50 text-teal-600',
}

const iconClass = (type: string): string => typeColors[type] ?? 'bg-neutral-100 text-neutral-500'
</script>

<template>
    <button
        type="button"
        :class="[
            'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left transition-all duration-75 group',
            selected
                ? 'bg-neutral-100 ring-1 ring-neutral-200'
                : 'hover:bg-neutral-50',
        ]"
        @click="$emit('click')"
        @mouseenter="$emit('mouseenter')"
    >
        <!-- Type icon chip -->
        <div :class="['w-8 h-8 rounded-lg flex items-center justify-center shrink-0', iconClass(result.type)]">
            <component :is="resolvedIcon(result.icon)" class="w-3.5 h-3.5" />
        </div>

        <!-- Text content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-sm font-medium text-neutral-900 truncate">{{ result.title }}</span>
                <span
                    v-if="result.badge"
                    :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded-md shrink-0', badgeClass(result.badge_color)]"
                >{{ result.badge }}</span>
            </div>
            <p v-if="result.subtitle" class="text-xs text-neutral-400 truncate mt-0.5">
                {{ result.subtitle }}
            </p>
        </div>

        <!-- Meta + arrow -->
        <div class="flex items-center gap-2 shrink-0">
            <span v-if="result.meta" class="text-[11px] text-neutral-400 tabular-nums">
                {{ result.meta }}
            </span>
            <ArrowUpRight
                :class="[
                    'w-3 h-3 transition-opacity duration-75',
                    selected ? 'opacity-40' : 'opacity-0 group-hover:opacity-30',
                ]"
            />
        </div>
    </button>
</template>
