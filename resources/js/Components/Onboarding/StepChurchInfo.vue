<script setup lang="ts">
import AppInput from '@/Components/UI/AppInput.vue'

const props = defineProps<{
    form: {
        church_name:    string
        church_tagline: string
        timezone:       string
        denomination:   string
        country:        string
    }
    errors:        Record<string, string>
    timezones:     { value: string; label: string }[]
    denominations: string[]
    countries:     { value: string; label: string }[]
}>()
</script>

<template>
    <div class="space-y-5">
        <!-- Church name -->
        <AppInput
            id="church_name"
            :model-value="form.church_name"
            label="Church name"
            placeholder="Grace Community Church"
            required
            :error="errors.church_name"
            @update:model-value="form.church_name = $event"
        />

        <!-- Tagline -->
        <AppInput
            id="church_tagline"
            :model-value="form.church_tagline"
            label="Tagline"
            placeholder="A Place to Belong (optional)"
            :error="errors.church_tagline"
            @update:model-value="form.church_tagline = $event"
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Denomination -->
            <div>
                <label for="denomination" class="block text-sm font-medium text-neutral-700 mb-1.5">
                    Denomination
                    <span class="text-neutral-400 font-normal">(optional)</span>
                </label>
                <select
                    id="denomination"
                    :value="form.denomination"
                    class="w-full h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    @change="form.denomination = ($event.target as HTMLSelectElement).value"
                >
                    <option value="">Select denomination…</option>
                    <option v-for="d in denominations" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>

            <!-- Country -->
            <div>
                <label for="country" class="block text-sm font-medium text-neutral-700 mb-1.5">
                    Country
                    <span class="text-neutral-400 font-normal">(optional)</span>
                </label>
                <select
                    id="country"
                    :value="form.country"
                    class="w-full h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                    @change="form.country = ($event.target as HTMLSelectElement).value"
                >
                    <option value="">Select country…</option>
                    <option v-for="c in countries" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <p v-if="errors.country" class="text-xs text-rose-500 mt-1.5">{{ errors.country }}</p>
            </div>
        </div>

        <!-- Timezone -->
        <div>
            <label for="timezone" class="block text-sm font-medium text-neutral-700 mb-1.5">
                Church timezone
            </label>
            <select
                id="timezone"
                :value="form.timezone"
                class="w-full h-10 rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-900 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent transition"
                @change="form.timezone = ($event.target as HTMLSelectElement).value"
            >
                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
            </select>
            <p class="text-xs text-neutral-400 mt-1.5">
                Used for scheduling events and attendance sessions.
            </p>
        </div>
    </div>
</template>
