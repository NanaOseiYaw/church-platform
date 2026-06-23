<script setup lang="ts">
import { useNotificationStore } from '@/stores/useNotificationStore'
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next'

const store = useNotificationStore()

const iconMap = {
    success: CheckCircle2,
    error:   XCircle,
    warning: AlertTriangle,
    info:    Info,
}

const colorMap = {
    success: 'border-l-emerald-500',
    error:   'border-l-rose-500',
    warning: 'border-l-amber-500',
    info:    'border-l-blue-500',
}

const iconColorMap = {
    success: 'text-emerald-500',
    error:   'text-rose-500',
    warning: 'text-amber-500',
    info:    'text-blue-500',
}
</script>

<template>
    <Teleport to="body">
        <div class="fixed bottom-5 right-5 z-[100] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-3">
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-2 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-for="toast in store.toasts"
                    :key="toast.id"
                    :class="[
                        'pointer-events-auto bg-white border border-neutral-100 border-l-4 rounded-xl shadow-xl px-4 py-3.5 flex items-start gap-3',
                        colorMap[toast.type]
                    ]"
                >
                    <component :is="iconMap[toast.type]" :class="['w-4 h-4 shrink-0 mt-0.5', iconColorMap[toast.type]]" />
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-neutral-900">{{ toast.title }}</p>
                        <p v-if="toast.message" class="text-xs text-neutral-500 mt-0.5">{{ toast.message }}</p>
                    </div>
                    <button class="text-neutral-400 hover:text-neutral-600 transition-colors shrink-0" @click="store.dismiss(toast.id)">
                        <X class="w-3.5 h-3.5" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
