<script setup lang="ts">
/**
 * AttendanceFilters
 *
 * Filter bar for the attendance index page.
 * Emits `change` with a partial filter object; the parent calls router.get.
 */
import { ref, watch } from 'vue'
import { Filter, X } from 'lucide-vue-next'
import type { Department } from '@/types'

const props = defineProps<{
    departments: Pick<Department, 'id' | 'name' | 'icon' | 'color'>[]
    filters: {
        type?: string
        status?: string
        department_id?: string
        from?: string
        to?: string
    }
}>()

const emit = defineEmits<{
    change: [filters: typeof props.filters]
}>()

const local = ref({ ...props.filters })

function apply() {
    emit('change', { ...local.value })
}

function reset() {
    local.value = {}
    emit('change', {})
}

// Sync if parent filters change (e.g. browser back)
watch(() => props.filters, v => { local.value = { ...v } })

const hasFilters = Object.values(props.filters).some(Boolean)
</script>

<template>
    <div class="bg-white border border-neutral-100 rounded-xl p-4">
        <div class="flex flex-wrap items-end gap-3">
            <!-- Type -->
            <div class="flex-1 min-w-32">
                <label class="block text-xs font-medium text-neutral-500 mb-1">Type</label>
                <select
                    v-model="local.type"
                    class="w-full text-sm border border-neutral-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    @change="apply"
                >
                    <option value="">All types</option>
                    <option value="service">Service</option>
                    <option value="meeting">Meeting</option>
                    <option value="rehearsal">Rehearsal</option>
                    <option value="outreach">Outreach</option>
                    <option value="volunteer">Volunteer</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <!-- Status -->
            <div class="flex-1 min-w-32">
                <label class="block text-xs font-medium text-neutral-500 mb-1">Status</label>
                <select
                    v-model="local.status"
                    class="w-full text-sm border border-neutral-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    @change="apply"
                >
                    <option value="">All statuses</option>
                    <option value="planned">Planned</option>
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <!-- Department -->
            <div class="flex-1 min-w-40">
                <label class="block text-xs font-medium text-neutral-500 mb-1">Department</label>
                <select
                    v-model="local.department_id"
                    class="w-full text-sm border border-neutral-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    @change="apply"
                >
                    <option value="">All departments</option>
                    <option
                        v-for="dept in departments"
                        :key="dept.id"
                        :value="String(dept.id)"
                    >{{ dept.name }}</option>
                </select>
            </div>

            <!-- Date from -->
            <div class="flex-1 min-w-36">
                <label class="block text-xs font-medium text-neutral-500 mb-1">From</label>
                <input
                    v-model="local.from"
                    type="date"
                    class="w-full text-sm border border-neutral-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    @change="apply"
                />
            </div>

            <!-- Date to -->
            <div class="flex-1 min-w-36">
                <label class="block text-xs font-medium text-neutral-500 mb-1">To</label>
                <input
                    v-model="local.to"
                    type="date"
                    class="w-full text-sm border border-neutral-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand-500"
                    @change="apply"
                />
            </div>

            <!-- Clear -->
            <button
                v-if="hasFilters"
                type="button"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-neutral-500 hover:text-neutral-700 hover:bg-neutral-50 border border-neutral-200 rounded-lg transition-colors"
                @click="reset"
            >
                <X class="w-3.5 h-3.5" />
                Clear
            </button>
        </div>
    </div>
</template>
