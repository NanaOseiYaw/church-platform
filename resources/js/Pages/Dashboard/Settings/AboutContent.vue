<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Plus, Trash2, GripVertical } from 'lucide-vue-next'
import { useChurch } from '@/composables/useChurch'

interface TeamMember {
    name:  string
    role:  string
    bio:   string
    image: string
}

interface ChurchValue {
    title:       string
    description: string
}

interface Settings {
    team:                TeamMember[]
    values:              ChurchValue[]
    hero_title:          string | null
    hero_eyebrow:        string | null
    hero_subtitle:       string | null
    leadership_subtitle: string | null
}

const props = defineProps<{ settings: Settings }>()

const { church } = useChurch()

const form = useForm({
    team:                props.settings.team.map(m => ({ ...m })) as TeamMember[],
    values:              props.settings.values.map(v => ({ ...v })) as ChurchValue[],
    hero_title:          props.settings.hero_title          ?? '',
    hero_eyebrow:        props.settings.hero_eyebrow        ?? '',
    hero_subtitle:       props.settings.hero_subtitle       ?? '',
    leadership_subtitle: props.settings.leadership_subtitle ?? '',
})

// ── Team helpers ──────────────────────────────────────────────────────────────

function addMember() {
    form.team.push({ name: '', role: '', bio: '', image: '' })
}

function removeMember(i: number) {
    form.team.splice(i, 1)
}

// ── Values helpers ────────────────────────────────────────────────────────────

function addValue() {
    form.values.push({ title: '', description: '' })
}

function removeValue(i: number) {
    form.values.splice(i, 1)
}

// ── Save ─────────────────────────────────────────────────────────────────────

function submit() {
    form.put('/dashboard/settings/about-content')
}
</script>

<template>
    <SettingsLayout section="about-content">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">About Page</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Manage the leadership team and church values shown on the public About page.
                Mission and vision text is set in
                <a href="/dashboard/settings/profile" class="text-brand-600 hover:underline">Church Profile</a>.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">

            <!-- ── Hero & Heading Copy ───────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Hero & Heading Copy</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Customise the About page headline and section subtitles.
                        Leave blank to use the defaults.
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        id="hero_eyebrow"
                        v-model="form.hero_eyebrow"
                        label="Hero eyebrow"
                        placeholder="Our Story"
                        hint="Small label above the main heading (default: Our Story)"
                        :error="(form.errors as any).hero_eyebrow"
                    />
                    <AppInput
                        id="hero_title"
                        v-model="form.hero_title"
                        label="Hero title"
                        :placeholder="church.name"
                        hint="Main heading on the About page (default: church name)"
                        :error="(form.errors as any).hero_title"
                    />
                    <AppInput
                        id="hero_subtitle"
                        v-model="form.hero_subtitle"
                        label="Hero subtitle"
                        placeholder="A community united in faith, growing and serving together."
                        hint="Short paragraph under the heading (max 300 characters)"
                        :error="(form.errors as any).hero_subtitle"
                    />
                    <AppInput
                        id="leadership_subtitle"
                        v-model="form.leadership_subtitle"
                        label="Leadership section subtitle"
                        placeholder="Meet the leaders who serve our community."
                        hint="Shown below the 'Meet our team.' heading (max 200 characters)"
                        :error="(form.errors as any).leadership_subtitle"
                    />
                </div>
            </div>

            <!-- ── Leadership Team ─────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Leadership Team</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">People shown in the "Meet our team" section.</p>
                    </div>
                    <AppButton type="button" variant="outline" size="sm" @click="addMember">
                        <Plus class="w-3.5 h-3.5" />
                        Add member
                    </AppButton>
                </div>

                <!-- Empty state -->
                <div v-if="form.team.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No team members added yet. Click "Add member" to get started.</p>
                    <p class="text-xs text-neutral-400 mt-1">The leadership section will be hidden on the public site until at least one member is added.</p>
                </div>

                <!-- Member rows -->
                <div
                    v-for="(member, i) in form.team"
                    :key="i"
                    class="p-5 space-y-4"
                >
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2 text-neutral-400">
                            <GripVertical class="w-4 h-4" />
                            <span class="text-xs font-medium text-neutral-500">Member {{ i + 1 }}</span>
                        </div>
                        <button
                            type="button"
                            @click="removeMember(i)"
                            class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                            :aria-label="`Remove ${member.name || 'member'}`"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <AppInput
                            :id="`team-name-${i}`"
                            v-model="form.team[i].name"
                            label="Full name"
                            placeholder="Pastor Jane Doe"
                            :error="(form.errors as any)[`team.${i}.name`]"
                        />
                        <AppInput
                            :id="`team-role-${i}`"
                            v-model="form.team[i].role"
                            label="Role / title"
                            placeholder="Senior Pastor"
                            :error="(form.errors as any)[`team.${i}.role`]"
                        />
                    </div>
                    <AppInput
                        :id="`team-bio-${i}`"
                        v-model="form.team[i].bio"
                        label="Short bio"
                        placeholder="A brief description of their ministry and role."
                        :error="(form.errors as any)[`team.${i}.bio`]"
                    />
                    <AppInput
                        :id="`team-image-${i}`"
                        v-model="form.team[i].image"
                        label="Photo URL (optional)"
                        placeholder="https://example.com/photo.jpg"
                        :error="(form.errors as any)[`team.${i}.image`]"
                    />
                </div>
            </div>

            <!-- ── Church Values ───────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Church Values</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">Shown in the values grid alongside your mission statement.</p>
                    </div>
                    <AppButton type="button" variant="outline" size="sm" @click="addValue">
                        <Plus class="w-3.5 h-3.5" />
                        Add value
                    </AppButton>
                </div>

                <!-- Empty state -->
                <div v-if="form.values.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No values added. Default values (Faith, Community, Service, Growth) will be used.</p>
                </div>

                <!-- Value rows -->
                <div
                    v-for="(value, i) in form.values"
                    :key="i"
                    class="p-5 space-y-3"
                >
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2 text-neutral-400">
                            <GripVertical class="w-4 h-4" />
                            <span class="text-xs font-medium text-neutral-500">Value {{ i + 1 }}</span>
                        </div>
                        <button
                            type="button"
                            @click="removeValue(i)"
                            class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                            :aria-label="`Remove ${value.title || 'value'}`"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <AppInput
                            :id="`value-title-${i}`"
                            v-model="form.values[i].title"
                            label="Title"
                            placeholder="e.g. Faith"
                            :error="(form.errors as any)[`values.${i}.title`]"
                        />
                        <AppInput
                            :id="`value-desc-${i}`"
                            v-model="form.values[i].description"
                            label="Description"
                            placeholder="One sentence about this value."
                            :error="(form.errors as any)[`values.${i}.description`]"
                        />
                    </div>
                </div>
            </div>

            <!-- Save -->
            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save about content</AppButton>
            </div>
        </form>

    </SettingsLayout>
</template>
