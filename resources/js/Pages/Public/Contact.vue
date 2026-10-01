<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppTextarea from '@/Components/UI/AppTextarea.vue'
import type { ServiceTime } from '@/types'
import { useChurch } from '@/composables/useChurch'
import { MapPin, Phone, Mail, Clock } from 'lucide-vue-next'

defineProps<{
    address: string
    phone: string
    email: string
    serviceTimes: ServiceTime[]
}>()

const { church } = useChurch()

const form = useForm({
    name:    '',
    email:   '',
    subject: '',
    message: '',
})

function submit() {
    form.post('/contact', { preserveScroll: true })
}
</script>

<template>
    <PublicLayout title="Contact" :description="`Get in touch with ${church.name}.`">

        <PageHero
            title="Get in Touch"
            subtitle="We would love to hear from you. Reach out with questions, prayer requests, or to plan your first visit."
        />

        <SectionWrapper bg="white">
            <div class="grid lg:grid-cols-5 gap-12">

                <!-- Contact info sidebar -->
                <div class="lg:col-span-2 space-y-8 reveal">

                    <!-- Contact details -->
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-900 uppercase tracking-widest mb-5">Contact Information</h2>
                        <div class="space-y-4">
                            <div class="flex items-start gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-brand-50 border border-brand-100 flex items-center justify-center shrink-0">
                                    <MapPin class="w-4 h-4 text-brand-600" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-0.5">Address</p>
                                    <p class="text-sm text-neutral-700">{{ address }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-brand-50 border border-brand-100 flex items-center justify-center shrink-0">
                                    <Phone class="w-4 h-4 text-brand-600" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-0.5">Phone</p>
                                    <a :href="`tel:${phone}`" class="text-sm text-neutral-700 hover:text-brand-600 transition-colors">{{ phone }}</a>
                                </div>
                            </div>
                            <div class="flex items-start gap-3.5">
                                <div class="w-9 h-9 rounded-xl bg-brand-50 border border-brand-100 flex items-center justify-center shrink-0">
                                    <Mail class="w-4 h-4 text-brand-600" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-0.5">Email</p>
                                    <a :href="`mailto:${email}`" class="text-sm text-neutral-700 hover:text-brand-600 transition-colors">{{ email }}</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service times -->
                    <div>
                        <h2 class="text-sm font-semibold text-neutral-900 uppercase tracking-widest mb-4 flex items-center gap-2">
                            <Clock class="w-4 h-4 text-brand-500" />
                            Service Times
                        </h2>
                        <div class="space-y-2">
                            <div
                                v-for="service in serviceTimes"
                                :key="service.day"
                                class="flex items-center justify-between text-sm bg-neutral-50 border border-neutral-100 rounded-xl px-4 py-3"
                            >
                                <span class="font-semibold text-neutral-800">{{ service.day }}</span>
                                <span class="text-neutral-500 text-xs">{{ service.times.join(' · ') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact form -->
                <div class="lg:col-span-3 reveal reveal-delay-2">
                    <div class="bg-white border border-neutral-100 rounded-2xl shadow-card p-8">
                        <h2 class="text-lg font-semibold text-neutral-900 mb-6">Send us a message</h2>

                        <form @submit.prevent="submit" class="space-y-5">
                            <div class="grid sm:grid-cols-2 gap-5">
                                <AppInput
                                    id="name"
                                    v-model="form.name"
                                    label="Your Name"
                                    placeholder="John Doe"
                                    required
                                    :error="form.errors.name"
                                />
                                <AppInput
                                    id="email"
                                    v-model="form.email"
                                    label="Email Address"
                                    type="email"
                                    placeholder="john@example.com"
                                    required
                                    :error="form.errors.email"
                                />
                            </div>
                            <AppInput
                                id="subject"
                                v-model="form.subject"
                                label="Subject"
                                placeholder="How can we help?"
                                required
                                :error="form.errors.subject"
                            />
                            <AppTextarea
                                id="message"
                                v-model="form.message"
                                label="Message"
                                placeholder="Tell us more..."
                                :rows="5"
                                required
                                :error="form.errors.message"
                            />
                            <AppButton type="submit" variant="primary" size="lg" :disabled="form.processing" class="w-full">
                                {{ form.processing ? 'Sending…' : 'Send Message' }}
                            </AppButton>
                        </form>
                    </div>
                </div>

            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
