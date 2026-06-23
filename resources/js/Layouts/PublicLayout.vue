<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppNav from '@/Components/Navigation/AppNav.vue'
import AppFooter from '@/Components/Navigation/AppFooter.vue'
import { useScrollReveal } from '@/composables/useScrollReveal'
import { useFlash } from '@/composables/useFlash'
import { useBrandColor } from '@/composables/useBrandColor'
import { useChurch } from '@/composables/useChurch'
import { Transition, ref, watch, computed } from 'vue'

withDefaults(defineProps<{
    title?: string
    description?: string
    ogImage?: string | null
}>(), {
    ogImage: null,
})

useScrollReveal()
useBrandColor()

const { church } = useChurch()

// Canonical URL — uses window.location so every SPA navigation gets the correct URL
const canonicalUrl = computed(() =>
    typeof window !== 'undefined' ? window.location.href : ''
)

const { flash } = useFlash()
const showToast = ref(false)

watch(() => flash.value.success, (val) => {
    if (val) {
        showToast.value = true
        setTimeout(() => { showToast.value = false }, 4000)
    }
})
</script>

<template>
    <Head>
        <title>{{ title ? `${title} · ${church.name}` : church.name }}</title>

        <!-- Primary meta -->
        <meta name="description" :content="description ?? church.seo?.meta_description ?? ''" />
        <meta v-if="church.seo?.robots" name="robots" :content="church.seo.robots" />

        <!-- Open Graph -->
        <meta property="og:type" content="website" />
        <meta property="og:title" :content="title ? `${title} · ${church.name}` : church.name" />
        <meta property="og:description" :content="description ?? church.seo?.meta_description ?? ''" />
        <meta v-if="ogImage ?? church.seo?.og_image ?? church.logo" property="og:image" :content="(ogImage ?? church.seo?.og_image ?? church.logo)!" />

        <!-- Site name + canonical URL -->
        <meta property="og:site_name" :content="church.seo?.meta_title ?? church.name" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />

        <!-- Twitter / X Card -->
        <meta name="twitter:card" :content="(ogImage ?? church.seo?.og_image ?? church.logo) ? 'summary_large_image' : 'summary'" />
        <meta name="twitter:title" :content="title ? `${title} · ${church.name}` : church.name" />
        <meta name="twitter:description" :content="description ?? church.seo?.meta_description ?? ''" />
        <meta v-if="ogImage ?? church.seo?.og_image ?? church.logo" name="twitter:image" :content="(ogImage ?? church.seo?.og_image ?? church.logo)!" />

        <!-- Favicon -->
        <link v-if="church.favicon" rel="icon" :href="church.favicon" />
    </Head>

    <div class="min-h-screen flex flex-col">
        <AppNav />

        <main class="flex-1 pt-16">
            <slot />
        </main>

        <AppFooter />
    </div>

    <!-- Flash toast -->
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showToast && flash.success"
            class="fixed bottom-6 right-6 z-50 bg-neutral-900 text-white text-sm px-5 py-3.5 rounded-xl shadow-xl flex items-center gap-3 max-w-sm"
        >
            <div class="w-2 h-2 bg-emerald-400 rounded-full shrink-0"></div>
            {{ flash.success }}
        </div>
    </Transition>
</template>
