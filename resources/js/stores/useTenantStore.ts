import { defineStore } from 'pinia'
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { ChurchBranding } from '@/types'

export const useTenantStore = defineStore('tenant', () => {
    const page = usePage()

    const church = computed<ChurchBranding>(
        () => (page.props.church as ChurchBranding) ?? {
            name: '',
            tagline: '',
            logo: null,
            primaryColor: '#6366f1',
            secondaryColor: null,
            address: '',
            phone: '',
            email: '',
            socials: {},
            seo: {},
        },
    )

    const churchName     = computed(() => church.value.name)
    const churchInitials = computed(() => {
        return church.value.name
            .split(' ')
            .slice(0, 2)
            .map(w => w[0])
            .join('')
            .toUpperCase()
    })

    const brandColor     = computed(() => church.value.primaryColor ?? '#6366f1')
    const secondaryColor = computed(() => church.value.secondaryColor ?? null)

    return { church, churchName, churchInitials, brandColor, secondaryColor }
})
