<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { PlusCircle, LogIn, PowerOff, Power } from 'lucide-vue-next'

interface Church {
    id:                number
    name:              string
    slug:              string
    users_count:       number
    subscription_plan: string
    is_active:         boolean
    created_at:        string
}

interface PaginationLink {
    url:    string | null
    label:  string
    active: boolean
}

defineProps<{
    churches: {
        data:  Church[]
        links: PaginationLink[]
        meta:  { current_page: number; last_page: number; total: number }
    }
}>()
</script>

<template>
    <SuperAdminLayout title="Churches">

        <!-- ── Page header ─────────────────────────────────────────────────── -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">All Churches</h1>
                <p class="text-sm text-neutral-500 mt-0.5">
                    {{ churches.meta.total }} church{{ churches.meta.total !== 1 ? 'es' : '' }} registered
                </p>
            </div>
            <Link
                :href="route('super-admin.churches.create')"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
            >
                <PlusCircle class="w-4 h-4" />
                Create Church
            </Link>
        </div>

        <!-- ── Table ──────────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-100 bg-neutral-50">
                        <th scope="col" class="text-left px-4 py-3 font-medium text-neutral-500">Church</th>
                        <th scope="col" class="text-left px-4 py-3 font-medium text-neutral-500">Members</th>
                        <th scope="col" class="text-left px-4 py-3 font-medium text-neutral-500">Plan</th>
                        <th scope="col" class="text-left px-4 py-3 font-medium text-neutral-500">Status</th>
                        <th scope="col" class="text-left px-4 py-3 font-medium text-neutral-500">Created</th>
                        <th scope="col" class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-50">
                    <tr
                        v-for="church in churches.data"
                        :key="church.id"
                        class="hover:bg-neutral-50 transition-colors"
                    >
                        <!-- Name + slug -->
                        <td class="px-4 py-3">
                            <p class="font-medium text-neutral-900">{{ church.name }}</p>
                            <p class="text-xs text-neutral-400 mt-0.5">{{ church.slug }}</p>
                        </td>

                        <!-- Members -->
                        <td class="px-4 py-3 text-neutral-600">{{ church.users_count }}</td>

                        <!-- Plan -->
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-700 capitalize">
                                {{ church.subscription_plan }}
                            </span>
                        </td>

                        <!-- Status badge -->
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                    church.is_active
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-rose-50 text-rose-700',
                                ]"
                            >
                                {{ church.is_active ? 'Active' : 'Suspended' }}
                            </span>
                        </td>

                        <!-- Created -->
                        <td class="px-4 py-3 text-neutral-400 text-xs">
                            {{ new Date(church.created_at).toLocaleDateString('en-US') }}
                        </td>

                        <!-- Actions -->
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <!-- Impersonate -->
                                <Link
                                    :href="route('super-admin.churches.impersonate', church.id)"
                                    method="post"
                                    as="button"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
                                >
                                    <LogIn class="w-3.5 h-3.5" />
                                    Switch to
                                </Link>

                                <!-- Suspend / Reactivate -->
                                <Link
                                    :href="route('super-admin.churches.toggle-active', church.id)"
                                    method="patch"
                                    as="button"
                                    :class="[
                                        'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors',
                                        church.is_active
                                            ? 'bg-rose-50 text-rose-700 hover:bg-rose-100'
                                            : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100',
                                    ]"
                                >
                                    <PowerOff v-if="church.is_active" class="w-3.5 h-3.5" />
                                    <Power v-else class="w-3.5 h-3.5" />
                                    {{ church.is_active ? 'Suspend' : 'Reactivate' }}
                                </Link>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr v-if="churches.data.length === 0">
                        <td colspan="6" class="px-4 py-12 text-center text-neutral-400 text-sm">
                            No churches found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ── Pagination ──────────────────────────────────────────────────── -->
        <div v-if="churches.meta.last_page > 1" class="mt-4 flex items-center justify-center gap-1">
            <template v-for="link in churches.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-3 py-1.5 rounded-lg text-sm transition-colors',
                        link.active
                            ? 'bg-indigo-600 text-white font-medium'
                            : 'text-neutral-500 hover:bg-neutral-100',
                    ]"
                    v-html="link.label"
                />
                <span
                    v-else
                    class="px-3 py-1.5 text-sm text-neutral-300"
                    v-html="link.label"
                />
            </template>
        </div>

    </SuperAdminLayout>
</template>
