<script setup lang="ts">
// AppSelect supports two usage patterns:
//
// 1. Prop-based (preferred for dynamic lists):
//    <AppSelect v-model="val" :options="[{ value: 'a', label: 'A' }]" />
//
// 2. Slot-based (preferred for static option lists):
//    <AppSelect v-model="val">
//      <option value="a">A</option>
//      <option value="b">B</option>
//    </AppSelect>
//
// Both patterns may be combined — slot content renders first, then prop options.
defineProps<{
    modelValue:  string | number | null
    options?:    { value: string | number; label: string }[]
    label?:      string
    placeholder?: string
    error?:      string
    required?:   boolean
    id?:         string
    disabled?:   boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string | number] }>()
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label v-if="label" :for="id" class="text-sm font-medium text-neutral-700">
            {{ label }}<span v-if="required" class="text-rose-500 ml-0.5">*</span>
        </label>
        <div class="relative">
            <select
                :id="id"
                :value="modelValue ?? ''"
                :required="required"
                :disabled="disabled"
                class="w-full px-3.5 py-2.5 text-sm bg-white border border-neutral-200 rounded-lg text-neutral-900 appearance-none transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10 disabled:opacity-50 disabled:cursor-not-allowed pr-9"
                :class="{ 'border-rose-400': error }"
                @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
            >
                <!-- Placeholder option (disabled, shown when no value selected) -->
                <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>

                <!-- Slot-based options (static <option> children passed by the caller) -->
                <slot />

                <!-- Prop-based options (dynamic array passed via :options) -->
                <option
                    v-for="opt in (options ?? [])"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center">
                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>
        <p v-if="error" class="text-xs text-rose-500">{{ error }}</p>
    </div>
</template>
