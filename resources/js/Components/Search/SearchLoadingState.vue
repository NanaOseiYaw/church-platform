<script setup lang="ts">
// Skeleton rows that mimic the real result layout while the API is in-flight.
// Count keeps it proportional without being too tall.
const SKELETON_ROWS = 4
</script>

<template>
    <div class="px-2 py-3 space-y-1 animate-pulse" aria-hidden="true">
        <!-- Fake group label -->
        <div class="h-2.5 w-16 bg-neutral-100 rounded-full mx-2 mb-3" />

        <div
            v-for="i in SKELETON_ROWS"
            :key="i"
            class="flex items-center gap-3 px-3 py-2.5"
        >
            <!-- Icon chip -->
            <div class="w-8 h-8 rounded-lg bg-neutral-100 shrink-0" />
            <!-- Text lines -->
            <div class="flex-1 min-w-0 space-y-1.5">
                <div :class="['h-2.5 bg-neutral-100 rounded-full', i % 2 === 0 ? 'w-2/5' : 'w-3/5']" />
                <div :class="['h-2 bg-neutral-100 rounded-full', i % 3 === 0 ? 'w-1/3' : 'w-1/2']" />
            </div>
            <!-- Meta -->
            <div class="h-2 w-12 bg-neutral-100 rounded-full shrink-0" />
        </div>
    </div>
</template>
