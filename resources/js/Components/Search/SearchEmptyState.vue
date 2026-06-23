<script setup lang="ts">
import { SearchX } from 'lucide-vue-next'

defineProps<{ query: string }>()
</script>

<template>
    <div class="flex flex-col items-center justify-center py-10 px-4 text-center">
        <div class="w-10 h-10 rounded-xl bg-neutral-100 flex items-center justify-center mb-3">
            <SearchX class="w-5 h-5 text-neutral-400" />
        </div>
        <p class="text-sm font-medium text-neutral-700">No results for "{{ query }}"</p>
        <p class="text-xs text-neutral-400 mt-1">Try a different keyword or check your spelling.</p>
    </div>
</template>
