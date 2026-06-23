import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'
import type { SearchResultGroup, SearchResultItem } from '@/types'

/**
 * Global search store.
 *
 * Controls modal visibility + executes the debounced search request.
 * Components interact with this store — they never call axios directly.
 */
export const useSearchStore = defineStore('search', () => {

    // ── State ────────────────────────────────────────────────────────────────────
    const isOpen  = ref(false)
    const query   = ref('')
    const groups  = ref<SearchResultGroup[]>([])
    const loading = ref(false)
    const error   = ref<string | null>(null)

    let debounceTimer: ReturnType<typeof setTimeout> | null = null

    // ── Derived ──────────────────────────────────────────────────────────────────

    /** All results flattened in display order — used for keyboard navigation. */
    const flatResults = computed<SearchResultItem[]>(() =>
        groups.value.flatMap(g => g.results)
    )

    const hasResults  = computed(() => groups.value.length > 0)
    const totalCount  = computed(() => flatResults.value.length)
    const isIdle      = computed(() => query.value.trim().length < 2)

    // ── Actions ──────────────────────────────────────────────────────────────────

    function open()   { isOpen.value = true }

    function close()  {
        isOpen.value = false
        // Clear state so the next open starts fresh
        query.value  = ''
        groups.value = []
        error.value  = null
        loading.value = false
        if (debounceTimer) {
            clearTimeout(debounceTimer)
            debounceTimer = null
        }
    }

    function toggle() { isOpen.value ? close() : open() }

    async function search(q: string): Promise<void> {
        query.value = q

        if (debounceTimer) clearTimeout(debounceTimer)

        if (q.trim().length < 2) {
            groups.value  = []
            loading.value = false
            error.value   = null
            return
        }

        // Show loading state immediately (before the debounce fires)
        loading.value = true

        debounceTimer = setTimeout(async () => {
            error.value = null

            try {
                const { data } = await axios.get<{ groups: SearchResultGroup[] }>(
                    '/dashboard/search',
                    { params: { q: q.trim() } }
                )
                groups.value = data.groups
            } catch {
                error.value  = 'Search failed. Please try again.'
                groups.value = []
            } finally {
                loading.value = false
            }
        }, 280)
    }

    return {
        isOpen,
        query,
        groups,
        loading,
        error,
        flatResults,
        hasResults,
        totalCount,
        isIdle,
        open,
        close,
        toggle,
        search,
    }
})
