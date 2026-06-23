<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppAvatar from '@/Components/UI/AppAvatar.vue'
import { UserCog, KeyRound, Camera } from 'lucide-vue-next'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    profile: {
        id:     number
        name:   string
        email:  string
        avatar: string | null
        roles:  string[]
    }
}>()

// ── Name form ─────────────────────────────────────────────────────────────────

const nameForm = useForm({
    name: props.profile.name,
})

function saveName() {
    nameForm.patch('/dashboard/profile', { preserveScroll: true })
}

// ── Password form ─────────────────────────────────────────────────────────────

const pwForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
})

function savePassword() {
    pwForm.patch('/dashboard/profile/password', {
        preserveScroll: true,
        onSuccess: () => pwForm.reset(),
    })
}

// ── Avatar upload ──────────────────────────────────────────────────────────────
const avatarFileRef    = ref<HTMLInputElement | null>(null)
const avatarForm       = useForm({ avatar: null as File | null })
const avatarPreviewUrl = ref<string | null>(props.profile.avatar)

function triggerAvatarUpload() {
    avatarFileRef.value?.click()
}

function onAvatarFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0]
    if (!file) return
    avatarPreviewUrl.value = URL.createObjectURL(file)
    avatarForm.avatar = file
    avatarForm.post('/dashboard/profile/avatar', {
        preserveScroll: true,
        onSuccess: () => {
            avatarForm.reset()
            if (avatarFileRef.value) avatarFileRef.value.value = ''
        },
    })
}

// ── Helpers ────────────────────────────────────────────────────────────────────

function formatRole(role: string): string {
    return role.replace(/_/g, ' ')
}
</script>

<template>
    <DashboardLayout
        title="Profile Settings"
        :breadcrumbs="[{ label: 'Dashboard', href: '/dashboard' }, { label: 'Profile' }]"
    >
        <PageHeader title="Profile Settings" description="Manage your personal account details." />

        <div class="max-w-2xl space-y-5">

            <!-- ── Identity card ─────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-50">
                <div class="px-5 py-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-50 flex items-center justify-center">
                        <UserCog class="w-4.5 h-4.5 text-brand-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Identity</h3>
                        <p class="text-xs text-neutral-500">Your name visible to other church members.</p>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Avatar + role -->
                    <div class="flex items-center gap-4">
                        <!-- Clickable avatar with camera overlay -->
                        <div class="relative group shrink-0 cursor-pointer" @click="triggerAvatarUpload">
                            <AppAvatar :name="profile.name" :src="avatarPreviewUrl" size="xl" />
                            <div class="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <Camera class="w-4 h-4 text-white" />
                            </div>
                            <input
                                ref="avatarFileRef"
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/gif"
                                class="sr-only"
                                @change="onAvatarFileChange"
                            />
                        </div>
                        <div>
                            <p class="text-sm font-medium text-neutral-900">{{ profile.name }}</p>
                            <p class="text-xs text-neutral-400">{{ profile.email }}</p>
                            <button
                                type="button"
                                class="text-xs text-brand-600 hover:underline mt-0.5"
                                :class="{ 'opacity-50 pointer-events-none': avatarForm.processing }"
                                @click="triggerAvatarUpload"
                            >
                                {{ avatarForm.processing ? 'Uploading…' : 'Change photo' }}
                            </button>
                            <p v-if="avatarForm.errors.avatar" class="text-xs text-rose-500 mt-0.5">
                                {{ avatarForm.errors.avatar }}
                            </p>
                            <div class="flex gap-1.5 mt-1 flex-wrap">
                                <span
                                    v-for="role in profile.roles"
                                    :key="role"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium capitalize bg-brand-50 text-brand-700"
                                >
                                    {{ formatRole(role) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Name field -->
                    <form class="space-y-4" @submit.prevent="saveName">
                        <AppInput
                            label="Display name"
                            v-model="nameForm.name"
                            :error="nameForm.errors.name"
                            required
                        />
                        <AppInput
                            label="Email address"
                            type="email"
                            :model-value="profile.email"
                            disabled
                            hint="Email changes are handled by an administrator."
                        />
                        <div class="flex items-center gap-3 pt-1">
                            <AppButton type="submit" :loading="nameForm.processing" size="sm">
                                Save name
                            </AppButton>
                            <p v-if="nameForm.wasSuccessful" class="text-xs text-emerald-600 font-medium">Saved.</p>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Password card ─────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-50">
                <div class="px-5 py-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-50 flex items-center justify-center">
                        <KeyRound class="w-4.5 h-4.5 text-brand-600" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Change Password</h3>
                        <p class="text-xs text-neutral-500">Minimum 8 characters. Use a strong password.</p>
                    </div>
                </div>

                <div class="p-5">
                    <form class="space-y-4" @submit.prevent="savePassword">
                        <AppInput
                            label="Current password"
                            type="password"
                            v-model="pwForm.current_password"
                            :error="pwForm.errors.current_password"
                            required
                        />
                        <AppInput
                            label="New password"
                            type="password"
                            v-model="pwForm.password"
                            :error="pwForm.errors.password"
                            required
                        />
                        <AppInput
                            label="Confirm new password"
                            type="password"
                            v-model="pwForm.password_confirmation"
                            :error="pwForm.errors.password_confirmation"
                            required
                        />
                        <div class="flex items-center gap-3 pt-1">
                            <AppButton type="submit" :loading="pwForm.processing" size="sm">
                                Change password
                            </AppButton>
                            <p v-if="pwForm.wasSuccessful" class="text-xs text-emerald-600 font-medium">Password updated.</p>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </DashboardLayout>
</template>
