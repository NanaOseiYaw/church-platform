<!-- resources/js/Components/Scheduling/AssignDrawer.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { X, Search, UserCheck } from 'lucide-vue-next'
import type { VolunteerAssignment } from '@/types'

interface Member { id: number; name: string; avatar: string | null }

const props = defineProps<{
    open: boolean
    planPositionId: number
    members: Member[]
    existingAssignments: VolunteerAssignment[]
}>()

const emit = defineEmits<{
    close: []
    assign: [planPositionId: number, userId: number]
}>()

const search = ref('')

const filteredMembers = computed(() => {
    const q = search.value.toLowerCase()
    return props.members.filter(m => m.name.toLowerCase().includes(q))
})

function isAssigned(memberId: number): boolean {
    return props.existingAssignments.some(
        a => a.user_id === memberId && a.status !== 'declined'
    )
}

function initials(name: string): string {
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}
</script>

<template>
    <!-- Backdrop -->
    <Transition name="fade">
        <div
            v-if="open"
            class="fixed inset-0 z-40 bg-black/30"
            @click="emit('close')"
        />
    </Transition>

    <!-- Drawer panel -->
    <Transition name="slide-right">
        <div
            v-if="open"
            class="fixed inset-y-0 right-0 z-50 flex w-80 flex-col bg-white shadow-2xl"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Assign Volunteer</h2>
                <button @click="emit('close')" class="rounded p-1 text-gray-400 hover:text-gray-600">
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Search -->
            <div class="border-b border-gray-100 px-4 py-2">
                <div class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-1.5">
                    <Search class="h-3.5 w-3.5 text-gray-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search members…"
                        class="flex-1 bg-transparent text-sm outline-none placeholder:text-gray-400"
                    />
                </div>
            </div>

            <!-- Member list -->
            <ul class="flex-1 overflow-y-auto py-1">
                <li
                    v-for="member in filteredMembers"
                    :key="member.id"
                    class="flex items-center gap-3 px-4 py-2.5"
                    :class="isAssigned(member.id) ? 'opacity-50' : 'cursor-pointer hover:bg-gray-50'"
                    @click="!isAssigned(member.id) && emit('assign', planPositionId, member.id)"
                >
                    <div class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                        <img v-if="member.avatar" :src="member.avatar" :alt="member.name" class="h-8 w-8 rounded-full object-cover" />
                        <span v-else>{{ initials(member.name) }}</span>
                    </div>
                    <span class="flex-1 text-sm text-gray-800">{{ member.name }}</span>
                    <UserCheck v-if="isAssigned(member.id)" class="h-4 w-4 text-green-500" />
                </li>
                <li v-if="filteredMembers.length === 0" class="px-4 py-6 text-center text-sm text-gray-400">
                    No members found
                </li>
            </ul>
        </div>
    </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.2s ease; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
</style>
