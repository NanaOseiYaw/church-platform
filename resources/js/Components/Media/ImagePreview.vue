<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import { X, Download } from 'lucide-vue-next'

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = defineProps<{
    src: string
    alt: string
    downloadUrl?: string
}>()

const emit = defineEmits<{
    close: []
}>()

// ── Keyboard: Escape closes the preview ────────────────────────────────────────

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close')
}

onMounted(() => {
    document.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'
})

onUnmounted(() => {
    document.removeEventListener('keydown', onKey)
    document.body.style.overflow = ''
})
</script>

<template>
    <!-- Backdrop -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4"
        @click.self="emit('close')"
    >
        <!-- Controls row -->
        <div class="absolute top-4 right-4 flex items-center gap-2 z-10">
            <a
                v-if="downloadUrl"
                :href="downloadUrl"
                download
                class="p-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                title="Download"
            >
                <Download class="w-5 h-5" />
            </a>
            <button
                type="button"
                class="p-2 rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
                @click="emit('close')"
            >
                <X class="w-5 h-5" />
            </button>
        </div>

        <!-- Image -->
        <img
            :src="src"
            :alt="alt"
            class="max-w-full max-h-[90vh] rounded-lg object-contain shadow-2xl"
        />
    </div>
</template>
