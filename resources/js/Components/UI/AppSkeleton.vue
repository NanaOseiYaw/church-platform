<script setup lang="ts">
/**
 * AppSkeleton — animated loading placeholder.
 *
 * Types:
 *   line   — a text line (default). Use `lines` prop for a paragraph block.
 *   circle — circular placeholder (avatars, icons). Size via class: class="w-10 h-10"
 *   rect   — rectangular placeholder (images, thumbnails). Size via class: class="h-32 w-full"
 *
 * Usage:
 *   <AppSkeleton />                               single line
 *   <AppSkeleton :lines="3" />                    3-line paragraph (last line 3/4 width)
 *   <AppSkeleton type="circle" class="w-10 h-10" />
 *   <AppSkeleton type="rect" class="h-32 w-full rounded-lg" />
 *
 * Note: component uses defineOptions({ inheritAttrs: false }) so that external
 * class/style bindings are applied to the rendered element via v-bind="$attrs".
 */
withDefaults(defineProps<{
    type?:  'line' | 'circle' | 'rect'
    /** Number of stacked text lines. Only applies when type === 'line'. */
    lines?: number
}>(), {
    type:  'line',
    lines: 1,
})

defineOptions({ inheritAttrs: false })
</script>

<template>
    <!-- Circle (avatar/icon placeholder) -->
    <div
        v-if="type === 'circle'"
        v-bind="$attrs"
        class="bg-neutral-100 animate-pulse rounded-full"
    />

    <!-- Rect (image/card body placeholder) -->
    <div
        v-else-if="type === 'rect'"
        v-bind="$attrs"
        class="bg-neutral-100 animate-pulse rounded-lg"
    />

    <!-- Line(s) — default -->
    <div v-else v-bind="$attrs" class="space-y-2.5">
        <div
            v-for="i in lines"
            :key="i"
            class="bg-neutral-100 animate-pulse rounded h-4"
            :class="i === lines && lines > 1 ? 'w-3/4' : 'w-full'"
        />
    </div>
</template>
