<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

interface PaginatorLink {
    url:    string | null
    label:  string
    active: boolean
}

const props = withDefaults(defineProps<{
    links:        PaginatorLink[]
    currentPage:  number
    lastPage:     number
    total:        number
    perPage:      number
    /** Show "X–Y of Z" count. Default true. */
    showCount?:   boolean
}>(), {
    showCount: true,
})

const from = (props.currentPage - 1) * props.perPage + 1
const to   = Math.min(props.currentPage * props.perPage, props.total)
</script>

<template>
    <div v-if="lastPage > 1" class="flex items-center justify-between pt-2 mt-2">
        <!-- Count -->
        <p v-if="showCount !== false" class="text-xs text-neutral-500">
            {{ from }}–{{ to }} of {{ total }}
        </p>
        <div v-else />

        <!-- Links -->
        <div class="flex items-center gap-1">
            <template v-for="link in links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    :class="[
                        'px-3 py-1.5 text-xs rounded-lg transition-colors',
                        link.active
                            ? 'bg-brand-600 text-white font-medium'
                            : 'text-neutral-600 hover:bg-neutral-100',
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="px-3 py-1.5 text-xs text-neutral-300"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
