<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useChurch } from '@/composables/useChurch'
import { useTenantStore } from '@/stores/useTenantStore'
import { MapPin, Phone, Mail, Facebook, Instagram, Youtube, Twitter, Linkedin } from 'lucide-vue-next'

const { church } = useChurch()
const tenant = useTenantStore()

const links = computed(() => ({
    'Explore': church.value.footerNav.explore_links,
    'Connect': church.value.footerNav.connect_links,
}))
</script>

<template>
    <footer class="bg-neutral-950 text-neutral-400">
        <div class="mx-auto max-w-7xl px-6 lg:px-8 pt-16 pb-8">
            <!-- Top grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10 mb-12">
                <!-- Brand column -->
                <div class="lg:col-span-2">
                    <div class="flex items-center gap-2.5 mb-4">
                        <img
                            v-if="church.logo"
                            :src="church.logo"
                            :alt="church.name"
                            class="w-8 h-8 rounded-lg object-contain shrink-0"
                        />
                        <div v-else class="w-8 h-8 rounded-lg gradient-brand flex items-center justify-center shrink-0">
                            <span class="text-white text-xs font-bold">{{ tenant.churchInitials }}</span>
                        </div>
                        <span class="font-semibold text-white text-sm">{{ church.name }}</span>
                    </div>
                    <p v-if="church.tagline" class="text-sm leading-relaxed text-neutral-500 mb-6 max-w-xs">
                        {{ church.tagline }}.
                    </p>
                    <p v-else-if="church.description" class="text-sm leading-relaxed text-neutral-500 mb-6 max-w-xs">
                        {{ church.description }}
                    </p>

                    <!-- Contact info -->
                    <div class="space-y-2 text-sm">
                        <div class="flex items-start gap-2.5">
                            <MapPin class="w-4 h-4 mt-0.5 text-neutral-600 shrink-0" />
                            <span>{{ church.address }}</span>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <Phone class="w-4 h-4 text-neutral-600 shrink-0" />
                            <a :href="`tel:${church.phone}`" class="hover:text-white transition-colors">{{ church.phone }}</a>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <Mail class="w-4 h-4 text-neutral-600 shrink-0" />
                            <a :href="`mailto:${church.email}`" class="hover:text-white transition-colors">{{ church.email }}</a>
                        </div>
                    </div>
                </div>

                <!-- Link columns -->
                <div v-for="(items, group) in links" :key="group">
                    <h3 class="text-xs font-semibold uppercase tracking-widest text-neutral-500 mb-4">{{ group }}</h3>
                    <ul class="space-y-2.5">
                        <li v-for="item in items" :key="item.href">
                            <Link :href="item.href" class="text-sm hover:text-white transition-colors">
                                {{ item.label }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="border-t border-neutral-800 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-xs text-neutral-600">
                    &copy; {{ new Date().getFullYear() }} {{ church.name }}. All rights reserved.
                </p>

                <!-- Socials -->
                <div class="flex items-center gap-3">
                    <a
                        v-if="church.socials.facebook"
                        :href="church.socials.facebook"
                        target="_blank" rel="noopener"
                        class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
                        aria-label="Facebook"
                    >
                        <Facebook class="w-3.5 h-3.5" />
                    </a>
                    <a
                        v-if="church.socials.instagram"
                        :href="church.socials.instagram"
                        target="_blank" rel="noopener"
                        class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
                        aria-label="Instagram"
                    >
                        <Instagram class="w-3.5 h-3.5" />
                    </a>
                    <a
                        v-if="church.socials.youtube"
                        :href="church.socials.youtube"
                        target="_blank" rel="noopener"
                        class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
                        aria-label="YouTube"
                    >
                        <Youtube class="w-3.5 h-3.5" />
                    </a>
                    <a
                        v-if="church.socials.twitter"
                        :href="church.socials.twitter"
                        target="_blank" rel="noopener"
                        class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
                        aria-label="Twitter / X"
                    >
                        <Twitter class="w-3.5 h-3.5" />
                    </a>
                    <a
                        v-if="church.socials.tiktok"
                        :href="church.socials.tiktok"
                        target="_blank" rel="noopener"
                        class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
                        aria-label="TikTok"
                    >
                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                        </svg>
                    </a>
                    <a
                        v-if="church.socials.linkedin"
                        :href="church.socials.linkedin"
                        target="_blank" rel="noopener"
                        class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
                        aria-label="LinkedIn"
                    >
                        <Linkedin class="w-3.5 h-3.5" />
                    </a>
                    <a
                        v-if="church.socials.spotify"
                        :href="church.socials.spotify"
                        target="_blank" rel="noopener"
                        class="w-8 h-8 rounded-lg bg-neutral-900 flex items-center justify-center hover:bg-neutral-800 hover:text-white transition-all"
                        aria-label="Spotify"
                    >
                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>
</template>
