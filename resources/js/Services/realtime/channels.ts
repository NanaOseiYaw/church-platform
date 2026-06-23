/**
 * Channel name helpers.
 *
 * Centralises the channel naming convention so renaming a channel requires
 * a single change here — not a grep across all composables.
 *
 * Echo adds 'private-' automatically for private() subscriptions.
 */

export const Channels = {
    /** Per-user private channel: live notifications and personal events. */
    user: (userId: number): string => `user.${userId}`,

    /** Per-church private channel: tasks, announcements, dashboard updates. */
    church: (churchId: number): string => `church.${churchId}`,

    /** Per-department private channel: department-scoped events (future). */
    department: (departmentId: number): string => `department.${departmentId}`,

    /** Per-attendance-session private channel: live check-in updates. */
    attendance: (sessionId: string | number): string => `attendance.${sessionId}`,
} as const

/**
 * Broadcast event name constants.
 * The backend broadcastAs() method must match these exactly.
 */
export const BroadcastEvents = {
    NOTIFICATION_NEW:       'notification.new',
    TASK_ACTIVITY:          'task.activity',
    ATTENDANCE_UPDATED:     'attendance.updated',
    ANNOUNCEMENT_PUBLISHED: 'announcement.published',
} as const

// ── Payload types ─────────────────────────────────────────────────────────────

export interface NotificationPayload {
    unread_count: number
    notification: {
        id:          string | null
        type:        string
        title:       string
        body:        string
        action_url:  string | null
        actor:       { name: string; avatar: string | null } | null
        created_at:  string
    }
}

export interface TaskActivityPayload {
    task_id:     number
    title:       string
    action:      'assigned' | 'updated' | 'completed'
    actor:       string
    new_status:  string
    assigned_to: number | null
}

export interface AttendanceUpdatedPayload {
    session_id:         string
    attendances_count:  number
    present_count:      number
    absent_count:       number
    late_count:         number
    excused_count:      number
    attendance_rate:    number
}

export interface AnnouncementPublishedPayload {
    announcement_id: number
    title:           string
    is_pinned:       boolean
    published_at:    string
}
