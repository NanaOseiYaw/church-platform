import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { ChurchBranding } from '@/types'

export function useChurch() {
    const page = usePage()
    const church = computed<ChurchBranding>(() => page.props.church as ChurchBranding)
    return { church }
}
