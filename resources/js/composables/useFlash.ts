import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { FlashMessages } from '@/types'

export function useFlash() {
    const page = usePage()
    const flash = computed<FlashMessages>(() => page.props.flash as FlashMessages)
    return { flash }
}
