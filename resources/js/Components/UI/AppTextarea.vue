<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    modelValue:   string
    label?:       string
    placeholder?: string
    rows?:        number
    error?:       string
    hint?:        string
    required?:    boolean
    disabled?:    boolean
    maxlength?:   number
    id?:          string
}>(), {
    rows: 4,
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const charCount   = computed(() => props.modelValue?.length ?? 0)
const isOverLimit = computed(() => props.maxlength !== undefined && charCount.value > props.maxlength)
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <!-- Label row: label on left, character counter on right -->
        <div class="flex items-center justify-between gap-2">
            <label v-if="label" :for="id" class="text-sm font-medium text-neutral-700">
                {{ label }}<span v-if="required" class="text-rose-500 ml-0.5">*</span>
            </label>
            <span
                v-if="maxlength !== undefined"
                :class="[
                    'text-xs tabular-nums shrink-0',
                    isOverLimit ? 'text-rose-500 font-medium' : 'text-neutral-400',
                ]"
            >
                {{ charCount }}/{{ maxlength }}
            </span>
        </div>

        <textarea
            :id="id"
            :value="modelValue"
            :placeholder="placeholder"
            :rows="rows"
            :required="required"
            :disabled="disabled"
            class="w-full px-3.5 py-2.5 text-sm border border-neutral-200 rounded-lg text-neutral-900 placeholder:text-neutral-400 transition-colors duration-150 focus:outline-none focus:border-brand-400 focus:ring-3 focus:ring-brand-500/10 resize-none"
            :class="{
                'border-rose-400 focus:ring-rose-500/10 focus:border-rose-400': error || isOverLimit,
                'bg-neutral-50 opacity-60 cursor-not-allowed': disabled,
                'bg-white': !disabled,
            }"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />

        <p v-if="error" class="text-xs text-rose-500">{{ error }}</p>
        <p v-else-if="hint" class="text-xs text-neutral-400">{{ hint }}</p>
    </div>
</template>
