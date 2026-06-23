<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { CheckCircle2, Users, BarChart3, Shield, Zap } from 'lucide-vue-next'

defineProps<{ title?: string }>()

const features = [
    { icon: Users,        text: 'Manage members, departments & attendance' },
    { icon: BarChart3,    text: 'Real-time analytics and ministry reports' },
    { icon: Zap,          text: 'Live notifications and team collaboration' },
    { icon: Shield,       text: 'Role-based access control, built for security' },
    { icon: CheckCircle2, text: 'Task management, sermons & event planning' },
]
</script>

<template>
    <Head>
        <title>{{ title ?? 'Set up your workspace' }}</title>
    </Head>

    <div class="min-h-screen flex">

        <!-- ── Left branding panel (hidden on mobile) ──────────────────────── -->
        <div class="hidden lg:flex lg:w-[44%] xl:w-[40%] gradient-brand flex-col justify-between p-10 relative overflow-hidden shrink-0">

            <!-- Decorative blobs -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute -top-32 -left-32 w-96 h-96 bg-white/5 rounded-full blur-3xl" />
                <div class="absolute -bottom-24 -right-24 w-80 h-80 bg-black/10 rounded-full blur-3xl" />
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-white/3 rounded-full blur-3xl" />
            </div>

            <!-- Top: Logo + wordmark -->
            <div class="relative z-10">
                <Link href="/" class="inline-flex items-center">
                    <div class="w-10 h-10 bg-white/15 backdrop-blur-sm border border-white/20 rounded-xl flex items-center justify-center">
                        <span class="text-white font-bold text-sm">CP</span>
                    </div>
                </Link>
            </div>

            <!-- Mid: Headline + features -->
            <div class="relative z-10 space-y-8">
                <div>
                    <h2 class="text-3xl font-bold text-white leading-tight mb-3">
                        Everything your<br>church needs,<br>in one place.
                    </h2>
                    <p class="text-white/70 text-sm leading-relaxed">
                        Set up your workspace in minutes. No technical expertise required.
                    </p>
                </div>

                <ul class="space-y-3.5">
                    <li
                        v-for="f in features"
                        :key="f.text"
                        class="flex items-center gap-3"
                    >
                        <div class="w-7 h-7 bg-white/15 rounded-lg flex items-center justify-center shrink-0">
                            <component :is="f.icon" class="w-3.5 h-3.5 text-white" />
                        </div>
                        <span class="text-white/85 text-sm">{{ f.text }}</span>
                    </li>
                </ul>
            </div>

            <!-- Bottom: Social proof -->
            <div class="relative z-10">
                <p class="text-white/50 text-xs">
                    Free to start &nbsp;·&nbsp; No credit card required &nbsp;·&nbsp; Cancel anytime
                </p>
            </div>
        </div>

        <!-- ── Right form panel ──────────────────────────────────────────────── -->
        <div class="flex-1 bg-white flex flex-col min-h-screen">
            <!-- Mobile logo bar -->
            <div class="lg:hidden flex items-center px-6 py-4 border-b border-neutral-100">
                <div class="w-8 h-8 gradient-brand rounded-lg flex items-center justify-center">
                    <span class="text-white text-xs font-bold">CP</span>
                </div>
            </div>

            <!-- Scrollable content -->
            <div class="flex-1 overflow-y-auto px-6 py-8 md:px-12 lg:px-16 xl:px-20">
                <div class="max-w-md mx-auto w-full lg:max-w-lg">
                    <slot />
                </div>
            </div>

            <!-- Bottom hint -->
            <div class="shrink-0 px-6 py-4 text-center">
                <p class="text-xs text-neutral-400">
                    Already have a workspace?
                    <Link href="/login" class="text-brand-600 hover:text-brand-700 font-medium transition-colors">Sign in</Link>
                </p>
            </div>
        </div>
    </div>
</template>
