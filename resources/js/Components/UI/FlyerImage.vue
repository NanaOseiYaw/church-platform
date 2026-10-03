<script setup lang="ts">
/**
 * An image shown whole, never cropped — for photos and flyers alike.
 *
 * A flyer carries its own text (date, time, venue), so the usual object-cover
 * crop would cut that off. Instead the image is contained in the frame and the
 * empty space is filled with a blurred, darkened copy of itself, which reads as
 * deliberate framing rather than letterboxing whatever its shape.
 *
 * The parent sets the frame's size with a class (an aspect ratio or a height).
 * Pass `href` to make it open the full-size image — useful for a flyer whose
 * small print is meant to be read.
 *
 * Pass `adaptRatio` ([narrowest, widest] width/height) where the frame has a
 * fixed width but its height is free: once the image loads, the frame takes
 * the image's own shape within those limits. Without it, a fixed portrait
 * frame shrank a landscape photo to under half its area (and vice versa).
 * The parent's aspect class still applies until the image has loaded.
 */
import { ref } from 'vue'
import { Maximize2 } from 'lucide-vue-next'

const props = defineProps<{
    src: string
    alt: string
    href?: string
    adaptRatio?: [number, number]
}>()

const ratio = ref<number | null>(null)

function onLoad(e: Event) {
    if (!props.adaptRatio) return
    const img = e.target as HTMLImageElement
    if (!img.naturalWidth || !img.naturalHeight) return
    const [narrowest, widest] = props.adaptRatio
    ratio.value = Math.min(widest, Math.max(narrowest, img.naturalWidth / img.naturalHeight))
}
</script>

<template>
    <component
        :is="href ? 'a' : 'div'"
        :href="href"
        :target="href ? '_blank' : undefined"
        :rel="href ? 'noopener' : undefined"
        class="relative block overflow-hidden bg-neutral-900"
        :style="ratio ? { aspectRatio: String(ratio) } : undefined"
    >
        <!-- Blurred fill. Decorative, so hidden from assistive technology. -->
        <img
            :src="src"
            alt=""
            aria-hidden="true"
            class="absolute inset-0 w-full h-full object-cover scale-110 blur-2xl opacity-60"
        />
        <div class="absolute inset-0 bg-black/25" aria-hidden="true"></div>

        <img
            :src="src"
            :alt="alt"
            class="relative w-full h-full object-contain"
            loading="lazy"
            decoding="async"
            @load="onLoad"
        />

        <!--
            An icon rather than a text label: in a small thumbnail a "View full
            size" label covered the bottom of the flyer — the part this component
            exists to keep visible. The words stay for screen readers.
        -->
        <span
            v-if="href"
            class="absolute bottom-2 right-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-black/55 text-white/90 backdrop-blur-sm"
        >
            <Maximize2 class="h-3.5 w-3.5" aria-hidden="true" />
            <span class="sr-only">View full size</span>
        </span>
    </component>
</template>
