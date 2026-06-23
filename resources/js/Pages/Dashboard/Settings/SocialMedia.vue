<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'

// 'socials' is a page-specific prop name (not 'church') so it does not
// overwrite the globally-shared 'church' prop that TenantStore depends on.
const props = defineProps<{
    socials: Record<string, string> | null
}>()

const s = props.socials ?? {}

const form = useForm({
    socials: {
        facebook:  s.facebook  ?? '',
        instagram: s.instagram ?? '',
        twitter:   s.twitter   ?? '',
        youtube:   s.youtube   ?? '',
        tiktok:    s.tiktok    ?? '',
        spotify:   s.spotify   ?? '',
        linkedin:  s.linkedin  ?? '',
    },
})

function submit() {
    form.put('/dashboard/settings/social')
}
</script>

<template>
    <SettingsLayout section="social">

        <!-- Section header -->
        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Social Media</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Links shown in the website footer, contact page, and email signatures.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Profiles card -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Social profiles</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Paste the full URL for each platform your church is active on.
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        label="Facebook"
                        v-model="form.socials.facebook"
                        placeholder="https://facebook.com/yourpage"
                        :error="form.errors['socials.facebook']"
                    />
                    <AppInput
                        label="Instagram"
                        v-model="form.socials.instagram"
                        placeholder="https://instagram.com/yourhandle"
                        :error="form.errors['socials.instagram']"
                    />
                    <AppInput
                        label="Twitter / X"
                        v-model="form.socials.twitter"
                        placeholder="https://x.com/yourhandle"
                        :error="form.errors['socials.twitter']"
                    />
                    <AppInput
                        label="YouTube"
                        v-model="form.socials.youtube"
                        placeholder="https://youtube.com/@yourchannel"
                        :error="form.errors['socials.youtube']"
                    />
                </div>
            </div>

            <!-- More platforms card -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">More platforms</h3>
                </div>
                <div class="p-5 space-y-4">
                    <AppInput
                        label="TikTok"
                        v-model="form.socials.tiktok"
                        placeholder="https://tiktok.com/@yourhandle"
                        :error="form.errors['socials.tiktok']"
                    />
                    <AppInput
                        label="Spotify (Podcast)"
                        v-model="form.socials.spotify"
                        placeholder="https://open.spotify.com/show/..."
                        :error="form.errors['socials.spotify']"
                    />
                    <AppInput
                        label="LinkedIn"
                        v-model="form.socials.linkedin"
                        placeholder="https://linkedin.com/company/yourchurch"
                        :error="form.errors['socials.linkedin']"
                    />
                </div>
            </div>

            <!-- Footer actions -->
            <div class="flex items-center justify-end gap-3 pt-1">
                <Transition
                    enter-active-class="transition-opacity duration-300"
                    enter-from-class="opacity-0"
                    leave-active-class="transition-opacity duration-200"
                    leave-to-class="opacity-0"
                >
                    <p v-if="form.wasSuccessful" class="text-xs text-emerald-600 font-medium">
                        Social links saved.
                    </p>
                </Transition>
                <AppButton type="submit" :loading="form.processing">
                    Save changes
                </AppButton>
            </div>

        </form>
    </SettingsLayout>
</template>
