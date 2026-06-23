<script setup lang="ts">
/**
 * GlobalSearchModal — command-palette style universal search.
 *
 * Keyboard shortcuts:
 *   Ctrl/Cmd + K  — open / close
 *   Escape        — close
 *   ↑ / ↓         — navigate results
 *   Enter         — open selected result
 *
 * Architecture:
 *   - Reads/writes useSearchStore
 *   - Never calls axios directly
 *   - Self-contained: mount once in DashboardLayout, works everywhere
 */
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { Search, X, Command, CornerDownLeft } from 'lucide-vue-next'
import { useSearchStore } from '@/stores/useSearchStore'
import type { SearchResultItem } from '@/types'
import SearchResultGroupVue  from './SearchResultGroup.vue'
import SearchEmptyState      from './SearchEmptyState.vue'
import SearchLoadingState    from './SearchLoadingState.vue'

const store = useSearchStore()

// ── Input ref for focus management ───────────────────────────────────────────
const inputRef    = ref<HTMLInputElement | null>(null)
const resultsRef  = ref<HTMLDivElement | null>(null)
const selectedIdx = ref(-1)        // -1 = no selection

// ── Computed flat list for keyboard nav ───────────────────────────────────────
const flat = computed<SearchResultItem[]>(() => store.flatResults)

/** Running offset for each group so SearchResultGroup knows its slice. */
const groupOffsets = computed<number[]>(() => {
    const offsets: number[] = []
    let running = 0
    for (const g of store.groups) {
        offsets.push(running)
        running += g.results.length
    }
    return offsets
})

// ── Open/close side effects ───────────────────────────────────────────────────
watch(() => store.isOpen, async (open) => {
    if (open) {
        selectedIdx.value = -1
        await nextTick()
        inputRef.value?.focus()
    }
})

// Reset selection when results change
watch(() => store.groups, () => { selectedIdx.value = -1 })

// ── Keyboard shortcut (Ctrl/Cmd + K) ─────────────────────────────────────────
function onGlobalKeydown(e: KeyboardEvent) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault()
        store.toggle()
    }
}

onMounted(()  => window.addEventListener('keydown', onGlobalKeydown))
onUnmounted(() => window.removeEventListener('keydown', onGlobalKeydown))

// ── In-modal keyboard navigation ─────────────────────────────────────────────
function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
        store.close()
        return
    }

    const total = flat.value.length
    if (total === 0) return

    if (e.key === 'ArrowDown') {
        e.preventDefault()
        selectedIdx.value = selectedIdx.value < total - 1
            ? selectedIdx.value + 1
            : 0               // wrap to top
        scrollSelectedIntoView()
        return
    }

    if (e.key === 'ArrowUp') {
        e.preventDefault()
        selectedIdx.value = selectedIdx.value > 0
            ? selectedIdx.value - 1
            : total - 1       // wrap to bottom
        scrollSelectedIntoView()
        return
    }

    if (e.key === 'Enter' && selectedIdx.value >= 0) {
        e.preventDefault()
        const result = flat.value[selectedIdx.value]
        if (result) navigateTo(result)
    }
}

function scrollSelectedIntoView() {
    nextTick(() => {
        const el = resultsRef.value?.querySelector('[data-selected="true"]')
        el?.scrollIntoView({ block: 'nearest' })
    })
}

// ── Navigation ────────────────────────────────────────────────────────────────
function navigateTo(result: SearchResultItem) {
    store.close()
    router.visit(result.url)
}

// ── Input handler ─────────────────────────────────────────────────────────────
function onInput(e: Event) {
    store.search((e.target as HTMLInputElement).value)
}

function clearQuery() {
    store.search('')
    nextTick(() => inputRef.value?.focus())
}

// ── Backdrop click → close ────────────────────────────────────────────────────
function onBackdropClick(e: MouseEvent) {
    if ((e.target as HTMLElement).dataset.backdrop) {
        store.close()
    }
}

// ── Show idle hint (no query entered yet) ─────────────────────────────────────
const showHint   = computed(() => store.isIdle && !store.loading)
const showEmpty  = computed(() =>
    !store.loading && !store.isIdle && !store.hasResults && !store.error
)
const showGroups = computed(() => store.hasResults && !store.loading)
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="store.isOpen"
                class="fixed inset-0 z-50 flex items-start justify-center pt-[10vh] px-4"
                data-backdrop="true"
                @click="onBackdropClick"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" data-backdrop="true" />

                <!-- Panel -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95 -translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="store.isOpen"
                        class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl shadow-black/20 border border-neutral-200 overflow-hidden flex flex-col"
                        style="max-height: min(600px, 80vh)"
                        @keydown="onKeydown"
                    >

                        <!-- ── Search input bar ─────────────────────────────────────── -->
                        <div class="flex items-center gap-2.5 px-4 py-3.5 border-b border-neutral-100">
                            <Search class="w-4 h-4 text-neutral-400 shrink-0" />

                            <input
                                ref="inputRef"
                                :value="store.query"
                                type="text"
                                placeholder="Search members, events, tasks…"
                                class="flex-1 bg-transparent text-sm text-neutral-900 placeholder-neutral-400 outline-none"
                                autocomplete="off"
                                spellcheck="false"
                                @input="onInput"
                            />

                            <!-- Loading spinner -->
                            <div
                                v-if="store.loading"
                                class="w-4 h-4 border-2 border-neutral-200 border-t-brand-500 rounded-full animate-spin shrink-0"
                            />

                            <!-- Clear button -->
                            <button
                                v-else-if="store.query"
                                type="button"
                                class="w-5 h-5 rounded flex items-center justify-center hover:bg-neutral-100 transition-colors shrink-0"
                                @click="clearQuery"
                            >
                                <X class="w-3 h-3 text-neutral-400" />
                            </button>

                            <!-- Shortcut hint (only when empty) -->
                            <div
                                v-else
                                class="flex items-center gap-0.5 shrink-0"
                            >
                                <kbd class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-neutral-100 text-neutral-400 text-[10px] rounded-md font-medium">
                                    <Command class="w-2.5 h-2.5" />K
                                </kbd>
                            </div>
                        </div>

                        <!-- ── Results area ────────────────────────────────────────── -->
                        <div
                            ref="resultsRef"
                            class="flex-1 overflow-y-auto overscroll-contain"
                        >
                            <!-- Loading skeleton -->
                            <SearchLoadingState v-if="store.loading" />

                            <!-- Error -->
                            <div v-else-if="store.error" class="flex items-center justify-center py-10 px-4">
                                <p class="text-sm text-rose-500 text-center">{{ store.error }}</p>
                            </div>

                            <!-- Idle hint: no query yet -->
                            <div v-else-if="showHint" class="flex flex-col items-center justify-center py-10 text-center px-4">
                                <div class="w-9 h-9 rounded-xl bg-brand-50 flex items-center justify-center mb-3">
                                    <Search class="w-4.5 h-4.5 w-[18px] h-[18px] text-brand-500" />
                                </div>
                                <p class="text-sm font-medium text-neutral-700">Search across your church</p>
                                <p class="text-xs text-neutral-400 mt-1">
                                    Members, events, tasks, announcements&hellip;
                                </p>
                            </div>

                            <!-- No results -->
                            <SearchEmptyState v-else-if="showEmpty" :query="store.query" />

                            <!-- Results grouped -->
                            <div v-else-if="showGroups" class="py-1">
                                <SearchResultGroupVue
                                    v-for="(group, gi) in store.groups"
                                    :key="group.type"
                                    :group="group"
                                    :selected-index="selectedIdx"
                                    :group-offset="groupOffsets[gi]"
                                    @select="navigateTo"
                                    @hover="(i) => { selectedIdx = i }"
                                />
                            </div>
                        </div>

                        <!-- ── Footer hint bar ────────────────────────────────────── -->
                        <div
                            v-if="showGroups"
                            class="flex items-center justify-between gap-4 px-4 py-2 border-t border-neutral-100 bg-neutral-50/60"
                        >
                            <div class="flex items-center gap-3 text-[10px] text-neutral-400">
                                <span class="flex items-center gap-1">
                                    <kbd class="px-1.5 py-0.5 bg-white border border-neutral-200 rounded text-[10px] font-medium">↑↓</kbd>
                                    navigate
                                </span>
                                <span class="flex items-center gap-1">
                                    <kbd class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-white border border-neutral-200 rounded text-[10px] font-medium">
                                        <CornerDownLeft class="w-2.5 h-2.5" />
                                    </kbd>
                                    open
                                </span>
                                <span class="flex items-center gap-1">
                                    <kbd class="px-1.5 py-0.5 bg-white border border-neutral-200 rounded text-[10px] font-medium">Esc</kbd>
                                    close
                                </span>
                            </div>
                            <span class="text-[10px] text-neutral-400 tabular-nums">
                                {{ store.totalCount }} result{{ store.totalCount !== 1 ? 's' : '' }}
                            </span>
                        </div>

                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
