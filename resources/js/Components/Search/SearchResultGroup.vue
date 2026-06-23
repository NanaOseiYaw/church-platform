<script setup lang="ts">
import type { SearchResultGroup, SearchResultItem } from '@/types'
import SearchResultItemVue from './SearchResultItem.vue'

const props = defineProps<{
    group:         SearchResultGroup
    selectedIndex: number          // global flat index of the currently-highlighted result
    groupOffset:   number          // how many results come before this group in the flat list
}>()

defineEmits<{
    select: [result: SearchResultItem]
    hover:  [flatIndex: number]
}>()
</script>

<template>
    <div>
        <!-- Group header -->
        <p class="px-3 pt-3 pb-1.5 text-[10px] font-semibold uppercase tracking-widest text-neutral-400 select-none">
            {{ group.label }}
        </p>

        <!-- Results -->
        <div class="space-y-0.5 px-1">
            <SearchResultItemVue
                v-for="(result, i) in group.results"
                :key="`${result.type}-${result.id}`"
                :result="result"
                :selected="selectedIndex === groupOffset + i"
                @click="$emit('select', result)"
                @mouseenter="$emit('hover', groupOffset + i)"
            />
        </div>
    </div>
</template>
