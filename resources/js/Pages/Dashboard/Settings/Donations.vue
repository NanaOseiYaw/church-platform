<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { computed } from 'vue'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import AppInput from '@/Components/UI/AppInput.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import { Plus, Trash2, GripVertical, CreditCard } from 'lucide-vue-next'

interface DonationFund {
    id:          string
    name:        string
    description: string
    icon:        string
}

interface Settings {
    funds:                      DonationFund[]
    stripe_publishable_key:     string | null
    stripe_has_secret_key:      boolean
    stripe_has_webhook_secret:  boolean
    suggested_amounts:          number[]
}

const props = defineProps<{ settings: Settings }>()

const form = useForm({
    funds:             props.settings.funds.map(f => ({ ...f })) as DonationFund[],
    suggested_amounts: [...props.settings.suggested_amounts] as number[],
})

const availableIcons = ['church', 'building', 'globe', 'heart', 'star', 'users', 'home', 'book']

function addFund() {
    form.funds.push({ id: '', name: '', description: '', icon: 'heart' })
}

function removeFund(i: number) {
    form.funds.splice(i, 1)
}

function addAmount() {
    if (form.suggested_amounts.length < 8) {
        form.suggested_amounts.push(0)
    }
}

function removeAmount(i: number) {
    form.suggested_amounts.splice(i, 1)
}

function submit() {
    form.put('/dashboard/settings/donations')
}

// ── Stripe keys form ──────────────────────────────────────────────────────────
const stripeForm = useForm({
    stripe_publishable_key: props.settings.stripe_publishable_key ?? '',
    stripe_secret_key:      '',
    stripe_webhook_secret:  '',
})

const webhookUrl = computed(() =>
    (typeof window !== 'undefined' ? window.location.origin : '') + '/api/stripe/webhook'
)

function submitStripe() {
    stripeForm.post('/dashboard/settings/donations/stripe', {
        preserveScroll: true,
    })
}
</script>

<template>
    <SettingsLayout section="donations">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Donations</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Manage the giving categories displayed on the public Give page.
                When no funds are configured, four default categories are shown.
            </p>
        </div>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6">

            <!-- ── Giving Funds ───────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Giving Funds</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">Categories visitors choose from on the Give page.</p>
                    </div>
                    <AppButton type="button" variant="outline" size="sm" @click="addFund" :disabled="form.funds.length >= 10">
                        <Plus class="w-3.5 h-3.5" />
                        Add fund
                    </AppButton>
                </div>

                <!-- Empty state -->
                <div v-if="form.funds.length === 0" class="px-5 py-8 text-center">
                    <p class="text-sm text-neutral-400">No custom funds configured.</p>
                    <p class="text-xs text-neutral-400 mt-1">
                        Default funds (General, Building, Missions, Benevolence) will be shown on the public site.
                    </p>
                </div>

                <!-- Fund rows -->
                <div
                    v-for="(fund, i) in form.funds"
                    :key="i"
                    class="p-5 space-y-4"
                >
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-2 text-neutral-400">
                            <GripVertical class="w-4 h-4" />
                            <span class="text-xs font-medium text-neutral-500">Fund {{ i + 1 }}</span>
                        </div>
                        <button
                            type="button"
                            @click="removeFund(i)"
                            class="p-1.5 rounded-lg text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                            :aria-label="`Remove ${fund.name || 'fund'}`"
                        >
                            <Trash2 class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <AppInput
                            :id="`fund-name-${i}`"
                            v-model="form.funds[i].name"
                            label="Fund name"
                            placeholder="General Fund"
                            :error="(form.errors as any)[`funds.${i}.name`]"
                        />
                        <AppInput
                            :id="`fund-id-${i}`"
                            v-model="form.funds[i].id"
                            label="Slug / ID"
                            placeholder="general"
                            hint="Lowercase, no spaces"
                            :error="(form.errors as any)[`funds.${i}.id`]"
                        />
                    </div>
                    <AppInput
                        :id="`fund-desc-${i}`"
                        v-model="form.funds[i].description"
                        label="Description"
                        placeholder="A short description of what this fund supports."
                        :error="(form.errors as any)[`funds.${i}.description`]"
                    />
                    <div>
                        <label class="block text-sm font-medium text-neutral-700 mb-1.5">Icon</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="icon in availableIcons"
                                :key="icon"
                                type="button"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-medium border transition-all duration-100',
                                    form.funds[i].icon === icon
                                        ? 'border-brand-400 bg-brand-50 text-brand-700'
                                        : 'border-neutral-200 bg-white text-neutral-500 hover:border-neutral-300',
                                ]"
                                @click="form.funds[i].icon = icon"
                            >
                                {{ icon }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Suggested Amounts ──────────────────────────────────────────────────── -->
            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Suggested Amounts</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Quick-select amounts shown on the Give page. Up to 8 amounts.
                        </p>
                    </div>
                    <AppButton
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addAmount"
                        :disabled="form.suggested_amounts.length >= 8"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        Add amount
                    </AppButton>
                </div>

                <div class="px-5 py-4">
                    <div v-if="form.suggested_amounts.length === 0" class="text-sm text-neutral-400 text-center py-2">
                        No custom amounts set. Default amounts (25, 50, 100, 250, 500) will be used.
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <div
                            v-for="(amount, i) in form.suggested_amounts"
                            :key="i"
                            class="flex items-center gap-1"
                        >
                            <input
                                type="number"
                                :value="amount"
                                @input="form.suggested_amounts[i] = Number(($event.target as HTMLInputElement).value)"
                                min="1"
                                max="1000000"
                                class="w-20 px-2 py-1.5 text-sm border border-neutral-200 rounded-lg text-center focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent"
                                :aria-label="`Amount ${i + 1}`"
                            />
                            <button
                                type="button"
                                @click="removeAmount(i)"
                                class="p-1 rounded text-neutral-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                :aria-label="`Remove amount ${i + 1}`"
                            >
                                <Trash2 class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="(form.errors as any)['suggested_amounts']"
                        class="text-xs text-rose-600 mt-2"
                    >
                        {{ (form.errors as any)['suggested_amounts'] }}
                    </p>
                </div>
            </div>

            <!-- Save -->
            <div class="flex items-center justify-between pt-1">
                <p v-if="form.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="form.processing">Save donation funds</AppButton>
            </div>
        </form>

        <!-- ── Stripe Integration ────────────────────────────────────────── -->
        <form @submit.prevent="submitStripe" class="max-w-2xl mt-8 space-y-6">

            <div class="bg-white border border-neutral-100 rounded-xl divide-y divide-neutral-100">
                <div class="px-5 py-4 flex items-center gap-2">
                    <CreditCard class="w-4 h-4 text-neutral-400 shrink-0" />
                    <div>
                        <h3 class="text-sm font-semibold text-neutral-900">Stripe Integration</h3>
                        <p class="text-xs text-neutral-500 mt-0.5">
                            Connect your Stripe account to accept online donations.
                            Get your keys at
                            <a href="https://dashboard.stripe.com/apikeys" target="_blank"
                               class="text-brand-500 underline hover:text-brand-700">dashboard.stripe.com/apikeys</a>.
                        </p>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <AppInput
                        id="publishable_key"
                        v-model="stripeForm.stripe_publishable_key"
                        label="Publishable Key"
                        placeholder="pk_live_..."
                        :error="(stripeForm.errors as any).stripe_publishable_key"
                    />

                    <div>
                        <AppInput
                            id="secret_key"
                            v-model="stripeForm.stripe_secret_key"
                            label="Secret Key"
                            type="password"
                            :placeholder="props.settings.stripe_has_secret_key
                                ? '(saved — leave blank to keep existing)'
                                : 'sk_live_...'"
                            :error="(stripeForm.errors as any).stripe_secret_key"
                        />
                        <p class="text-xs text-neutral-400 mt-1">
                            <span v-if="props.settings.stripe_has_secret_key" class="text-emerald-600 font-medium">✓ Key saved.</span>
                            Leave blank to keep existing key.
                        </p>
                    </div>

                    <div>
                        <AppInput
                            id="webhook_secret"
                            v-model="stripeForm.stripe_webhook_secret"
                            label="Webhook Signing Secret"
                            type="password"
                            :placeholder="props.settings.stripe_has_webhook_secret
                                ? '(saved — leave blank to keep existing)'
                                : 'whsec_...'"
                            :error="(stripeForm.errors as any).stripe_webhook_secret"
                        />
                        <p class="text-xs text-neutral-400 mt-1">
                            <span v-if="props.settings.stripe_has_webhook_secret" class="text-emerald-600 font-medium">✓ Secret saved.</span>
                            In Stripe Dashboard → Developers → Webhooks → Add endpoint.
                            Register URL: <code class="text-[11px] bg-neutral-100 px-1.5 py-0.5 rounded font-mono">{{ webhookUrl }}</code>
                            · Event: <code class="text-[11px] bg-neutral-100 px-1.5 py-0.5 rounded font-mono">checkout.session.completed</code>
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-1">
                <p v-if="stripeForm.recentlySuccessful" class="text-xs text-emerald-600 font-medium">✓ Saved</p>
                <span v-else />
                <AppButton type="submit" :loading="stripeForm.processing">Save Stripe settings</AppButton>
            </div>
        </form>

    </SettingsLayout>
</template>
