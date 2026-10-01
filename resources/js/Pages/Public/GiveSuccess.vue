<script setup lang="ts">
import PublicLayout from '@/Layouts/PublicLayout.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { useChurch } from '@/composables/useChurch'
import { CheckCircle } from 'lucide-vue-next'

const { church } = useChurch()
</script>

<template>
    <PublicLayout
        title="Thank You"
        description="Your gift has been received. Thank you for your generosity."
    >
        <div class="min-h-[70vh] flex items-center justify-center bg-white px-6 py-24">
            <div class="text-center max-w-md reveal">

                <div class="w-20 h-20 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-6">
                    <CheckCircle class="w-10 h-10 text-emerald-500" />
                </div>

                <h1 class="text-3xl font-display text-neutral-900 mb-3 leading-tight">
                    Thank you for your generosity!
                </h1>

                <p class="text-neutral-500 leading-relaxed mb-8">
                    Your gift to {{ church.name }} has been received and will be put to work
                    for the ministry. You'll receive a receipt at your email shortly.
                </p>

                <div class="flex gap-3 justify-center">
                    <AppButton href="/give" variant="outline">Give Again</AppButton>
                    <AppButton href="/" variant="primary">Return Home</AppButton>
                </div>

            </div>
        </div>
    </PublicLayout>
</template>
