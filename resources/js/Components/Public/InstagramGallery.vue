<script setup lang="ts">
/**
 * A grid of the church's recent Instagram posts.
 *
 * Square tiles, cropped to fill (unlike event flyers, Instagram posts are
 * framed for a square grid already). Images lazy-load straight from Meta's CDN
 * and the square aspect is reserved up front, so nothing shifts as they load.
 * A carousel is one tile with a small "multiple" mark; a video or Reel shows its
 * cover with a play mark and never plays in the grid.
 *
 * Meta's CDN links are temporary. The server refreshes them well before they
 * expire, but if one has died anyway the tile simply removes itself rather
 * than showing a broken image.
 */
import { computed, nextTick, ref } from 'vue'
import { Clapperboard, Layers, Play } from 'lucide-vue-next'
import InstagramLightbox from '@/Components/Public/InstagramLightbox.vue'
import type { InstagramPost } from '@/types'

const props = withDefaults(defineProps<{
    posts:   InstagramPost[]
    /** `grid` for the gallery page (3 → 4 columns); `strip` for the homepage (3 → 6). */
    variant?: 'grid' | 'strip'
    /**
     * Show at most this many. Pass a few spare posts with it: a tile whose
     * image fails is removed, and the next post moves up to fill the row
     * instead of leaving it one short.
     */
    limit?: number
}>(), { variant: 'grid' })

const broken  = ref(new Set<string>())
const visible = computed(() => {
    const ok = props.posts.filter(p => !broken.value.has(p.id))
    return props.limit ? ok.slice(0, props.limit) : ok
})

const open    = ref<InstagramPost | null>(null)
let   opener: HTMLElement | null = null

function show(post: InstagramPost, e: MouseEvent) {
    opener = e.currentTarget as HTMLElement
    open.value = post
}

async function close() {
    open.value = null
    await nextTick()
    opener?.focus()   // back to the tile that opened it
}

function hide(id: string) {
    broken.value = new Set(broken.value).add(id)
}

function date(post: InstagramPost): string {
    return post.timestamp
        ? new Date(post.timestamp).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
        : ''
}

/** Alt text: the caption's opening, or what and when it is. Never invented. */
function alt(post: InstagramPost): string {
    const kind = post.type === 'carousel' ? 'Instagram carousel' : post.is_reel ? 'Instagram reel' : post.type === 'video' ? 'Instagram video' : 'Instagram photo'
    if (post.caption) {
        const text = post.caption.replace(/\s+/g, ' ').trim()
        return `${kind}: ${text.length > 120 ? text.slice(0, 117) + '…' : text}`
    }
    return date(post) ? `${kind} from ${date(post)}` : kind
}
</script>

<template>
    <div v-if="visible.length">
        <ul
            class="grid gap-1.5 sm:gap-2"
            :class="variant === 'strip' ? 'grid-cols-3 md:grid-cols-6' : 'grid-cols-3 md:grid-cols-4'"
        >
            <li v-for="post in visible" :key="post.id">
                <button
                    type="button"
                    class="group relative block aspect-square w-full overflow-hidden rounded-lg bg-neutral-100 sm:rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
                    @click="show(post, $event)"
                >
                    <img
                        :src="post.thumb"
                        :alt="alt(post)"
                        width="640"
                        height="640"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.04]"
                        @error="hide(post.id)"
                    />
                    <span class="pointer-events-none absolute inset-0 bg-black/0 transition-colors duration-300 group-hover:bg-black/15" aria-hidden="true"></span>

                    <!-- Type marks: small, white, in the corner — and spoken for screen readers. -->
                    <span v-if="post.type === 'carousel'" class="absolute right-1.5 top-1.5 sm:right-2 sm:top-2">
                        <Layers class="h-4 w-4 text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]" aria-hidden="true" />
                        <span class="sr-only">, {{ post.count }} items</span>
                    </span>
                    <span v-else-if="post.type === 'video'" class="absolute right-1.5 top-1.5 sm:right-2 sm:top-2">
                        <Clapperboard v-if="post.is_reel" class="h-4 w-4 text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]" aria-hidden="true" />
                        <Play v-else class="h-4 w-4 fill-white text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.6)]" aria-hidden="true" />
                    </span>
                </button>
            </li>
        </ul>

        <InstagramLightbox :post="open" @close="close" />
    </div>
</template>
