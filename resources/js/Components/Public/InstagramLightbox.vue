<script setup lang="ts">
/**
 * On-site viewer for one Instagram post — a photo, a video, or every item in
 * a carousel.
 *
 * Only the item on screen is rendered, so a carousel's other images are not
 * downloaded until someone moves to them; once the current image has loaded,
 * the next one (if it is an image) is fetched ahead to make "next" instant.
 * Videos never autoplay and use preload="none", so nothing is downloaded until
 * play is pressed. All media comes straight from Meta's CDN; nothing is stored.
 *
 * Accessibility: a labelled modal dialog. Focus moves into it on open, Tab is
 * kept inside it, Escape closes it, ←/→ move through a carousel, and focus
 * returns to the tile that opened it (handled by the parent).
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, ExternalLink, Play, X } from 'lucide-vue-next'
import AppButton from '@/Components/UI/AppButton.vue'
import type { InstagramPost } from '@/types'

const props = defineProps<{ post: InstagramPost | null }>()
const emit  = defineEmits<{ close: [] }>()

const index   = ref(0)
const dialog  = ref<HTMLElement | null>(null)
const closeBtn = ref<HTMLButtonElement | null>(null)

const item  = computed(() => props.post?.media[index.value] ?? null)
const count = computed(() => props.post?.media.length ?? 0)

const label = computed(() => {
    if (!props.post) return 'Instagram post'
    const kind = props.post.type === 'carousel' ? 'Instagram carousel' : props.post.is_reel ? 'Instagram reel' : 'Instagram post'
    return props.post.caption ? `${kind}: ${props.post.caption.slice(0, 80)}` : kind
})

const date = computed(() => {
    if (!props.post?.timestamp) return null
    return new Date(props.post.timestamp).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
})

const itemAlt = computed(() => {
    const caption = props.post?.caption?.slice(0, 120)
    const position = count.value > 1 ? ` (${index.value + 1} of ${count.value})` : ''
    return (caption ? caption : `Instagram post${date.value ? ` from ${date.value}` : ''}`) + position
})

function go(step: number) {
    const next = index.value + step
    if (next >= 0 && next < count.value) index.value = next
}

/** Fetch the next image ahead of time — only after the current one has loaded. */
function prefetchNext() {
    const next = props.post?.media[index.value + 1]
    if (next?.type === 'image' && next.src) {
        const img = new Image()
        img.decoding = 'async'
        img.src = next.src
    }
}

function close() {
    emit('close')
}

// Keyboard: Escape, arrows, and keeping Tab inside the dialog.
function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') { e.preventDefault(); close(); return }
    if (e.key === 'ArrowRight') { e.preventDefault(); go(1); return }
    if (e.key === 'ArrowLeft')  { e.preventDefault(); go(-1); return }

    if (e.key === 'Tab' && dialog.value) {
        const focusable = Array.from(dialog.value.querySelectorAll<HTMLElement>(
            'a[href], button:not([disabled]), video[controls], [tabindex]:not([tabindex="-1"])',
        )).filter(el => el.offsetParent !== null)
        if (focusable.length === 0) return
        const first = focusable[0]
        const last  = focusable[focusable.length - 1]
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
    }
}

// Swipe on touch screens.
let touchX: number | null = null
function onTouchStart(e: TouchEvent) { touchX = e.touches[0]?.clientX ?? null }
function onTouchEnd(e: TouchEvent) {
    if (touchX === null) return
    const dx = (e.changedTouches[0]?.clientX ?? touchX) - touchX
    if (Math.abs(dx) > 45) go(dx < 0 ? 1 : -1)
    touchX = null
}

// Open/close side effects: reset position, lock page scroll, move focus in.
watch(() => props.post, async (post) => {
    index.value = 0
    document.documentElement.style.overflow = post ? 'hidden' : ''
    if (post) {
        await nextTick()
        closeBtn.value?.focus()
    }
})

onBeforeUnmount(() => { document.documentElement.style.overflow = '' })
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="post && item"
                ref="dialog"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-neutral-950/90 p-3 sm:p-6"
                role="dialog"
                aria-modal="true"
                :aria-label="label"
                @click.self="close"
                @keydown="onKeydown"
            >
                <div class="relative flex max-h-full w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-neutral-900 md:flex-row">

                    <!-- Media -->
                    <div
                        class="relative flex min-h-[40vh] flex-1 items-center justify-center bg-black"
                        @touchstart.passive="onTouchStart"
                        @touchend.passive="onTouchEnd"
                    >
                        <img
                            v-if="item.type === 'image' && item.src"
                            :key="item.src"
                            :src="item.src"
                            :alt="itemAlt"
                            class="max-h-[62vh] w-full object-contain md:max-h-[86vh]"
                            decoding="async"
                            @load="prefetchNext"
                        />

                        <!-- Never autoplays; nothing downloads until play is pressed. -->
                        <video
                            v-else-if="item.type === 'video' && item.src"
                            :key="item.src"
                            :src="item.src"
                            :poster="item.poster ?? undefined"
                            :aria-label="itemAlt"
                            class="max-h-[62vh] w-full md:max-h-[86vh]"
                            controls
                            playsinline
                            preload="none"
                        />

                        <!-- A video Meta will not serve (licensed music): poster + link out. -->
                        <div v-else class="relative w-full">
                            <img
                                v-if="item.poster"
                                :src="item.poster"
                                :alt="itemAlt"
                                class="max-h-[62vh] w-full object-contain opacity-60 md:max-h-[86vh]"
                                decoding="async"
                            />
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3 p-6 text-center">
                                <span class="flex h-14 w-14 items-center justify-center rounded-full bg-white/15 backdrop-blur-sm">
                                    <Play class="h-6 w-6 text-white" aria-hidden="true" />
                                </span>
                                <p class="max-w-xs text-sm text-white/80">This video plays on Instagram.</p>
                                <AppButton :href="post.permalink" external variant="primary" size="sm">
                                    Watch on Instagram <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                                </AppButton>
                            </div>
                        </div>

                        <!-- Carousel controls -->
                        <template v-if="count > 1">
                            <button
                                type="button"
                                class="absolute left-2 top-1/2 -translate-y-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-sm transition hover:bg-black/70 disabled:opacity-30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                                :disabled="index === 0"
                                aria-label="Previous item"
                                @click="go(-1)"
                            >
                                <ChevronLeft class="h-5 w-5" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/50 text-white backdrop-blur-sm transition hover:bg-black/70 disabled:opacity-30 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                                :disabled="index === count - 1"
                                aria-label="Next item"
                                @click="go(1)"
                            >
                                <ChevronRight class="h-5 w-5" aria-hidden="true" />
                            </button>
                            <p class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/55 px-3 py-1 text-xs font-medium tabular-nums text-white" aria-live="polite">
                                {{ index + 1 }} / {{ count }}
                            </p>
                        </template>
                    </div>

                    <!-- Caption and link -->
                    <div class="flex max-h-[30vh] w-full flex-col gap-4 overflow-y-auto p-5 text-white md:max-h-none md:w-80 md:shrink-0">
                        <p v-if="date" class="text-xs text-white/50">{{ date }}</p>
                        <!-- Rendered as text, never HTML. -->
                        <p v-if="post.caption" class="whitespace-pre-line text-sm leading-relaxed text-white/85">{{ post.caption }}</p>
                        <div class="mt-auto pt-2">
                            <AppButton :href="post.permalink" external variant="outline" size="sm">
                                View on Instagram <ExternalLink class="h-3.5 w-3.5" aria-hidden="true" />
                            </AppButton>
                        </div>
                    </div>

                    <button
                        ref="closeBtn"
                        type="button"
                        class="absolute right-3 top-3 z-10 inline-flex h-9 w-9 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm transition hover:bg-black/75 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white"
                        aria-label="Close"
                        @click="close"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
