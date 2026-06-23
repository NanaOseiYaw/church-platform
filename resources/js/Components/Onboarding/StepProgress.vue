<script setup lang="ts">
import { computed } from 'vue'
import { Check } from 'lucide-vue-next'

const props = defineProps<{
    currentStep: number
    steps: string[]
}>()

function stepState(i: number): 'done' | 'active' | 'upcoming' {
    if (i + 1 < props.currentStep) return 'done'
    if (i + 1 === props.currentStep) return 'active'
    return 'upcoming'
}
</script>

<template>
    <nav aria-label="Onboarding steps" class="flex items-center gap-0 mb-8">
        <template v-for="(label, i) in steps" :key="label">
            <!-- Step node -->
            <div class="flex flex-col items-center gap-1.5 shrink-0">
                <!-- Circle -->
                <div
                    :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center font-semibold text-xs transition-all duration-300',
                        stepState(i) === 'done'     && 'bg-brand-600 text-white',
                        stepState(i) === 'active'   && 'bg-brand-600 text-white ring-4 ring-brand-100',
                        stepState(i) === 'upcoming' && 'bg-neutral-100 text-neutral-400',
                    ]"
                >
                    <Check v-if="stepState(i) === 'done'" class="w-4 h-4" />
                    <span v-else>{{ i + 1 }}</span>
                </div>
                <!-- Label -->
                <span
                    :class="[
                        'text-[11px] font-medium whitespace-nowrap transition-colors duration-200 hidden sm:block',
                        stepState(i) === 'upcoming' ? 'text-neutral-400' : 'text-neutral-700',
                    ]"
                >
                    {{ label }}
                </span>
            </div>

            <!-- Connector line (between steps) -->
            <div
                v-if="i < steps.length - 1"
                class="flex-1 mx-2 mb-5"
            >
                <div
                    :class="[
                        'h-px transition-colors duration-300',
                        i + 1 < currentStep ? 'bg-brand-400' : 'bg-neutral-200',
                    ]"
                />
            </div>
        </template>
    </nav>
</template>
