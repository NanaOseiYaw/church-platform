<script setup lang="ts">
import { ref } from 'vue'
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { ChevronDown, ChevronRight, Users } from 'lucide-vue-next'

interface Role {
    id: number
    name: string
    display_name: string
    permissions: string[]
    users_count: number
}

const props = defineProps<{ roles: Role[] }>()

const expanded = ref<Set<number>>(new Set())

function toggle(id: number) {
    if (expanded.value.has(id)) expanded.value.delete(id)
    else expanded.value.add(id)
}

function groupPermissions(permissions: string[]): Record<string, string[]> {
    const groups: Record<string, string[]> = {}
    permissions.forEach(p => {
        const [module] = p.split('.')
        if (!groups[module]) groups[module] = []
        groups[module].push(p)
    })
    return groups
}
</script>

<template>
    <SettingsLayout section="roles">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Roles &amp; Permissions</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                View the built-in roles and their permission sets.
            </p>
        </div>

        <!-- Roles list -->
        <div class="max-w-2xl space-y-3">
            <div
                v-for="role in roles"
                :key="role.id"
                class="bg-white border border-neutral-100 rounded-xl overflow-hidden"
            >
                <!-- Role header -->
                <button
                    type="button"
                    class="w-full flex items-center gap-3 px-5 py-4 text-left hover:bg-neutral-50 transition-colors"
                    @click="toggle(role.id)"
                >
                    <div class="w-7 h-7 rounded-lg bg-brand-50 flex items-center justify-center shrink-0">
                        <Users class="w-3.5 h-3.5 text-brand-600" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-neutral-900 capitalize">{{ role.display_name }}</p>
                        <p class="text-xs text-neutral-400 mt-0.5">
                            {{ role.permissions.length }} permissions ·
                            {{ role.users_count }} {{ role.users_count === 1 ? 'member' : 'members' }}
                        </p>
                    </div>
                    <component
                        :is="expanded.has(role.id) ? ChevronDown : ChevronRight"
                        class="w-4 h-4 text-neutral-400 shrink-0"
                    />
                </button>

                <!-- Expanded permission groups -->
                <div v-if="expanded.has(role.id)" class="border-t border-neutral-100 px-5 py-4">
                    <div v-if="!role.permissions.length" class="text-xs text-neutral-400">
                        No specific permissions assigned to this role.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="(perms, module) in groupPermissions(role.permissions)"
                            :key="module"
                        >
                            <p class="text-[10px] font-bold uppercase tracking-widest text-neutral-400 mb-1.5 capitalize">
                                {{ module }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="perm in perms"
                                    :key="perm"
                                    class="inline-flex items-center px-2 py-0.5 rounded-md bg-neutral-50 border border-neutral-200 text-[11px] text-neutral-600 font-mono"
                                >
                                    {{ perm.split('.').slice(1).join('.') || perm }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!roles.length" class="bg-white border border-neutral-100 rounded-xl p-10 text-center">
                <Users class="w-8 h-8 text-neutral-200 mx-auto mb-3" />
                <p class="text-sm text-neutral-400">No roles found.</p>
            </div>

            <!-- Info note -->
            <div class="bg-neutral-50 border border-neutral-100 rounded-xl px-5 py-4">
                <p class="text-xs text-neutral-500">
                    <span class="font-semibold text-neutral-700">Role permissions</span> — Each role grants a fixed set of
                    capabilities. Assign roles to members from the Members section.
                </p>
            </div>
        </div>

    </SettingsLayout>
</template>
