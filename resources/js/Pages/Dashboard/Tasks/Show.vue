<script setup lang="ts">
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import DashboardLayout from '@/Layouts/DashboardLayout.vue'
import PageHeader from '@/Components/Dashboard/PageHeader.vue'
import TaskStatusBadge from '@/Components/Dashboard/TaskStatusBadge.vue'
import AppButton from '@/Components/UI/AppButton.vue'
import AttachmentList from '@/Components/Media/AttachmentList.vue'
import {
    ArrowLeft, Edit2, Trash2, Calendar, User, Building2, Flag, CheckCircle2,
    Clock, MessageSquare, ChevronDown
} from 'lucide-vue-next'
import type { Task, TaskStatus } from '@/types'

// ── Props ──────────────────────────────────────────────────────────────────────

const props = defineProps<{
    task:       Task
    canEdit:    boolean
    canDelete:  boolean
    canStatus:  boolean
    canComment: boolean
    canUpload:  boolean
}>()

// ── Display helpers ─────────────────────────────────────────────────────────────
// Dates are pre-formatted by TaskResource — no new Date() needed for task fields.
// timeAgo() is kept only for comment timestamps (always ISO, always non-null).

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime()
    const mins = Math.floor(diff / 60000)
    if (mins < 1)    return 'just now'
    if (mins < 60)   return `${mins}m ago`
    const hrs = Math.floor(mins / 60)
    if (hrs < 24)    return `${hrs}h ago`
    const days = Math.floor(hrs / 24)
    return `${days}d ago`
}

// ── Status update ──────────────────────────────────────────────────────────────

const statusOpen = ref(false)

const statusOptions: { value: TaskStatus; label: string }[] = [
    { value: 'pending',     label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed',   label: 'Completed' },
    { value: 'cancelled',   label: 'Cancelled' },
]

function setStatus(status: TaskStatus) {
    statusOpen.value = false
    router.patch(`/dashboard/tasks/${props.task.id}/status`, { status }, { preserveScroll: true })
}

// ── Delete ─────────────────────────────────────────────────────────────────────

function deleteTask() {
    if (confirm('Delete this task? This cannot be undone.')) {
        router.delete(`/dashboard/tasks/${props.task.id}`)
    }
}

// ── Comments ───────────────────────────────────────────────────────────────────

const commentForm = useForm({ body: '' })

function submitComment() {
    commentForm.post(`/dashboard/tasks/${props.task.id}/comments`, {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    })
}

function deleteComment(commentId: number) {
    if (confirm('Delete this comment?')) {
        router.delete(`/dashboard/tasks/${props.task.id}/comments/${commentId}`, {
            preserveScroll: true,
        })
    }
}
</script>

<template>
    <DashboardLayout
        :title="task.title"
        :breadcrumbs="[
            { label: 'Dashboard', href: '/dashboard' },
            { label: 'Tasks',     href: '/dashboard/tasks' },
            { label: task.title },
        ]"
    >
        <PageHeader :title="task.title" description="">
            <template #actions>
                <div class="flex items-center gap-2">
                    <AppButton href="/dashboard/tasks" variant="outline" size="sm">
                        <ArrowLeft class="w-4 h-4" /> Back
                    </AppButton>
                    <AppButton
                        v-if="canEdit"
                        :href="`/dashboard/tasks/${task.id}/edit`"
                        variant="outline"
                        size="sm"
                    >
                        <Edit2 class="w-4 h-4" /> Edit
                    </AppButton>
                    <AppButton
                        v-if="canDelete"
                        variant="danger"
                        size="sm"
                        @click="deleteTask"
                    >
                        <Trash2 class="w-4 h-4" /> Delete
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Main column ────────────────────────────────────────────── -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Description -->
                <div class="bg-white border border-neutral-100 rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-neutral-900 mb-3">Description</h3>
                    <p v-if="task.description" class="text-sm text-neutral-700 whitespace-pre-line leading-relaxed">
                        {{ task.description }}
                    </p>
                    <p v-else class="text-sm text-neutral-400 italic">No description provided.</p>
                </div>

                <!-- Comments -->
                <div class="bg-white border border-neutral-100 rounded-xl p-6">
                    <h3 class="text-sm font-semibold text-neutral-900 mb-4 flex items-center gap-2">
                        <MessageSquare class="w-4 h-4 text-neutral-400" />
                        Comments
                        <span class="ml-auto text-xs text-neutral-400 font-normal">
                            {{ (task.comments ?? []).length }} comment{{ (task.comments ?? []).length !== 1 ? 's' : '' }}
                        </span>
                    </h3>

                    <!-- Comment list -->
                    <div v-if="(task.comments ?? []).length" class="space-y-4 mb-6">
                        <div
                            v-for="comment in task.comments"
                            :key="comment.id"
                            class="flex gap-3 group"
                        >
                            <!-- Avatar -->
                            <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold shrink-0 uppercase">
                                {{ comment.author?.name?.[0] ?? '?' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-baseline gap-2">
                                    <span class="text-xs font-medium text-neutral-900">{{ comment.author?.name ?? 'Unknown' }}</span>
                                    <span class="text-xs text-neutral-400">{{ timeAgo(comment.created_at) }}</span>
                                    <button
                                        type="button"
                                        class="ml-auto text-xs text-rose-400 hover:text-rose-600 opacity-0 group-hover:opacity-100 transition-opacity"
                                        @click="deleteComment(comment.id)"
                                    >
                                        Delete
                                    </button>
                                </div>
                                <p class="mt-0.5 text-sm text-neutral-700 whitespace-pre-line">{{ comment.body }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-neutral-400 italic mb-6">No comments yet. Be the first to comment.</p>

                    <!-- Add comment -->
                    <form v-if="canComment" @submit.prevent="submitComment" class="flex gap-2">
                        <textarea
                            v-model="commentForm.body"
                            rows="2"
                            placeholder="Add a comment…"
                            class="flex-1 px-3 py-2 text-sm border border-neutral-200 rounded-lg resize-none focus:outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-500/10"
                            :class="{ 'border-rose-400': commentForm.errors.body }"
                        />
                        <AppButton type="submit" size="sm" :loading="commentForm.processing">
                            Post
                        </AppButton>
                    </form>
                    <p v-if="commentForm.errors.body" class="mt-1 text-xs text-rose-500">{{ commentForm.errors.body }}</p>
                </div>

                <!-- Attachments -->
                <AttachmentList
                    :files="task.files ?? []"
                    attachable-type="task"
                    :attachable-id="task.id"
                    :can-upload="canUpload"
                />
            </div>

            <!-- ── Sidebar ─────────────────────────────────────────────────── -->
            <div class="space-y-4">

                <!-- Status card -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5">
                    <h3 class="text-xs font-semibold text-neutral-400 uppercase tracking-wide mb-3">Status</h3>
                    <div class="flex items-center justify-between">
                        <TaskStatusBadge :status="task.status" />

                        <!-- Status changer -->
                        <div v-if="canStatus" class="relative">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-xs text-neutral-500 hover:text-neutral-800 px-2 py-1 rounded hover:bg-neutral-100 transition-colors"
                                @click="statusOpen = !statusOpen"
                            >
                                Change <ChevronDown class="w-3 h-3" />
                            </button>
                            <div
                                v-if="statusOpen"
                                class="absolute right-0 top-full mt-1 z-10 bg-white border border-neutral-200 rounded-lg shadow-lg py-1 min-w-[140px]"
                            >
                                <button
                                    v-for="opt in statusOptions"
                                    :key="opt.value"
                                    type="button"
                                    class="w-full text-left px-3 py-1.5 text-sm hover:bg-neutral-50 transition-colors"
                                    :class="{ 'font-medium text-brand-600': opt.value === task.status }"
                                    @click="setStatus(opt.value)"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details card -->
                <div class="bg-white border border-neutral-100 rounded-xl p-5 space-y-4">
                    <h3 class="text-xs font-semibold text-neutral-400 uppercase tracking-wide">Details</h3>

                    <dl class="space-y-3 text-sm">
                        <div class="flex items-start gap-2.5">
                            <Flag class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-xs text-neutral-400">Priority</dt>
                                <dd class="mt-0.5">
                                    <TaskStatusBadge :priority="task.priority" />
                                </dd>
                            </div>
                        </div>

                        <div v-if="task.due_at" class="flex items-start gap-2.5">
                            <Calendar class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-xs text-neutral-400">Due date</dt>
                                <dd class="mt-0.5 text-neutral-800 font-medium">{{ task.due_at_formatted ?? '—' }}</dd>
                            </div>
                        </div>

                        <div v-if="task.completed_at" class="flex items-start gap-2.5">
                            <CheckCircle2 class="w-4 h-4 text-emerald-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-xs text-neutral-400">Completed</dt>
                                <dd class="mt-0.5 text-neutral-800">{{ task.completed_at_formatted ?? '—' }}</dd>
                            </div>
                        </div>

                        <div v-if="task.assignee" class="flex items-start gap-2.5">
                            <User class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-xs text-neutral-400">Assigned to</dt>
                                <dd class="mt-0.5 text-neutral-800 font-medium">{{ task.assignee.name }}</dd>
                            </div>
                        </div>

                        <div v-if="task.assigner" class="flex items-start gap-2.5">
                            <User class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-xs text-neutral-400">Assigned by</dt>
                                <dd class="mt-0.5 text-neutral-800">{{ task.assigner.name }}</dd>
                            </div>
                        </div>

                        <div v-if="task.department" class="flex items-start gap-2.5">
                            <Building2 class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-xs text-neutral-400">Department</dt>
                                <dd class="mt-0.5 text-neutral-800">{{ task.department.name }}</dd>
                            </div>
                        </div>

                        <div v-if="task.created_at" class="flex items-start gap-2.5">
                            <Clock class="w-4 h-4 text-neutral-400 mt-0.5 shrink-0" />
                            <div>
                                <dt class="text-xs text-neutral-400">Created</dt>
                                <dd class="mt-0.5 text-neutral-800">{{ task.created_at_formatted ?? '—' }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
