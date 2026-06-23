<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Plus, Trash2, GripVertical, BarChart2, MessageSquare, AlignLeft, ImagePlus, Upload } from 'lucide-vue-next'

interface StatItem {
    label: string
    value: string
}

interface Testimonial {
    name: string
    text: string
}

interface SectionVisibility {
    events:        boolean
    ministry:      boolean
    sermons:       boolean
    testimonials:  boolean
    announcements: boolean
    livestream:    boolean
}

interface Settings {
    hero_description:      string | null
    hero_image:            string | null
    stats:                 StatItem[]
    testimonials:          Testimonial[]
    events_subtitle:       string | null
    ministry_heading:      string | null
    ministry_body:         string | null
    sermons_subtitle:      string | null
    testimonials_subtitle: string | null
    livestream_cta:        string | null
    sermons_page_subtitle: string | null
    section_visibility:    SectionVisibility
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    hero_description:      props.settings.hero_description      ?? '',
    stats:                 props.settings.stats.map(s => ({ ...s }))        as StatItem[],
    testimonials:          props.settings.testimonials.map(t => ({ ...t })) as Testimonial[],
    events_subtitle:       props.settings.events_subtitle       ?? '',
    ministry_heading:      props.settings.ministry_heading      ?? '',
    ministry_body:         props.settings.ministry_body         ?? '',
    sermons_subtitle:      props.settings.sermons_subtitle      ?? '',
    testimonials_subtitle: props.settings.testimonials_subtitle ?? '',
    livestream_cta:        props.settings.livestream_cta        ?? '',
    sermons_page_subtitle: props.settings.sermons_page_subtitle ?? '',
    section_visibility:    { ...props.settings.section_visibility } as SectionVisibility,
})

// ── Stats helpers ─────────────────────────────────────────────────────────────

function addStat() {
    form.stats.push({ label: '', value: '' })
}

function removeStat(i: number) {
    form.stats.splice(i, 1)
}

// ── Testimonials helpers ──────────────────────────────────────────────────────

function addTestimonial() {
    form.testimonials.push({ name: '', text: '' })
}

function removeTestimonial(i: number) {
    form.testimonials.splice(i, 1)
}

// ── Section visibility helper ─────────────────────────────────────────────────

function toggleSection(key: keyof SectionVisibility) {
    form.section_visibility[key] = !form.section_visibility[key]
}

// ── Save ─────────────────────────────────────────────────────────────────────

function submit() {
    form.put('/dashboard/settings/homepage')
}

// ── Hero image upload ─────────────────────────────────────────────────────────
const heroImageFileRef    = ref<HTMLInputElement | null>(null)
const heroImageForm       = useForm({ hero_image: null as File | null })
const heroImagePreviewUrl = ref<string | null>(props.settings.hero_image)

function triggerHeroImageUpload() {
    heroImageFileRef.value?.click()
}

function onHeroImageFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    heroImagePreviewUrl.value = URL.createObjectURL(file)
    heroImageForm.hero_image = file
    heroImageForm.post('/dashboard/settings/homepage/hero-image', {
        preserveScroll: true,
        onSuccess: () => {
            heroImageForm.reset()
            if (heroImageFileRef.value) heroImageFileRef.value.value = ''
        },
    })
}
</script>

<template>
    <SettingsLayout section="homepage">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Homepage</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Configure the stats banner and testimonials shown on the public home page.
                Leave both sections empty to hide them from visitors.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">

            <!-- ── Hero image ────────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center gap-2">
                    <ImagePlus class="w-4 h-4 text-neutral-400 shrink-0" />
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Hero image</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Background photo shown in the homepage hero. Leave blank to use the default dark gradient.
                            Recommended: landscape, at least 1920×1080 px.
                        </p>
                    </div>
                </div>
                <div class="p-5">
                    <div v-if="heroImagePreviewUrl" class="mb-4 rounded-xl overflow-hidden aspect-video bg-neutral-100 relative">
                        <img :src="heroImagePreviewUrl" class="w-full h-full object-cover" alt="Hero preview" />
                    </div>
                    <input
                        ref="heroImageFileRef"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="sr-only"
                        @change="onHeroImageFileChange"
                    />
                    <AppButton
                        variant="outline"
                        size="sm"
                        type="button"
                        :loading="heroImageForm.processing"
                        @click="triggerHeroImageUpload"
                    >
                        <Upload class="w-3.5 h-3.5 mr-1.5" />
                        {{ heroImagePreviewUrl ? 'Replace image' : 'Upload image' }}
                    </AppButton>
                    <p class="text-xs text-neutral-400 mt-1.5">JPG, PNG or WebP · max 5 MB</p>
                    <p v-if="heroImageForm.errors.hero_image" class="text-xs text-rose-500 mt-1">
                        {{ (heroImageForm.errors as any).hero_image }}
                    </p>
                </div>
            </div>

            <!-- ── Hero description ───────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center gap-2">
                    <AlignLeft class="w-4 h-4 text-neutral-400 shrink-0" />
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Hero description</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Short paragraph shown beneath the headline on the homepage hero.
                            Leave blank to use the church description from Church Profile, or the platform default.
                        </p>
                    </div>
                </div>
                <div class="p-5">
                    <textarea
                        v-model="form.hero_description"
                        rows="3"
                        maxlength="300"
                        placeholder="A welcoming community where faith grows, lives change, and everyone belongs."
                        class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10 resize-none"
                        :class="{ 'border-rose-400': (form.errors as any).hero_description }"
                    />
                    <div class="flex items-center justify-between mt-1">
                        <p v-if="(form.errors as any).hero_description" class="text-xs text-rose-500">
                            {{ (form.errors as any).hero_description }}
                        </p>
                        <span v-else />
                        <p class="text-[11px] text-neutral-400">{{ form.hero_description.length }}/300</p>
                    </div>
                </div>
            </div>

            <!-- ── Section Copy ──────────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Section Copy</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Override the subtitle text for each homepage section. Leave blank to use the defaults.
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        id="events_subtitle"
                        v-model="form.events_subtitle"
                        label="Events subtitle"
                        placeholder="Join us for worship, community, and service."
                        :error="(form.errors as any).events_subtitle"
                    />
                    <AppInput
                        id="ministry_heading"
                        v-model="form.ministry_heading"
                        label="Ministry section heading"
                        placeholder="Find your place in our community."
                        :error="(form.errors as any).ministry_heading"
                    />
                    <AppInput
                        id="ministry_body"
                        v-model="form.ministry_body"
                        label="Ministry section body"
                        placeholder="There is a place for every person in the family of God."
                        :error="(form.errors as any).ministry_body"
                    />
                    <AppInput
                        id="sermons_subtitle"
                        v-model="form.sermons_subtitle"
                        label="Sermons section subtitle"
                        placeholder="Grow in faith with teaching rooted in scripture."
                        :error="(form.errors as any).sermons_subtitle"
                    />
                    <AppInput
                        id="testimonials_subtitle"
                        v-model="form.testimonials_subtitle"
                        label="Testimonials subtitle"
                        placeholder="Hear from people whose lives have been transformed."
                        :error="(form.errors as any).testimonials_subtitle"
                    />
                    <AppInput
                        id="livestream_cta"
                        v-model="form.livestream_cta"
                        label="Livestream CTA text"
                        placeholder="Experience our Sunday services from anywhere in the world."
                        :error="(form.errors as any).livestream_cta"
                    />
                    <AppInput
                        id="sermons_page_subtitle"
                        v-model="form.sermons_page_subtitle"
                        label="Sermons page subtitle"
                        placeholder="Deep, scripture-rooted teaching to strengthen your faith."
                        :error="(form.errors as any).sermons_page_subtitle"
                    />
                </div>
            </div>

            <!-- ── Section Visibility ────────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Section Visibility</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Toggle which sections appear on the public homepage.
                    </p>
                </div>
                <div class="p-5 space-y-3">
                    <div
                        v-for="section in (['events', 'ministry', 'sermons', 'testimonials', 'announcements', 'livestream'] as const)"
                        :key="section"
                        class="flex items-center justify-between"
                    >
                        <span class="text-sm text-neutral-700 capitalize">{{ section }}</span>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.section_visibility[section]"
                            @click="toggleSection(section)"
                            :class="[
                                'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-1',
                                form.section_visibility[section] ? 'bg-brand-600' : 'bg-neutral-200',
                            ]"
                        >
                            <span
                                :class="[
                                    'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out',
                                    form.section_visibility[section] ? 'translate-x-4' : 'translate-x-0',
                                ]"
                            />
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Stats ──────────────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <BarChart2 class="w-4 h-4 text-neutral-400 shrink-0" />
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-900">Stats banner</h3>
                            <p class="text-xs text-neutral-500 mt-0.5">Numbers shown beneath the hero headline (e.g. "500+ Members").</p>
                        </div>
                    </div>
                    <AppButton type="button" variant="outline" size="sm" @click="addStat" :disabled="form.stats.length >= 6">
                        <Plus class="w-3.5 h-3.5" />
                        Add stat
                    </AppButton>
                </div>

                <!-- Empty state -->
                <div v-if="form.stats.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No stats added — the stats banner will be hidden on the public site.</p>
                    <p class="text-xs text-neutral-400 mt-1">Add up to 6 stats (e.g. "500+ Members", "12 Departments").</p>
                </div>

                <!-- Stat rows -->
                <div
                    v-for="(stat, i) in form.stats"
                    :key="i"
                    class="p-5"
                >
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2 text-neutral-400">
                            <GripVertical class="w-4 h-4" />
                            <span class="text-xs font-medium text-neutral-500">Stat {{ i + 1 }}</span>
                        </div>
                        <button
                            type="button"
                            @click="removeStat(i)"
                            class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <AppInput
                            :id="`stat-value-${i}`"
                            v-model="form.stats[i].value"
                            label="Value"
                            placeholder="500+"
                            :error="(form.errors as any)[`stats.${i}.value`]"
                        />
                        <AppInput
                            :id="`stat-label-${i}`"
                            v-model="form.stats[i].label"
                            label="Label"
                            placeholder="Members"
                            :error="(form.errors as any)[`stats.${i}.label`]"
                        />
                    </div>
                </div>
            </div>

            <!-- ── Testimonials ───────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <MessageSquare class="w-4 h-4 text-neutral-400 shrink-0" />
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-900">Testimonials</h3>
                            <p class="text-xs text-neutral-500 mt-0.5">Quotes from church members shown in the testimonials section.</p>
                        </div>
                    </div>
                    <AppButton type="button" variant="outline" size="sm" @click="addTestimonial" :disabled="form.testimonials.length >= 10">
                        <Plus class="w-3.5 h-3.5" />
                        Add quote
                    </AppButton>
                </div>

                <!-- Empty state -->
                <div v-if="form.testimonials.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No testimonials added — the testimonials section will be hidden.</p>
                </div>

                <!-- Testimonial rows -->
                <div
                    v-for="(testimonial, i) in form.testimonials"
                    :key="i"
                    class="p-5 space-y-4"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-neutral-400">
                            <GripVertical class="w-4 h-4" />
                            <span class="text-xs font-medium text-neutral-500">Quote {{ i + 1 }}</span>
                        </div>
                        <button
                            type="button"
                            @click="removeTestimonial(i)"
                            class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>
                    <AppInput
                        :id="`testimonial-name-${i}`"
                        v-model="form.testimonials[i].name"
                        label="Name"
                        placeholder="Jane Smith"
                        :error="(form.errors as any)[`testimonials.${i}.name`]"
                    />
                    <AppInput
                        :id="`testimonial-text-${i}`"
                        v-model="form.testimonials[i].text"
                        label="Quote"
                        placeholder="This church has changed my life..."
                        :error="(form.errors as any)[`testimonials.${i}.text`]"
                    />
                </div>
            </div>

            <!-- Save -->
            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save homepage content</AppButton>
            </div>
        </form>

    </SettingsLayout>
</template>
