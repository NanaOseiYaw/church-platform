<script setup lang="ts">
/**
 * Onboarding Welcome screen
 *
 * Shown immediately after OnboardChurch action completes and the new
 * church admin is authenticated. Acts as a celebration / orientation
 * moment before the first dashboard visit.
 */

import { ref, computed, onMounted } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import {
    PartyPopper, ArrowRight, Users, CalendarDays,
    Megaphone, CheckSquare, Building2,
} from 'lucide-vue-next'
import OnboardingLayout from '@/Layouts/OnboardingLayout.vue'
import { useChurch } from '@/composables/useChurch'

const { church } = useChurch()
const page       = usePage()

// The auth user was just created — available via Inertia shared auth prop
const adminName = (page.props.auth as any)?.user?.name?.split(' ')[0] ?? 'there'

// ── Starter actions (quick-start checklist) ────────────────────────────────────
const quickstarts = computed(() => [
    {
        icon:  Users,
        label: 'Invite team members',
        desc:  'Add staff, coordinators and volunteers',
        href:  route('dashboard.members.index'),
        color: 'bg-brand-50 text-brand-600',
    },
    {
        icon:  Building2,
        label: 'Set up departments',
        desc:  'We created 4 starter departments for you',
        href:  route('dashboard.departments.index'),
        color: 'bg-brand-50 text-brand-600',
    },
    {
        icon:  CalendarDays,
        label: 'Create your first event',
        desc:  'Services, meetings, outreach programs',
        href:  route('dashboard.events.create'),
        color: 'bg-emerald-50 text-emerald-600',
    },
    {
        icon:  Megaphone,
        label: 'Post an announcement',
        desc:  'Keep your church community informed',
        href:  route('dashboard.announcements.create'),
        color: 'bg-amber-50 text-amber-600',
    },
])

// ── Confetti burst effect ──────────────────────────────────────────────────────
const showConfetti = ref(false)
onMounted(() => {
    setTimeout(() => { showConfetti.value = true }, 100)
})
</script>

<template>
    <Head title="Welcome!" />

    <OnboardingLayout title="Welcome">

        <!-- Celebration header -->
        <div class="text-center mb-8">
            <!-- Animated success icon -->
            <div
                :class="[
                    'w-16 h-16 gradient-brand rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-md transition-all duration-700',
                    showConfetti ? 'scale-100 opacity-100' : 'scale-75 opacity-0',
                ]"
            >
                <PartyPopper class="w-8 h-8 text-white" />
            </div>

            <h1
                :class="[
                    'text-2xl font-bold text-neutral-900 transition-all duration-500 delay-150',
                    showConfetti ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0',
                ]"
            >
                Welcome, {{ adminName }}! 🎉
            </h1>
            <p
                :class="[
                    'text-sm text-neutral-500 mt-1.5 transition-all duration-500 delay-200',
                    showConfetti ? 'translate-y-0 opacity-100' : 'translate-y-2 opacity-0',
                ]"
            >
                <strong class="text-neutral-700">{{ church.name }}</strong> is live.
                Your workspace is ready to go.
            </p>
        </div>

        <!-- Quick-start checklist -->
        <div
            :class="[
                'space-y-2.5 transition-all duration-500 delay-300',
                showConfetti ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
            ]"
        >
            <p class="text-xs font-semibold uppercase tracking-widest text-neutral-400 mb-3">
                Get started in minutes
            </p>

            <Link
                v-for="item in quickstarts"
                :key="item.href"
                :href="item.href"
                class="flex items-center gap-3.5 p-3.5 bg-white border border-neutral-100 rounded-2xl hover:border-neutral-200 hover:shadow-sm transition-all group"
            >
                <div :class="['w-9 h-9 rounded-xl flex items-center justify-center shrink-0', item.color]">
                    <component :is="item.icon" class="w-4.5 h-4.5" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-neutral-900">{{ item.label }}</p>
                    <p class="text-xs text-neutral-500 truncate">{{ item.desc }}</p>
                </div>
                <ArrowRight class="w-4 h-4 text-neutral-300 group-hover:text-neutral-500 group-hover:translate-x-0.5 transition-all shrink-0" />
            </Link>
        </div>

        <!-- CTA to dashboard -->
        <div
            :class="[
                'mt-6 transition-all duration-500 delay-500',
                showConfetti ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
            ]"
        >
            <Link
                href="/dashboard"
                class="flex items-center justify-center gap-2 w-full py-3 rounded-xl text-sm font-semibold text-white gradient-brand hover:opacity-90 transition-opacity shadow-sm"
            >
                Go to dashboard
                <ArrowRight class="w-4 h-4" />
            </Link>
            <p class="text-xs text-neutral-400 text-center mt-3">
                You can always change your church settings from the dashboard.
            </p>
        </div>

    </OnboardingLayout>
</template>
