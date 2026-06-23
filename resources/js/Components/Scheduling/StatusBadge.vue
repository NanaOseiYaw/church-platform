<script setup lang="ts">
const props = defineProps<{
    status: string
}>()

const colors: Record<string, string> = {
    // Plan statuses
    draft:     'bg-gray-100 text-gray-600',
    published: 'bg-green-100 text-green-700',
    archived:  'bg-slate-100 text-slate-500',
    // Assignment statuses
    pending:   'bg-yellow-100 text-yellow-700',
    confirmed: 'bg-green-100 text-green-700',
    declined:  'bg-red-100 text-red-600',
}

const labels: Record<string, string> = {
    draft:     'Draft',
    published: 'Published',
    archived:  'Archived',
    pending:   'Pending',
    confirmed: 'Confirmed',
    declined:  'Declined',
}
</script>

<template>
    <span
        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', colors[status] ?? 'bg-gray-100 text-gray-600']"
    >
        {{ labels[status] ?? status }}
    </span>
</template>
