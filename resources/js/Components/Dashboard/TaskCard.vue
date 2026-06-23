<script setup lang="ts">
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import TaskStatusBadge from '@/Components/Dashboard/TaskStatusBadge.vue'
import { Calendar, User, Building2, MoreVertical, CheckCircle2 } from 'lucide-vue-next'
import type { Task } from '@/types'

const props = defineProps<{
    task:      Task
    canEdit?:  boolean
    canDelete?:boolean
}>()

// ── Display helpers — pre-formatted by TaskResource, no new Date() needed ──────

// is_overdue is computed server-side in TaskResource.
const isOverdue = computed(() => props.task.is_overdue ?? false)

const deptStyle = computed(() => {
    const color = props.task.department?.color
    if (! color) return {}
    return { backgroundColor: color + '30', color }
})

function markDone() {
    router.patch(`/dashboard/tasks/${props.task.id}/status`, { status: 'completed' }, { preserveScroll: true })
}

function deleteTask() {
    if (confirm(`Delete "${props.task.title}"?`)) {
        router.delete(`/dashboard/tasks/${props.task.id}`, { preserveScroll: true })
    }
}
</script>

<template>
    <article
        class="group flex gap-3 p-4 bg-white border border-neutral-100 rounded-xl hover:border-neutral-200 hover:shadow-sm transition-all"
        :class="{ 'opacity-60': task.status === 'cancelled' || task.status === 'completed' }"
    >
        <!-- Quick complete checkbox -->
        <button
            v-if="task.status !== 'completed' && task.status !== 'cancelled'"
            type="button"
            class="mt-0.5 w-5 h-5 shrink-0 rounded-full border-2 border-neutral-300 hover:border-emerald-400 transition-colors"
            title="Mark complete"
            @click="markDone"
        />
        <CheckCircle2
            v-else
            class="mt-0.5 w-5 h-5 shrink-0"
            :class="task.status === 'completed' ? 'text-emerald-500' : 'text-neutral-300'"
        />

        <!-- Main content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-2">
                <Link
                    :href="`/dashboard/tasks/${task.id}`"
                    class="text-sm font-medium text-neutral-900 hover:text-brand-600 transition-colors line-clamp-2 leading-snug"
                    :class="{ 'line-through text-neutral-400': task.status === 'cancelled' || task.status === 'completed' }"
                >
                    {{ task.title }}
                </Link>

                <!-- Actions dropdown (visible on hover) -->
                <div class="flex items-center gap-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    <Link
                        v-if="canEdit"
                        :href="`/dashboard/tasks/${task.id}/edit`"
                        class="text-xs text-neutral-500 hover:text-neutral-800 px-1.5 py-0.5 rounded hover:bg-neutral-100 transition-colors"
                    >
                        Edit
                    </Link>
                    <button
                        v-if="canDelete"
                        type="button"
                        class="text-xs text-rose-400 hover:text-rose-600 px-1.5 py-0.5 rounded hover:bg-rose-50 transition-colors"
                        @click="deleteTask"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <!-- Meta row -->
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <TaskStatusBadge :status="task.status" />
                <TaskStatusBadge :priority="task.priority" />

                <!-- Due date -->
                <span
                    v-if="task.due_at"
                    class="inline-flex items-center gap-1 text-xs"
                    :class="isOverdue ? 'text-rose-500 font-medium' : 'text-neutral-400'"
                >
                    <Calendar class="w-3 h-3" />
                    {{ isOverdue ? 'Due ' : '' }}{{ task.due_at_short }}
                </span>

                <!-- Assignee -->
                <span v-if="task.assignee" class="inline-flex items-center gap-1 text-xs text-neutral-400">
                    <User class="w-3 h-3" />
                    {{ task.assignee.name }}
                </span>

                <!-- Department -->
                <span
                    v-if="task.department"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium"
                    :style="deptStyle"
                >
                    <Building2 class="w-3 h-3" />
                    {{ task.department.name }}
                </span>
            </div>
        </div>
    </article>
</template>
