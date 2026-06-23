<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { X } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
    open: boolean
    title?: string
    size?: 'sm' | 'md' | 'lg' | 'xl'
    closeable?: boolean
}>(), { size: 'md', closeable: true })

const emit = defineEmits<{ close: [] }>()

const widths = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape' && props.closeable) emit('close')
}

onMounted(()  => document.addEventListener('keydown', onKey))
onUnmounted(() => document.removeEventListener('keydown', onKey))
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div
                    class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                    @click="closeable && emit('close')"
                />

                <!-- Panel -->
                <Transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                >
                    <div
                        v-if="open"
                        :class="['relative w-full bg-white rounded-2xl shadow-2xl flex flex-col max-h-[90vh]', widths[size]]"
                        role="dialog"
                        :aria-label="title"
                    >
                        <!-- Header -->
                        <div v-if="title || closeable" class="flex items-center justify-between px-6 pt-5 pb-4 border-b border-neutral-100 shrink-0">
                            <h2 v-if="title" class="text-base font-semibold text-neutral-900">{{ title }}</h2>
                            <button
                                v-if="closeable"
                                class="ml-auto p-1.5 rounded-lg text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 transition-colors"
                                @click="emit('close')"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="flex-1 overflow-y-auto px-6 py-5">
                            <slot />
                        </div>

                        <!-- Footer -->
                        <div v-if="$slots.footer" class="px-6 pb-5 pt-4 border-t border-neutral-100 shrink-0">
                            <slot name="footer" />
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
