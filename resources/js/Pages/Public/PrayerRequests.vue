<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import PageHero from '@/Components/UI/PageHero.vue'
import { useChurch } from '@/composables/useChurch'
import { Heart, Send, CheckCircle2, Lock, EyeOff } from 'lucide-vue-next'

const { church } = useChurch()
const page = usePage()

const submitted = ref(false)

const form = useForm({
    name:         '',
    email:        '',
    request:      '',
    is_anonymous: false,
    is_private:   false,
})

const flashSuccess = computed(() => (page.props as any).flash?.success)

function submit() {
    form.post('/prayer', {
        onSuccess: () => {
            submitted.value = true
            form.reset()
        },
    })
}
</script>

<template>
    <PublicLayout
        title="Prayer Requests"
        :description="`Share your prayer needs with ${church?.name ?? 'our church community'}.`"
    >
        <!--
            NOTE: the prop is `subtitle`, not `description`. This previously passed
            `:description`, which PageHero does not declare, so the supporting line
            silently never rendered at all.

            The background image is not hard-coded here — it comes from
            Settings → Public Website → Header images → Prayer, falling back to the
            site-wide default and then to the brand gradient.
        -->
        <PageHero
            eyebrow="Prayer"
            title="Submit a Prayer Request"
            subtitle="We believe in the power of prayer. Share your request and our community will pray with you."
        >
            <template #actions>
                <figure class="max-w-2xl border-l-2 border-brand-400/70 pl-5">
                    <blockquote class="font-display text-lg md:text-xl text-white/90 leading-relaxed">
                        &ldquo;Do not be anxious about anything, but in every situation, by prayer
                        and petition, with thanksgiving, present your requests to God.&rdquo;
                    </blockquote>
                    <figcaption class="mt-3 text-xs font-semibold tracking-[0.2em] uppercase text-brand-300">
                        Philippians 4:6
                    </figcaption>
                </figure>
            </template>
        </PageHero>

        <SectionWrapper class="py-16">
            <div class="max-w-2xl mx-auto">

                <!-- Success message -->
                <div v-if="submitted || flashSuccess" class="flex flex-col items-center text-center py-16">
                    <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mb-5">
                        <CheckCircle2 class="w-9 h-9 text-emerald-600" />
                    </div>
                    <h2 class="text-2xl font-bold text-neutral-900 mb-2">Request Submitted</h2>
                    <p class="text-neutral-500 max-w-sm leading-relaxed">
                        Thank you for sharing. Our community is praying with you. May God grant you peace and strength.
                    </p>
                    <button
                        type="button"
                        @click="submitted = false"
                        class="mt-6 px-5 py-2.5 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-xl transition-colors"
                    >
                        Submit another request
                    </button>
                </div>

                <!-- Form -->
                <form v-else @submit.prevent="submit" class="bg-white border border-neutral-100 rounded-2xl p-6 sm:p-8 shadow-sm space-y-5">

                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-brand-50 rounded-xl flex items-center justify-center shrink-0">
                            <Heart class="w-5 h-5 text-brand-600" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-neutral-900">Your Prayer Request</h2>
                            <p class="text-xs text-neutral-500">We read every request and pray for each person.</p>
                        </div>
                    </div>

                    <!-- Name (hidden when anonymous) -->
                    <div v-if="!form.is_anonymous">
                        <label class="block text-sm font-medium text-neutral-700 mb-1">
                            Your Name <span class="text-neutral-400 font-normal">(optional)</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="e.g. John Mensah"
                            class="w-full rounded-xl border border-neutral-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                        />
                    </div>

                    <!-- Email (hidden when anonymous) -->
                    <div v-if="!form.is_anonymous">
                        <label class="block text-sm font-medium text-neutral-700 mb-1">
                            Email <span class="text-neutral-400 font-normal">(optional — for follow-up)</span>
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="you@example.com"
                            class="w-full rounded-xl border border-neutral-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition"
                        />
                    </div>

                    <!-- Request -->
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1">
                            Prayer Request <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            v-model="form.request"
                            rows="5"
                            placeholder="Share what's on your heart…"
                            class="w-full rounded-xl border border-neutral-200 px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-400 transition resize-none"
                            :class="{ 'border-red-400': form.errors.request }"
                            required
                        />
                        <p v-if="form.errors.request" class="mt-1 text-xs text-red-500">{{ form.errors.request }}</p>
                    </div>

                    <!-- Options -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <label class="flex items-center gap-2.5 cursor-pointer group">
                            <input
                                v-model="form.is_anonymous"
                                type="checkbox"
                                class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                            />
                            <span class="flex items-center gap-1.5 text-sm text-neutral-600">
                                <EyeOff class="w-3.5 h-3.5 text-neutral-400" />
                                Submit anonymously
                            </span>
                        </label>
                        <label class="flex items-center gap-2.5 cursor-pointer group">
                            <input
                                v-model="form.is_private"
                                type="checkbox"
                                class="w-4 h-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
                            />
                            <span class="flex items-center gap-1.5 text-sm text-neutral-600">
                                <Lock class="w-3.5 h-3.5 text-neutral-400" />
                                Keep private (pastors only)
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-brand-600 hover:bg-brand-700 disabled:opacity-60 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            <Send class="w-4 h-4" />
                            {{ form.processing ? 'Submitting…' : 'Submit Request' }}
                        </button>
                    </div>
                </form>
            </div>
        </SectionWrapper>
    </PublicLayout>
</template>
