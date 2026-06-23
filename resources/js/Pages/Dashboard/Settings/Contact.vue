<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'

interface ContactInfo {
    address: string | null
    phone: string | null
    email: string | null
}

// Named 'contactInfo' (not 'church') to avoid overwriting the shared TenantStore prop.
const props = defineProps<{ contactInfo: ContactInfo }>()

const form = useForm({
    address: props.contactInfo.address ?? '',
    phone:   props.contactInfo.phone   ?? '',
    email:   props.contactInfo.email   ?? '',
})

function submit() {
    form.put('/dashboard/settings/contact')
}
</script>

<template>
    <SettingsLayout section="contact">

        <!-- Section header -->
        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Contact</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Public contact details shown on the website and in emails.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-xl space-y-5">

            <!-- Address card -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Location</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Shown on the contact page and event directions.
                    </p>
                </div>
                <div class="p-5">
                    <AppInput
                        label="Address"
                        v-model="form.address"
                        placeholder="123 Church Street, City, State 00000"
                        :error="form.errors.address"
                    />
                </div>
            </div>

            <!-- Contact details card -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4">
                    <h3 class="text-sm font-semibold text-neutral-900">Reach</h3>
                    <p class="text-xs text-neutral-500 mt-0.5">
                        Phone and email used for public enquiries.
                    </p>
                </div>
                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <AppInput
                            label="Phone"
                            v-model="form.phone"
                            placeholder="+1 (555) 000-0000"
                            :error="form.errors.phone"
                        />
                        <AppInput
                            label="Email"
                            type="email"
                            v-model="form.email"
                            placeholder="hello@yourchurch.org"
                            :error="form.errors.email"
                        />
                    </div>
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
                        Contact details saved.
                    </p>
                </Transition>
                <AppButton type="submit" :loading="form.processing">
                    Save changes
                </AppButton>
            </div>

        </form>
    </SettingsLayout>
</template>
