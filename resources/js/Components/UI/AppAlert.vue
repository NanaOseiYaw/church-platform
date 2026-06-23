<script setup lang="ts">
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next'

withDefaults(defineProps<{
    type?: 'success' | 'error' | 'warning' | 'info'
    title?: string
    dismissible?: boolean
}>(), { type: 'info', dismissible: false })

const emit = defineEmits<{ dismiss: [] }>()

const styles = {
    success: { wrap: 'bg-emerald-50 border-emerald-200 text-emerald-800', icon: CheckCircle2, iconClass: 'text-emerald-500' },
    error:   { wrap: 'bg-rose-50 border-rose-200 text-rose-800',          icon: XCircle,      iconClass: 'text-rose-500' },
    warning: { wrap: 'bg-amber-50 border-amber-200 text-amber-800',       icon: AlertTriangle, iconClass: 'text-amber-500' },
    info:    { wrap: 'bg-blue-50 border-blue-200 text-blue-800',           icon: Info,         iconClass: 'text-blue-500' },
}
</script>

<template>
    <div :class="['flex items-start gap-3 border rounded-xl px-4 py-3.5 text-sm', styles[type].wrap]">
        <component :is="styles[type].icon" :class="['w-4 h-4 shrink-0 mt-0.5', styles[type].iconClass]" />
        <div class="flex-1 min-w-0">
            <p v-if="title" class="font-semibold mb-0.5">{{ title }}</p>
            <div class="leading-relaxed"><slot /></div>
        </div>
        <button v-if="dismissible" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity" @click="emit('dismiss')">
            <X class="w-4 h-4" />
        </button>
    </div>
</template>
