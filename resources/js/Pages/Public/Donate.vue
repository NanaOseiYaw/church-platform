<script setup lang="ts">
import { ref, computed } from 'vue'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import SectionWrapper from '@/Components/UI/SectionWrapper.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import type { DonationFund } from '@/types'
import { useChurch } from '@/composables/useChurch'
import { Heart, Building, Globe, Church } from 'lucide-vue-next'

const props = defineProps<{
    funds:            DonationFund[]
    suggestedAmounts: number[]
    stripeEnabled:    boolean
}>()

const { church } = useChurch()

const selectedFund = ref('general')
const selectedAmount = ref(50)
const customAmount = ref('')

const fundIconMap: Record<string, any> = {
    church: Church, building: Building, globe: Globe, heart: Heart,
}

const metaDescription = computed(() =>
    `Support the mission of ${church.value.name} through your generous giving.`
)

const checkingOut    = ref(false)
const checkoutError  = ref<string | null>(null)

// The active fund object for display
const activeFund = computed(() =>
    props.funds.find(f => f.id === selectedFund.value) ?? props.funds[0] ?? null
)

// Dollar amount shown (custom input takes precedence over preset)
const displayAmount = computed(() => {
    const custom = parseFloat(customAmount.value)
    return isNaN(custom) || custom <= 0 ? selectedAmount.value : custom
})

// Amount in cents for the Stripe API
const amountCents = computed(() => Math.round(displayAmount.value * 100))

async function startCheckout() {
    if (amountCents.value < 100) return
    checkingOut.value   = true
    checkoutError.value = null

    try {
        const resp = await fetch('/give/checkout', {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({
                fund_id:      selectedFund.value,
                amount_cents: amountCents.value,
            }),
        })

        const data = await resp.json()

        if (!resp.ok || !data.url) {
            checkoutError.value = data.error ?? 'Something went wrong. Please try again.'
            return
        }

        window.location.href = data.url
    } catch {
        checkoutError.value = 'Network error. Please check your connection and try again.'
    } finally {
        checkingOut.value = false
    }
}
</script>

<template>
    <PublicLayout title="Give" :description="metaDescription">
        <!-- Hero -->
        <div class="bg-neutral-950 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-25"
                style="background: radial-gradient(ellipse 60% 60% at 20% 50%, rgba(30,90,168,0.6) 0%, transparent 70%);"
                aria-hidden="true"></div>
            <div class="relative mx-auto max-w-7xl px-6 lg:px-8 py-24">
                <h1 class="text-5xl font-display text-white leading-tight max-w-xl mb-4">
                    Give with a joyful heart.
                </h1>
                <p class="text-xl text-neutral-400 max-w-lg">
                    Your generosity fuels the mission — ministry, outreach, community, and care.
                </p>
            </div>
        </div>

        <SectionWrapper bg="white">
            <div class="grid lg:grid-cols-2 gap-12 items-start">
                <!-- Fund selector -->
                <div class="reveal">
                    <h2 class="text-xl font-semibold text-neutral-900 mb-2">Choose a Fund</h2>
                    <p class="text-sm text-neutral-500 mb-6">Your gift goes directly to the area you care about most.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <button
                            v-for="fund in funds"
                            :key="fund.id"
                            class="flex items-start gap-3 p-4 rounded-xl border text-left transition-all duration-150"
                            :class="selectedFund === fund.id
                                ? 'border-brand-400 bg-brand-50 ring-2 ring-brand-300/30'
                                : 'border-neutral-100 bg-white hover:border-neutral-200 hover:bg-neutral-50'"
                            @click="selectedFund = fund.id"
                        >
                            <div
                                class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                                :class="selectedFund === fund.id ? 'bg-brand-100 text-brand-600' : 'bg-neutral-100 text-neutral-500'"
                            >
                                <component :is="fundIconMap[fund.icon] ?? Heart" class="w-4 h-4" />
                            </div>
                            <div>
                                <p class="font-semibold text-sm text-neutral-900">{{ fund.name }}</p>
                                <p class="text-xs text-neutral-500 leading-relaxed mt-0.5">{{ fund.description }}</p>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Payment panel -->
                <div class="reveal reveal-delay-2">

                    <!-- Stripe configured — show payment form -->
                    <template v-if="stripeEnabled">
                        <h2 class="text-xl font-semibold text-neutral-900 mb-2">Complete Your Gift</h2>
                        <p class="text-sm text-neutral-500 mb-6">
                            Giving to: <strong>{{ activeFund?.name ?? 'General Fund' }}</strong>
                        </p>

                        <!-- Suggested amounts -->
                        <div class="mb-5">
                            <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-3">Select an amount</p>
                            <div class="grid grid-cols-3 gap-2 mb-3">
                                <button
                                    v-for="amt in suggestedAmounts"
                                    :key="amt"
                                    type="button"
                                    class="py-2.5 rounded-xl border text-sm font-semibold transition-all duration-150"
                                    :class="selectedAmount === amt && !customAmount
                                        ? 'border-brand-400 bg-brand-50 text-brand-700 ring-2 ring-brand-300/30'
                                        : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 hover:bg-neutral-50'"
                                    @click="selectedAmount = amt; customAmount = ''"
                                >
                                    ${{ amt }}
                                </button>
                            </div>
                            <input
                                v-model="customAmount"
                                type="number"
                                min="1"
                                step="1"
                                placeholder="Other amount ($)"
                                class="w-full px-3.5 py-2.5 text-sm border border-neutral-200 rounded-xl focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-300/20 placeholder:text-neutral-400"
                                @focus="selectedAmount = 0"
                            />
                        </div>

                        <!-- Amount summary -->
                        <div class="bg-brand-50 border border-brand-100 rounded-xl p-4 mb-5 flex justify-between items-center">
                            <span class="text-sm text-neutral-600">Your gift</span>
                            <span class="text-2xl font-bold text-brand-700">${{ displayAmount.toFixed(2) }}</span>
                        </div>

                        <!-- Error message -->
                        <p v-if="checkoutError" class="text-sm text-rose-600 mb-3 text-center">{{ checkoutError }}</p>

                        <AppButton
                            variant="primary"
                            size="lg"
                            class="w-full"
                            type="button"
                            :loading="checkingOut"
                            :disabled="amountCents < 100"
                            @click="startCheckout"
                        >
                            <Heart class="w-4 h-4 mr-2" />
                            Give Now — Secure Checkout
                        </AppButton>
                        <p class="text-xs text-neutral-400 text-center mt-3">
                            Secured by Stripe · 256-bit SSL encryption
                        </p>
                    </template>

                    <!-- Stripe not yet configured — keep existing placeholder -->
                    <template v-else>
                        <div class="bg-neutral-50 border border-neutral-100 rounded-2xl p-7 flex flex-col items-center text-center">
                            <div class="w-14 h-14 rounded-2xl bg-brand-50 flex items-center justify-center mb-4">
                                <Heart class="w-7 h-7 text-brand-500" />
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-600 text-xs font-semibold mb-3">
                                Online Giving — Coming Soon
                            </span>
                            <h3 class="text-base font-semibold text-neutral-900 mb-2">Give Online</h3>
                            <p class="text-sm text-neutral-500 leading-relaxed mb-6 max-w-xs">
                                Online giving is being set up. In the meantime, please give in person
                                during a service or contact us for bank transfer details.
                            </p>
                            <AppButton href="/contact" variant="primary">
                                Contact Us
                            </AppButton>
                        </div>
                    </template>

                </div>
            </div>
        </SectionWrapper>


    </PublicLayout>
</template>
