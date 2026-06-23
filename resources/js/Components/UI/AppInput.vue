<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    modelValue:   string | number
    label?:       string
    placeholder?: string
    type?:        string
    error?:       string
    hint?:        string
    required?:    boolean
    disabled?:    boolean
    readonly?:    boolean
    size?:        'sm' | 'md'
    id?:          string
}>(), {
    type: 'text',
    size: 'md',
})

const emit  = defineEmits<{ 'update:modelValue': [value: string] }>()

const inputClasses = computed(() => [
    'w-full border rounded-lg text-neutral-900 placeholder:text-neutral-400',
    'transition-colors duration-150 focus:outline-none',
    // Size
    props.size === 'sm'
        ? 'px-3 py-1.5 text-xs'
        : 'px-3.5 py-2.5 text-sm',
    // Border & focus ring
    props.error
        ? 'border-rose-400 focus:border-rose-400 focus:ring-3 focus:ring-rose-500/10'
        : 'border-neutral-200 focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10',
    // Disabled / readonly visual
    (props.disabled || props.readonly)
        ? 'bg-neutral-50 opacity-60 cursor-not-allowed'
        : 'bg-white',
])
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <!-- Label -->
        <label v-if="label" :for="id" class="text-sm font-medium text-neutral-700">
            {{ label }}<span v-if="required" class="text-rose-500 ml-0.5">*</span>
        </label>

        <!-- Input wrapper (position:relative for icon slots) -->
        <div class="relative">
            <!-- Leading icon -->
            <div
                v-if="$slots.leading"
                :class="[
                    'absolute inset-y-0 left-0 flex items-center pointer-events-none text-neutral-400',
                    size === 'sm' ? 'pl-2.5' : 'pl-3.5',
                ]"
            >
                <slot name="leading" />
            </div>

            <input
                :id="id"
                :type="type"
                :value="modelValue"
                :placeholder="placeholder"
                :required="required"
                :disabled="disabled"
                :readonly="readonly"
                :class="[
                    inputClasses,
                    $slots.leading  ? (size === 'sm' ? 'pl-8'  : 'pl-10') : '',
                    $slots.trailing ? (size === 'sm' ? 'pr-8'  : 'pr-10') : '',
                ]"
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />

            <!-- Trailing icon -->
            <div
                v-if="$slots.trailing"
                :class="[
                    'absolute inset-y-0 right-0 flex items-center pointer-events-none text-neutral-400',
                    size === 'sm' ? 'pr-2.5' : 'pr-3.5',
                ]"
            >
                <slot name="trailing" />
            </div>
        </div>

        <!-- Error or hint (error takes priority) -->
        <p v-if="error" class="text-xs text-rose-500">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-neutral-400">{{ hint }}</p>
    </div>
</template>
