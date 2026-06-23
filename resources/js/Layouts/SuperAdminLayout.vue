<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { useAuthStore } from '@/stores/useAuthStore'
import { ShieldCheck } from 'lucide-vue-next'

withDefaults(defineProps<{
    title?: string
}>(), {})

const auth = useAuthStore()

function logout() {
    router.post('/logout')
}
</script>

<template>
    <Head>
        <title v-if="title">{{ title }} · Platform Admin</title>
        <title v-else>Platform Admin</title>
    </Head>

    <div class="min-h-screen bg-neutral-100 flex flex-col">

        <!-- ── Header ────────────────────────────────────────────────────────── -->
        <header class="bg-slate-900 text-white shrink-0">
            <div class="max-w-7xl mx-auto px-4 lg:px-6 h-14 flex items-center justify-between">

                <!-- Brand -->
                <Link href="/super-admin" class="flex items-center gap-2.5 group">
                    <div class="w-7 h-7 rounded-lg bg-indigo-500 flex items-center justify-center shrink-0">
                        <ShieldCheck class="w-4 h-4 text-white" />
                    </div>
                    <span class="text-sm font-semibold text-white">Platform Admin</span>
                </Link>

                <!-- Nav + user -->
                <div class="flex items-center gap-4">
                    <Link
                        href="/super-admin"
                        class="text-sm text-slate-300 hover:text-white transition-colors"
                        :class="{ 'text-white font-medium': $page.url.startsWith('/super-admin') }"
                    >
                        Churches
                    </Link>

                    <div class="flex items-center gap-3 pl-4 border-l border-slate-700">
                        <span class="text-xs text-slate-400">{{ auth.user?.name }}</span>
                        <button
                            class="text-xs text-slate-300 hover:text-white transition-colors"
                            @click="logout"
                        >
                            Sign out
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- ── Page content ──────────────────────────────────────────────────── -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 lg:px-6 py-8">
            <slot />
        </main>

    </div>
</template>
