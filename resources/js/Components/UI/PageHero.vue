<script setup lang="ts">
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = withDefaults(defineProps<{
    eyebrow?: string
    title:    string
    subtitle?: string
    size?:    'sm' | 'md' | 'lg'
    /**
     * Background image for this hero.
     *   undefined → fall back to the site-wide image from Settings → Website
     *   a URL      → use it for this page only
     *   null       → force the gradient, ignoring the site-wide image
     */
    image?: string | null
}>(), {
    size: 'md',
})

const paddingMap = {
    sm: 'py-20 md:py-24',
    md: 'py-24 md:py-32',
    lg: 'py-28 md:py-36',
}

const page = usePage()

const currentPath = computed(() => page.url.split('?')[0].replace(/(.+)\/$/, '$1') || '/')

// Which configured page we are on, e.g. '/about/history' → 'history'.
const pageKey = computed<string | null>(() => {
    const paths = (page.props as any).pageHeroPaths ?? {}

    return paths[currentPath.value] ?? null
})

// This page's own image, if the admin set one for it.
const perPageImage = computed<string | null>(() => {
    const images = (page.props as any).pageHeroImages ?? {}

    return pageKey.value ? (images[pageKey.value] ?? null) : null
})

// Site-wide fallback, used by any page without its own image.
const siteWideImage = computed<string | null>(
    () => (page.props as any).pageHeroImage ?? null
)

// per-page → site-wide → gradient.
// An explicit `image` prop always wins, including an explicit null, which is how
// a page opts out of both configured images and forces the gradient.
const resolvedImage = computed<string | null>(() => {
    if (props.image !== undefined) return props.image

    return perPageImage.value ?? siteWideImage.value
})

</script>

<template>
    <section
        class="relative overflow-hidden"
        :class="resolvedImage ? 'bg-neutral-950' : 'gradient-dark-mesh'"
        :style="resolvedImage
            ? { backgroundImage: `url(${resolvedImage})`, backgroundSize: 'cover', backgroundPosition: 'center' }
            : {}"
    >
        <!--
            Scrim over the image. The heading is white on whatever the admin
            uploads, and that could be a bright photo, so a single flat tint is
            not enough to guarantee legibility. Two layers: an even wash to knock
            the whole image back, plus a left-weighted gradient over the side the
            text actually sits on. This keeps the title readable on a light upload
            without flattening a dark one into mud.
        -->
        <template v-if="resolvedImage">
            <div class="absolute inset-0 bg-neutral-950/55 pointer-events-none" aria-hidden="true"></div>
            <div
                class="absolute inset-0 bg-gradient-to-r from-neutral-950/85 via-neutral-950/55 to-neutral-950/25 pointer-events-none"
                aria-hidden="true"
            ></div>
        </template>

        <!-- Top edge line -->
        <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-brand-500/30 to-transparent" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-7xl px-6 lg:px-8" :class="paddingMap[size]">
            <!-- Eyebrow -->
            <div v-if="eyebrow" class="flex items-center gap-3 mb-6 reveal">
                <span class="text-xs font-semibold tracking-[0.2em] uppercase text-brand-400">{{ eyebrow }}</span>
            </div>

            <!-- Title slot or prop -->
            <h1 class="text-4xl sm:text-5xl md:text-6xl font-display text-white leading-tight max-w-3xl reveal reveal-delay-1">
                <slot name="title">{{ title }}</slot>
            </h1>

            <!-- Subtitle. Lifted over a photo — white/45 is legible on the flat
                 gradient but too faint against image detail, even behind the scrim. -->
            <p
                v-if="subtitle"
                class="mt-5 text-lg max-w-xl leading-relaxed reveal reveal-delay-2"
                :class="resolvedImage ? 'text-white/75' : 'text-white/45'"
            >
                {{ subtitle }}
            </p>

            <!-- Extra slot for CTAs / tags below -->
            <div v-if="$slots.actions" class="mt-8 reveal reveal-delay-3">
                <slot name="actions" />
            </div>
        </div>

        <!--
            Content that continues on the hero's dark ground, such as a featured
            item. It lives inside the section rather than in a dark block placed
            after it: the background glows are positioned relative to their own
            box, so two adjacent dark blocks each paint their own pattern and a
            visible step appears where one ends and the next begins.
        -->
        <div v-if="$slots.below" class="relative mx-auto max-w-7xl px-6 lg:px-8 pb-16">
            <slot name="below" />
        </div>

        <!--
            No fade into the section below, deliberately. A fade from near-black to
            white has to pass through flat mid-grey, which reads as a band of smoke
            across the bottom of the hero however long or eased it is. A longer,
            eased version was tried and was worse: it greyed out whatever content
            sat lowest in the hero. The clean edge is the transition.

            (The `fade` prop that used to control this never worked: Vue casts an
            absent optional boolean prop to `false`, not `undefined`, so the
            "automatic" branch was unreachable and no hero ever drew a fade.)
        -->
    </section>
</template>
