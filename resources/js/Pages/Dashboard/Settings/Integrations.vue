<script setup lang="ts">
import SettingsLayout from '@/Layouts/SettingsLayout.vue'
import { Plug, Youtube, Instagram, Mail, Calendar, BarChart2, MessageCircle } from 'lucide-vue-next'

const integrations = [
    {
        name:        'YouTube',
        description: 'Sync sermons automatically from your YouTube channel.',
        icon:        Youtube,
        status:      'available',
        href:        '/dashboard/sermons/channel',
    },
    {
        name:        'Google Calendar',
        description: 'Sync events to and from Google Calendar.',
        icon:        Calendar,
        status:      'soon',
        href:        null,
    },
    {
        name:        'Instagram',
        description: 'Pull posts and stories onto your public website.',
        icon:        Instagram,
        status:      'soon',
        href:        null,
    },
    {
        name:        'Mailchimp',
        description: 'Sync your member list with Mailchimp audiences.',
        icon:        Mail,
        status:      'soon',
        href:        null,
    },
    {
        name:        'Google Analytics',
        description: 'Track public website visitors and engagement.',
        icon:        BarChart2,
        status:      'available',
        href:        '/dashboard/settings/seo',
    },
    {
        name:        'WhatsApp',
        description: 'Send announcements via WhatsApp Business.',
        icon:        MessageCircle,
        status:      'soon',
        href:        null,
    },
]
</script>

<template>
    <SettingsLayout section="integrations">

        <div class="mb-7">
            <h1 class="text-xl font-semibold text-neutral-900 tracking-tight">Integrations</h1>
            <p class="text-sm text-neutral-500 mt-0.5">
                Connect your church platform to external services and tools.
            </p>
        </div>

        <div class="max-w-2xl grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div
                v-for="item in integrations"
                :key="item.name"
                class="bg-white border border-neutral-100 rounded-xl p-5 flex flex-col gap-3"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="w-9 h-9 rounded-lg bg-neutral-50 border border-neutral-100 flex items-center justify-center shrink-0">
                        <component :is="item.icon" class="w-4.5 h-4.5 text-neutral-500" />
                    </div>
                    <span
                        :class="[
                            'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border',
                            item.status === 'available'
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                : 'bg-amber-50 text-amber-600 border-amber-200',
                        ]"
                    >
                        {{ item.status === 'available' ? 'Available' : 'Coming soon' }}
                    </span>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-neutral-900">{{ item.name }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5 leading-relaxed">{{ item.description }}</p>
                </div>
                <a
                    v-if="item.href"
                    :href="item.href"
                    class="text-xs text-brand-600 hover:text-brand-700 font-medium"
                >
                    Configure →
                </a>
                <span v-else class="text-xs text-neutral-300">–</span>
            </div>
        </div>

    </SettingsLayout>
</template>
