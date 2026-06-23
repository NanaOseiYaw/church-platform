<script setup lang="ts" generic="T extends Record<string, any>">
defineProps<{
    rows: T[]
    loading?: boolean
    skeletonRows?: number
}>()
</script>

<template>
    <div class="bg-white border border-neutral-100 rounded-2xl overflow-hidden">
        <!-- Table header row -->
        <div v-if="$slots.header" class="flex items-center justify-between px-5 py-4 border-b border-neutral-50">
            <slot name="header" />
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-50">
                        <slot name="columns" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-50">
                    <!-- Loading skeleton -->
                    <template v-if="loading">
                        <tr v-for="i in (skeletonRows ?? 5)" :key="i" class="animate-pulse">
                            <td colspan="99" class="px-5 py-3.5">
                                <div class="h-4 bg-neutral-100 rounded w-full" />
                            </td>
                        </tr>
                    </template>

                    <!-- Data rows -->
                    <template v-else-if="rows.length">
                        <tr v-for="(row, i) in rows" :key="i"
                            class="hover:bg-neutral-50/60 transition-colors group">
                            <slot name="row" :row="row" :index="i" />
                        </tr>
                    </template>

                    <!-- Empty -->
                    <tr v-else>
                        <td colspan="99" class="py-14 text-center text-sm text-neutral-400">
                            <slot name="empty">No records found.</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Footer / pagination -->
        <div v-if="$slots.footer" class="px-5 py-3.5 border-t border-neutral-50">
            <slot name="footer" />
        </div>
    </div>
</template>
