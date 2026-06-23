import type { DepartmentRole, DepartmentVisibility } from '@/types'

// ── Role helpers ───────────────────────────────────────────────────────────────

export function roleLabel(role: DepartmentRole | string): string {
    const map: Record<string, string> = {
        coordinator:           'Coordinator',
        assistant_coordinator: 'Asst. Coordinator',
        member:                'Member',
    }
    return map[role] ?? role
}

export function roleBadgeClass(role: DepartmentRole | string): string {
    const map: Record<string, string> = {
        coordinator:           'bg-amber-100 text-amber-800',
        assistant_coordinator: 'bg-blue-100 text-blue-700',
        member:                'bg-neutral-100 text-neutral-600',
    }
    return map[role] ?? 'bg-neutral-100 text-neutral-500'
}

// ── Visibility helpers ─────────────────────────────────────────────────────────

export function visibilityLabel(v: DepartmentVisibility | string): string {
    const map: Record<string, string> = {
        public:       'Public',
        members_only: 'Members only',
        private:      'Private',
    }
    return map[v] ?? v
}

export function visibilityIcon(v: DepartmentVisibility | string): string {
    const map: Record<string, string> = {
        public:       '🌐',
        members_only: '👥',
        private:      '🔒',
    }
    return map[v] ?? '🌐'
}

export function visibilityBadgeClass(v: DepartmentVisibility | string): string {
    const map: Record<string, string> = {
        public:       'bg-emerald-50 text-emerald-700',
        members_only: 'bg-blue-50 text-blue-700',
        private:      'bg-neutral-100 text-neutral-600',
    }
    return map[v] ?? 'bg-neutral-100 text-neutral-500'
}

// ── Color swatch ───────────────────────────────────────────────────────────────

export function deptColor(color: string | null | undefined): string {
    return color ?? '#1e5aa8'
}
