import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { AuthUser } from '@/types'

export const useAuthStore = defineStore('auth', () => {
    // Source of truth is always the Inertia shared prop — Pinia just wraps it
    // with convenience getters so components don't import usePage everywhere.
    const page = usePage()

    const user = computed<AuthUser | null>(
        () => page.props.auth?.user ?? null,
    )

    // ── Live notification count ───────────────────────────────────────────────
    // Starts null (falls back to Inertia prop) and gets updated in real time by
    // useRealtimeNotifications without needing a full page reload.
    const _liveNotificationsCount = ref<number | null>(null)

    const notificationsCount = computed<number>(() =>
        _liveNotificationsCount.value ?? (page.props.auth?.notifications_count ?? 0)
    )

    function setLiveNotificationsCount(count: number): void {
        _liveNotificationsCount.value = count
    }

    // Reset live count on page navigation so the Inertia prop takes over again
    // (the page reload will give us the fresh server-side count)
    function resetLiveNotificationsCount(): void {
        _liveNotificationsCount.value = null
    }

    const unreadAnnouncements = computed<number>(
        () => page.props.auth?.unread_announcements_count ?? 0,
    )

    /**
     * Count of overdue tasks assigned to the current user.
     *
     * Sourced from the shared Inertia prop populated by HandleInertiaRequests,
     * using the same "due_at < now() AND status NOT IN (completed, cancelled)"
     * definition as Task::scopeOverdue() and TaskResource::is_overdue.
     * This makes the sidebar badge consistent with the red indicators in TaskCard.
     */
    const overdueTasksCount = computed<number>(
        () => (page.props.auth as any)?.overdue_tasks_count ?? 0,
    )

    // ── Role helpers ────────────────────────────────────────────────────────────
    const isAuthenticated = computed(() => user.value !== null)
    const isSuperAdmin    = computed(() => user.value?.roles.includes('super_admin') ?? false)
    const isChurchAdmin   = computed(() => user.value?.roles.includes('church_admin') ?? false)
    const isCoordinator   = computed(() => user.value?.roles.includes('coordinator') ?? false)
    const isMember        = computed(() => user.value?.roles.includes('member') ?? false)
    const isAdmin         = computed(() => isSuperAdmin.value || isChurchAdmin.value)
    const isStaff         = computed(() => isAdmin.value || isCoordinator.value)

    const can = (permission: string): boolean =>
        user.value?.permissions.includes(permission) ?? false

    const hasRole = (role: string): boolean =>
        user.value?.roles.includes(role) ?? false

    const hasAnyRole = (...roles: string[]): boolean =>
        roles.some(r => user.value?.roles.includes(r) ?? false)

    // ── Avatar ──────────────────────────────────────────────────────────────────
    const avatarInitials = computed(() => {
        if (!user.value?.name) return '?'
        return user.value.name
            .split(' ')
            .slice(0, 2)
            .map(n => n[0])
            .join('')
            .toUpperCase()
    })

    return {
        user,
        notificationsCount,
        setLiveNotificationsCount,
        resetLiveNotificationsCount,
        unreadAnnouncements,
        overdueTasksCount,
        isAuthenticated,
        isSuperAdmin,
        isChurchAdmin,
        isCoordinator,
        isMember,
        isAdmin,
        isStaff,
        can,
        hasRole,
        hasAnyRole,
        avatarInitials,
    }
})
