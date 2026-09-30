<script setup lang="ts">
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppSelect from '@/Components/UI/AppSelect.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Plus, Trash2, Link2, ImagePlus, Upload } from 'lucide-vue-next'

interface FooterNavLink {
    label: string
    href:  string
}

interface FooterNav {
    explore_links: FooterNavLink[]
    connect_links: FooterNavLink[]
}

interface WebsiteConfig {
    domain:   string | null
    timezone: string | null
    language: string | null
}

interface Settings {
    privacy_mode:    boolean
    page_hero_image: string | null
    footer_nav:      FooterNav
}

interface HeroPage {
    key:    string
    label:  string
    path:   string
    group?: string
    image:  string | null
}

// Named 'websiteConfig' (not 'church') to avoid overwriting the shared TenantStore prop.
const props = defineProps<{
    websiteConfig: WebsiteConfig
    settings:      Settings
    heroPages:     HeroPage[]
}>()

const defaultLinks: FooterNav = {
    explore_links: [
        { label: 'About Us',   href: '/about' },
        { label: 'Ministries', href: '/ministries' },
        { label: 'Events',     href: '/events' },
        { label: 'Sermons',    href: '/sermons' },
    ],
    connect_links: [
        { label: 'Announcements', href: '/announcements' },
        { label: 'Contact Us',    href: '/contact' },
        { label: 'Watch Live',    href: '/live' },
        { label: 'Give Online',   href: '/give' },
    ],
}

const form = useForm({
    domain:       props.websiteConfig.domain   ?? '',
    timezone:     props.websiteConfig.timezone ?? 'UTC',
    language:     props.websiteConfig.language ?? 'en',
    privacy_mode: props.settings.privacy_mode  ?? false,
    footer_nav: {
        explore_links: (props.settings.footer_nav?.explore_links ?? defaultLinks.explore_links).map(l => ({ ...l })) as FooterNavLink[],
        connect_links: (props.settings.footer_nav?.connect_links ?? defaultLinks.connect_links).map(l => ({ ...l })) as FooterNavLink[],
    },
})

function addLink(group: 'explore_links' | 'connect_links') {
    form.footer_nav[group].push({ label: '', href: '' })
}

function removeLink(group: 'explore_links' | 'connect_links', i: number) {
    form.footer_nav[group].splice(i, 1)
}

function submit() {
    form.put('/dashboard/settings/website')
}

const timezones = [
    { value: 'UTC',                 label: 'UTC' },
    { value: 'Africa/Accra',        label: 'Africa / Accra (GMT+0)' },
    { value: 'Africa/Lagos',        label: 'Africa / Lagos (GMT+1)' },
    { value: 'Africa/Nairobi',      label: 'Africa / Nairobi (GMT+3)' },
    { value: 'Europe/London',       label: 'Europe / London' },
    { value: 'Europe/Amsterdam',    label: 'Europe / Amsterdam' },
    { value: 'America/New_York',    label: 'America / New York' },
    { value: 'America/Los_Angeles', label: 'America / Los Angeles' },
    { value: 'Australia/Sydney',    label: 'Australia / Sydney' },
]
const languages = [
    { value: 'en', label: 'English' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
    { value: 'es', label: 'Spanish' },
    { value: 'pt', label: 'Portuguese' },
    { value: 'nl', label: 'Dutch' },
]

// ── Header images ────────────────────────────────────────────────────────────
// Uploads and clears save immediately rather than waiting for a Save of
// unrelated website settings. `page` identifies which page is being changed;
// omitting it targets the site-wide default that every page falls back to.
const heroFileRefs = ref<Record<string, HTMLInputElement | null>>({})
const heroForm     = useForm({ page_hero_image: null as File | null, page: null as string | null })
const uploadingKey = ref<string | null>(null)

function setHeroFileRef(key: string, el: any) {
    heroFileRefs.value[key] = el as HTMLInputElement | null
}

function triggerHeroUpload(key: string) {
    heroFileRefs.value[key]?.click()
}

function onHeroFileChange(e: Event, key: string | null) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return

    uploadingKey.value        = key ?? '__default__'
    heroForm.page_hero_image  = file
    heroForm.page             = key
    heroForm.post('/dashboard/settings/website/page-hero-image', {
        preserveScroll: true,
        onFinish: () => {
            heroForm.reset()
            uploadingKey.value = null
            const input = heroFileRefs.value[key ?? '__default__']
            if (input) input.value = ''
        },
    })
}

function removeHero(key: string | null) {
    router.delete('/dashboard/settings/website/page-hero-image', {
        data: key ? { page: key } : {},
        preserveScroll: true,
    })
}
</script>

<template>
    <SettingsLayout section="website">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Public Website</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Domain, locale, and visibility settings for your public church website.
            </p>
        </div>

        <!-- ── Header images ──────────────────────────────────────────────────
             Outside the main form: each upload saves immediately, not on Save. -->
        <div class="max-w-2xl mb-5">
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">

                <div class="px-5 py-4 flex items-start gap-2">
                    <ImagePlus class="w-4 h-4 text-neutral-400 shrink-0 mt-0.5" />
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Header images</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            The photo behind the title at the top of each page. A page uses its own image
                            if it has one, otherwise the default below, otherwise the brand gradient.
                            Recommended: landscape, at least 1920×1080&nbsp;px.
                        </p>
                    </div>
                </div>

                <!-- Default, used by every page without its own image -->
                <div class="p-5">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-28 shrink-0 rounded-lg overflow-hidden aspect-[16/9] relative bg-neutral-950 border border-neutral-200"
                            :class="!settings.page_hero_image && 'gradient-dark-mesh'"
                        >
                            <img
                                v-if="settings.page_hero_image"
                                :src="settings.page_hero_image"
                                class="w-full h-full object-cover"
                                alt=""
                            />
                            <div v-if="settings.page_hero_image" class="absolute inset-0 bg-neutral-950/55" aria-hidden="true"></div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-neutral-900">Default for all pages</p>
                            <p class="text-xs text-neutral-500 mt-0.5">
                                {{ settings.page_hero_image
                                    ? 'Used by any page without its own image.'
                                    : 'No image — pages without their own use the brand gradient.' }}
                            </p>

                            <input
                                :ref="(el) => setHeroFileRef('__default__', el)"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="sr-only"
                                @change="(e) => onHeroFileChange(e, null)"
                            />

                            <div class="flex flex-wrap items-center gap-2 mt-2.5">
                                <AppButton
                                    variant="outline" size="sm" type="button"
                                    :loading="uploadingKey === '__default__'"
                                    @click="triggerHeroUpload('__default__')"
                                >
                                    <Upload class="w-3.5 h-3.5 mr-1.5" />
                                    {{ settings.page_hero_image ? 'Replace' : 'Upload' }}
                                </AppButton>
                                <AppButton
                                    v-if="settings.page_hero_image"
                                    variant="ghost" size="sm" type="button"
                                    class="!text-rose-600 hover:!bg-rose-50"
                                    @click="removeHero(null)"
                                >
                                    <Trash2 class="w-3.5 h-3.5 mr-1.5" />
                                    Use gradient
                                </AppButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Per-page overrides -->
                <div class="px-5 py-3 bg-neutral-50/60">
                    <p class="text-xs font-semibold text-neutral-700">Individual pages</p>
                    <p class="text-xs text-neutral-500 mt-0.5">Give any page its own image to override the default.</p>
                </div>

                <div
                    v-for="p in heroPages"
                    :key="p.key"
                    class="px-5 py-3.5 flex items-center gap-4"
                >
                    <div
                        class="w-20 shrink-0 rounded-md overflow-hidden aspect-[16/9] relative bg-neutral-950 border border-neutral-200"
                        :class="!(p.image || settings.page_hero_image) && 'gradient-dark-mesh'"
                    >
                        <img
                            v-if="p.image || settings.page_hero_image"
                            :src="p.image || settings.page_hero_image || ''"
                            class="w-full h-full object-cover"
                            :class="!p.image && 'opacity-45'"
                            alt=""
                        />
                        <div v-if="p.image || settings.page_hero_image" class="absolute inset-0 bg-neutral-950/55" aria-hidden="true"></div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-neutral-900 truncate">
                            <span v-if="p.group" class="text-neutral-400">{{ p.group }} › </span>{{ p.label }}
                        </p>
                        <p class="text-xs mt-0.5" :class="p.image ? 'text-neutral-500' : 'text-neutral-400'">
                            {{ p.image
                                ? 'Own image'
                                : (settings.page_hero_image ? 'Using default' : 'Using gradient') }}
                            <span class="text-neutral-300"> · {{ p.path }}</span>
                        </p>
                    </div>

                    <input
                        :ref="(el) => setHeroFileRef(p.key, el)"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="sr-only"
                        @change="(e) => onHeroFileChange(e, p.key)"
                    />

                    <div class="flex items-center gap-1.5 shrink-0">
                        <AppButton
                            variant="outline" size="sm" type="button"
                            :loading="uploadingKey === p.key"
                            @click="triggerHeroUpload(p.key)"
                        >
                            <Upload class="w-3.5 h-3.5" />
                        </AppButton>
                        <AppButton
                            v-if="p.image"
                            variant="ghost" size="sm" type="button"
                            class="!text-rose-600 hover:!bg-rose-50"
                            :aria-label="`Remove image for ${p.label}`"
                            @click="removeHero(p.key)"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </AppButton>
                    </div>
                </div>

                <div class="px-5 py-3">
                    <p class="text-xs text-neutral-400">JPG, PNG or WebP · max 5 MB</p>
                    <p v-if="(heroForm.errors as any).page_hero_image" class="text-xs text-rose-500 mt-1">
                        {{ (heroForm.errors as any).page_hero_image }}
                    </p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Domain -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Custom domain</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Point your domain to this platform. DNS configuration is handled externally.
                    </p>
                </div>
                <div class="p-5">
                    <AppInput
                        label="Domain"
                        v-model="form.domain"
                        :error="form.errors.domain"
                        placeholder="www.yourchurch.com"
                    />
                    <p class="text-xs text-neutral-400 mt-2">Leave blank to use the platform default URL.</p>
                </div>
            </div>

            <!-- Locale -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Locale</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">Default timezone and language for public-facing pages.</p>
                </div>
                <div class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <AppSelect label="Timezone" v-model="form.timezone" :options="timezones" :error="form.errors.timezone" />
                    <AppSelect label="Language"  v-model="form.language"  :options="languages"  :error="form.errors.language" />
                </div>
            </div>

            <!-- Privacy -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Privacy</h3>
                </div>
                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-neutral-900">Privacy mode</p>
                        <p class="text-xs text-neutral-500 mt-0.5">Hide the public website from search engines and unauthenticated visitors.</p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="form.privacy_mode"
                        @click="form.privacy_mode = !form.privacy_mode"
                        :class="['relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2', form.privacy_mode ? 'bg-brand-500' : 'bg-neutral-200']"
                    >
                        <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200', form.privacy_mode ? 'translate-x-4' : 'translate-x-0']" />
                    </button>
                </div>
            </div>

            <!-- Footer Navigation -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center gap-2">
                    <Link2 class="w-4 h-4 text-neutral-400 shrink-0" />
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Footer navigation</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Links shown in the two footer columns. Changes appear on the public site immediately after saving.
                        </p>
                    </div>
                </div>

                <!-- Explore group -->
                <div class="divide-y divide-neutral-100">
                    <div class="px-5 py-3 flex items-center justify-between bg-neutral-50">
                        <span class="text-xs font-semibold uppercase tracking-widest text-neutral-500">Explore</span>
                        <AppButton
                            type="button" variant="outline" size="sm"
                            @click="addLink('explore_links')"
                            :disabled="form.footer_nav.explore_links.length >= 12"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            Add link
                        </AppButton>
                    </div>

                    <div v-if="form.footer_nav.explore_links.length === 0" class="px-5 py-6 text-center">
                        <p class="text-sm text-neutral-400">No links — use the button above to add one.</p>
                    </div>

                    <div
                        v-for="(link, i) in form.footer_nav.explore_links"
                        :key="i"
                        class="p-5"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2 text-neutral-400">
                                <span class="text-xs font-medium text-neutral-500">Link {{ i + 1 }}</span>
                            </div>
                            <button
                                type="button"
                                @click="removeLink('explore_links', i)"
                                class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <AppInput
                                :id="`explore-label-${i}`"
                                v-model="form.footer_nav.explore_links[i].label"
                                label="Label"
                                placeholder="About Us"
                                :error="(form.errors as any)[`footer_nav.explore_links.${i}.label`]"
                            />
                            <AppInput
                                :id="`explore-href-${i}`"
                                v-model="form.footer_nav.explore_links[i].href"
                                label="URL"
                                placeholder="/about"
                                :error="(form.errors as any)[`footer_nav.explore_links.${i}.href`]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Connect group -->
                <div class="divide-y divide-neutral-100">
                    <div class="px-5 py-3 flex items-center justify-between bg-neutral-50">
                        <span class="text-xs font-semibold uppercase tracking-widest text-neutral-500">Connect</span>
                        <AppButton
                            type="button" variant="outline" size="sm"
                            @click="addLink('connect_links')"
                            :disabled="form.footer_nav.connect_links.length >= 12"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            Add link
                        </AppButton>
                    </div>

                    <div v-if="form.footer_nav.connect_links.length === 0" class="px-5 py-6 text-center">
                        <p class="text-sm text-neutral-400">No links — use the button above to add one.</p>
                    </div>

                    <div
                        v-for="(link, i) in form.footer_nav.connect_links"
                        :key="i"
                        class="p-5"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2 text-neutral-400">
                                <span class="text-xs font-medium text-neutral-500">Link {{ i + 1 }}</span>
                            </div>
                            <button
                                type="button"
                                @click="removeLink('connect_links', i)"
                                class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                            >
                                <Trash2 class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <AppInput
                                :id="`connect-label-${i}`"
                                v-model="form.footer_nav.connect_links[i].label"
                                label="Label"
                                placeholder="Contact Us"
                                :error="(form.errors as any)[`footer_nav.connect_links.${i}.label`]"
                            />
                            <AppInput
                                :id="`connect-href-${i}`"
                                v-model="form.footer_nav.connect_links[i].href"
                                label="URL"
                                placeholder="/contact"
                                :error="(form.errors as any)[`footer_nav.connect_links.${i}.href`]"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save website settings</AppButton>
            </div>
        </form>
    </SettingsLayout>
</template>
