// ── Shared Inertia props ───────────────────────────────────────────────────────

export interface ChurchSeo {
    meta_title?:           string
    meta_description?:     string
    google_analytics_id?:  string
    clarity_id?:           string
    robots?:               string
    favicon_url?:          string
    og_image?:             string | null
}

export interface FooterNavLink {
    label: string
    href:  string
}

export interface FooterNav {
    explore_links: FooterNavLink[]
    connect_links: FooterNavLink[]
}

export interface ChurchBranding {
    name: string
    tagline: string
    description?: string | null
    logo: string | null
    favicon?: string | null
    primaryColor: string
    secondaryColor?: string | null
    address: string
    phone: string
    email: string
    socials: {
        facebook?:  string | null
        instagram?: string | null
        youtube?:   string | null
        twitter?:   string | null
        tiktok?:    string | null
        linkedin?:  string | null
        spotify?:   string | null
    }
    seo: ChurchSeo
    footerNav: FooterNav
}

export interface AuthUser {
    id: number
    name: string
    email: string
    avatar: string | null
    church_id: number | null
    roles: string[]
    permissions: string[]
}

export interface FlashMessages {
    success: string | null
    error: string | null
}

export interface SharedProps {
    church: ChurchBranding
    auth: {
        user: AuthUser | null
        notifications_count: number
        unread_announcements_count: number
        /** Tasks assigned to the current user whose due_at is past (not completed/cancelled). */
        overdue_tasks_count: number
    }
    flash: FlashMessages
}

// ── Domain types ───────────────────────────────────────────────────────────────

export type EventStatus     = 'upcoming' | 'ongoing' | 'completed' | 'cancelled'
export type EventVisibility = 'public' | 'members_only' | 'department_only' | 'private'
export type ContentVisibility = 'public' | 'members_only' | 'department_only' | 'private'
export type RsvpStatus      = 'going' | 'maybe' | 'not_going'

export interface Event {
    id: number
    church_id?: number
    department_id: number | null
    created_by?: number
    title: string
    description: string | null
    location: string | null
    cover_image: string | null
    category: string | null
    // Dates — ISO 8601 strings (safe for new Date() when non-null)
    start_at: string
    end_at: string | null
    published_at?: string | null
    created_at?: string | null
    // Booleans
    all_day: boolean
    visibility: EventVisibility
    is_public: boolean
    is_recurring: boolean
    is_cancelled: boolean
    is_featured?: boolean
    rsvp_enabled: boolean
    capacity: number | null
    // Computed
    status: EventStatus
    going_count?: number
    maybe_count?: number
    // Relations
    creator?: { id: number; name: string; avatar: string | null }
    department?: { id: number; name: string; icon: string | null; color: string | null } | null
    // ── Pre-formatted fields from EventResource (no new Date() needed) ─────────
    start_month?: string           // "May"
    start_day?: number             // 31
    start_weekday?: string         // "Sat"
    start_at_formatted?: string    // "Sat, May 31, 2026"
    start_time_formatted?: string  // "1:00 PM"
    end_at_formatted?: string      // "Sat, May 31, 2026"
    end_time_formatted?: string    // "4:00 PM"
    time_range?: string            // "1:00 PM – 4:00 PM" | "All day"
    date_range?: string            // "Sat, May 31, 2026" | "May 31 – Jun 1, 2026"
    // Legacy aliases kept for public site / dashboard home widget
    date?: string
    time?: string
    image?: string | null
    // File attachments (present on show pages)
    files?: FileAttachment[]
}

/** Used on the public Sermons page (static data, legacy shape). */
export interface Sermon {
    id: number
    title: string
    speaker: string
    date: string
    series: string
    duration: string
    thumbnail: string | null
    description?: string
    preached_at?: string
}

/** Lightweight sermon for the public website. */
export interface PublicSermon {
    id: number
    title: string
    slug: string              // slug or fallback to id
    speaker: string | null
    description: string | null
    series: string | null
    series_id: number | null
    thumbnail: string | null
    embed_url: string | null
    audio_url: string | null
    duration: string | null
    duration_seconds: number | null
    is_featured: boolean
    provider: string
    provider_video_id: string | null
    preached_at: string | null
    preached_at_formatted: string | null
    preached_month: string | null
    preached_day: number | null
    preached_year: number | null
    sermon_series?: { id: number; title: string; slug: string | null } | null
}

/** Full sermon shape returned by SermonResource (dashboard pages). */
export interface DashboardSermon {
    id: number
    title: string
    slug: string | null
    speaker: string | null
    description: string | null
    series: string | null
    series_id: number | null
    // Provider
    provider: string
    provider_video_id: string | null
    // Media
    video_url: string | null
    audio_url: string | null
    embed_url: string | null
    thumbnail: string | null
    duration: string | null
    duration_seconds: number | null
    // Status
    is_public: boolean
    visibility: 'public' | 'members_only' | 'unlisted'
    is_featured: boolean
    // Dates — pre-formatted; never call new Date() on these
    preached_at: string | null
    preached_at_formatted: string | null
    preached_at_long: string | null
    preached_month: string | null
    preached_day: number | null
    preached_year: number | null
    synced_at: string | null
    synced_at_formatted: string | null
    created_at: string | null
    created_at_formatted: string | null
    uploader?: { id: number; name: string; avatar: string | null } | null
    channel_connection?: {
        id: number
        provider: string
        channel_title: string | null
        channel_id: string
    } | null
}

/** Sermon series / teaching series. */
export interface SermonSeries {
    id: number
    title: string
    slug: string | null
    description: string | null
    cover_image: string | null
    is_active: boolean
    sort_order: number
    started_at: string | null
    ended_at: string | null
    sermon_count?: number
}

/** Provider channel connection (YouTube, Vimeo, etc.). */
export interface ChannelConnection {
    id: number
    provider: string
    channel_id: string
    channel_title: string | null
    channel_thumbnail: string | null
    channel_description: string | null
    is_active: boolean
    subscriber_count: number | null
    video_count: number | null
    sync_frequency_hours: number
    last_synced_at: string | null
    last_synced_at_formatted: string | null
    next_sync_at: string | null
    next_sync_at_formatted: string | null
    created_at: string | null
    created_at_formatted: string | null
    channel_url: string | null
}

export type AnnouncementPriority = 'low' | 'medium' | 'high' | 'urgent'
export type AnnouncementStatus   = 'draft' | 'scheduled' | 'published' | 'expired'

export interface Announcement {
    id: number
    church_id: number
    department_id: number | null
    created_by: number
    title: string
    body: string
    /** Optional cover image or flyer: `cover_image` in the dashboard, `image` on public pages. */
    cover_image?: string | null
    image?: string | null
    category: string | null
    priority: AnnouncementPriority
    is_pinned: boolean
    is_church_wide: boolean
    visibility: ContentVisibility
    is_featured?: boolean
    // Dates — ISO 8601 strings
    published_at: string | null
    expires_at: string | null
    created_at: string | null
    // Computed
    status: AnnouncementStatus
    reads_count?: number
    // Relations
    creator?: { id: number; name: string; avatar: string | null }
    department?: { id: number; name: string; icon: string | null; color: string | null } | null
    // ── Pre-formatted fields from AnnouncementResource (no new Date() needed) ──
    published_at_formatted?: string  // "28 May 2026"
    published_at_long?: string       // "28 May 2026, 10:30 AM"
    expires_at_formatted?: string    // "30 Jun 2026"
    created_at_formatted?: string    // "28 May 2026"
    date_label?: string              // published_at_formatted ?? created_at_formatted
    // Public-site aliases
    excerpt?: string
    date?: string
    // File attachments (present on show pages)
    files?: FileAttachment[]
}

export interface Ministry {
    id: number
    name: string
    description: string
    icon: string
    leader: string
    color: string
}

export type DepartmentVisibility = 'public' | 'members_only' | 'private'
export type DepartmentRole      = 'coordinator' | 'assistant_coordinator' | 'member'

export interface DepartmentMember {
    id: number
    name: string
    avatar: string | null
    email: string
    pivot: {
        role: DepartmentRole
        joined_at: string | null
        joined_at_formatted: string | null  // pre-formatted by DepartmentController
    }
}

export interface Department {
    id: number
    church_id: number
    name: string
    slug: string
    description: string | null
    cover_image: string | null
    coordinator_id: number | null
    icon: string | null
    color: string | null
    is_active: boolean
    visibility: DepartmentVisibility
    settings: Record<string, unknown> | null
    created_by: number | null
    coordinator?: { id: number; name: string; avatar: string | null }
    members_count?: number
    announcements_count?: number
    events_count?: number
    tasks_count?: number
}

export type TaskStatus   = 'pending' | 'in_progress' | 'completed' | 'overdue' | 'cancelled'
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent'

export interface TaskComment {
    id: number
    task_id: number
    user_id: number
    body: string
    created_at: string
    author?: { id: number; name: string; avatar: string | null }
}

export interface Task {
    id: number
    church_id?: number
    department_id: number | null
    assigned_to: number | null
    assigned_by: number | null
    title: string
    description: string | null
    priority: TaskPriority
    status: TaskStatus
    // Dates — ISO 8601 strings
    due_at: string | null
    completed_at: string | null
    created_at?: string | null
    // Relations
    assignee?: { id: number; name: string; avatar: string | null } | null
    assigner?: { id: number; name: string } | null
    department?: { id: number; name: string; icon: string | null; color: string | null } | null
    comments?: TaskComment[]
    comments_count?: number
    // ── Pre-formatted fields from TaskResource (no new Date() needed) ──────────
    due_at_formatted?: string       // "Mon, 2 Jun 2026"
    due_at_short?: string           // "2 Jun 2026"
    completed_at_formatted?: string // "2 Jun 2026, 10:30 AM"
    created_at_formatted?: string   // "2 Jun 2026, 10:30 AM"
    is_overdue?: boolean            // computed server-side
    // File attachments (present on show pages)
    files?: FileAttachment[]
}

export interface TeamMember {
    name: string
    role: string
    bio: string
    image: string | null
}

export interface ChurchValue {
    title: string
    description: string
}

export interface Stat {
    label: string
    value: string
}

export interface Testimonial {
    name: string
    text: string
}

export interface MinistryHighlight {
    name:        string
    description: string
}

/**
 * One item inside an Instagram post. `src` is null for a video Meta will not
 * serve (licensed or copyrighted audio) — it is shown by its poster and plays
 * on Instagram instead.
 */
export interface InstagramMedia {
    type:   'image' | 'video'
    src:    string | null
    poster: string | null
}

/** A post from the church's Instagram, as cached by App\Services\Instagram\InstagramService. */
export interface InstagramPost {
    id:         string
    type:       'image' | 'video' | 'carousel'
    is_reel:    boolean
    caption:    string | null
    permalink:  string
    timestamp:  string | null
    thumb:      string
    media:      InstagramMedia[]
    count:      number
    expires_at: number | null
}

export interface ServiceTime {
    day: string
    times: string[]
}

export interface DonationFund {
    id: string
    name: string
    description: string
    icon: string
}

// ── Dashboard-specific ─────────────────────────────────────────────────────────

export interface DashboardStats {
    total_members: number
    upcoming_events: number
    pending_tasks: number
    active_departments: number
}

export type UserRole = 'super_admin' | 'church_admin' | 'coordinator' | 'member'

// ── File attachments ───────────────────────────────────────────────────────────

export type FileType = 'image' | 'pdf' | 'audio' | 'video' | 'document'

export interface FileAttachment {
    id: number
    name: string
    original_name: string
    mime_type: string | null
    extension: string | null
    size: number | null
    formatted_size: string
    url: string
    is_public: boolean
    // Computed type helpers from FileResource
    is_image: boolean
    is_pdf: boolean
    is_audio: boolean
    is_video: boolean
    file_type: FileType
    // Dates
    uploaded_at: string
    uploaded_at_formatted: string
    uploader?: { id: number; name: string; avatar: string | null }
}

// ── Attendance ────────────────────────────────────────────────────────────────

export type AttendanceStatus      = 'present' | 'absent' | 'late' | 'excused'
export type AttendanceSource      = 'manual' | 'qr' | 'self_checkin' | 'imported'
export type AttendanceSessionType = 'service' | 'meeting' | 'rehearsal' | 'outreach' | 'volunteer' | 'other'
export type AttendanceSessionStatus = 'planned' | 'active' | 'completed' | 'cancelled'

export interface AttendanceSession {
    id: number
    church_id: number
    department_id: number | null
    event_id: number | null
    title: string
    type: AttendanceSessionType
    type_label: string
    description: string | null
    status: AttendanceSessionStatus
    check_in_enabled: boolean
    check_in_token: string | null
    // Dates — ISO 8601
    scheduled_at: string
    ended_at: string | null
    created_at?: string | null
    // Pre-formatted
    scheduled_at_formatted?: string   // "Sun, Jun 1, 2026"
    scheduled_time?: string           // "10:00 AM"
    scheduled_date_short?: string     // "1 Jun 2026"
    scheduled_month?: string          // "Jun"
    scheduled_day?: number            // 1
    ended_at_formatted?: string       // "12:30 PM"
    // Stats (via withCount)
    attendances_count?: number | null
    present_count?: number | null
    absent_count?: number | null
    late_count?: number | null
    excused_count?: number | null
    attendance_rate?: number | null   // 0-100
    // Relations
    service_plan_id?: number | null
    department?: { id: number; name: string; icon: string | null; color: string | null } | null
    event?: { id: number; title: string; start_at_formatted?: string } | null
    creator?: { id: number; name: string; avatar: string | null } | null
    service_plan?: { id: number; title: string; status: string; scheduled_at_formatted?: string } | null
}

export interface AttendanceRecord {
    id: string                        // UUID
    session_id: number
    user_id: number | null
    guest_name: string | null
    status: AttendanceStatus
    source: AttendanceSource
    notes: string | null
    // Dates — ISO 8601
    checked_in_at: string | null
    check_out_at: string | null
    created_at?: string | null
    // Pre-formatted
    checked_in_at_formatted?: string  // "10:05 AM"
    check_out_at_formatted?: string
    created_at_formatted?: string
    // Relations
    member?: { id: number; name: string; avatar: string | null; email: string } | null
    session?: Pick<AttendanceSession, 'id'|'title'|'type'|'type_label'|'scheduled_at'|'scheduled_at_formatted'|'scheduled_time'|'scheduled_date_short'|'status'> | null
}

/** One row in the attendance marking table on the Show page. */
export interface AttendeeRow {
    user_id: number
    name: string
    avatar: string | null
    status: AttendanceStatus | null     // null = not yet marked
    notes: string | null
    checked_in_at: string | null
    checked_in_at_formatted: string | null
    source: AttendanceSource | null
    attendance_id: string | null        // UUID of existing record, if any
}

export interface AttendanceDashboardStats {
    sessions_this_week: number
    total_records_month: number
    present_count_month: number
    avg_attendance_rate: number | null
    recent_session_rates: { label: string; rate: number }[]
}

export interface MemberAttendanceStats {
    total: number
    present: number
    late: number
    excused: number
    absent: number
    rate: number | null
}

// ── In-app notifications ───────────────────────────────────────────────────────

export type InAppNotificationType =
    | 'task_assigned'
    | 'task_updated'
    | 'task_completed'
    | 'announcement_published'
    | 'event_created'
    | 'event_updated'
    | 'department_member_added'
    | 'department_role_changed'

export interface InAppNotificationData {
    type:       InAppNotificationType
    title:      string
    body:       string
    action_url: string | null
    actor:      { name: string; avatar: string | null } | null
}

export interface InAppNotification {
    id:         string           // UUID
    type:       string           // FQCN of the notification class
    data:       InAppNotificationData
    read_at:    string | null    // ISO-8601 or null
    created_at: string           // ISO-8601
}

// ── Global Search ──────────────────────────────────────────────────────────────

export type SearchResultType =
    | 'member'
    | 'department'
    | 'announcement'
    | 'event'
    | 'task'
    | 'media'
    | 'attendance'

/**
 * Normalized search result — every module produces this shape.
 * The frontend consumes one unified structure regardless of the source.
 */
export interface SearchResultItem {
    id:          string | number
    type:        SearchResultType
    title:       string
    subtitle:    string | null
    meta:        string | null
    url:         string
    /** Lucide icon key: user | building | megaphone | calendar | check-square | file | file-text | image | music | video | calendar-check */
    icon:        string
    badge:       string | null
    badge_color: string | null
}

export interface SearchResultGroup {
    type:    string
    label:   string
    results: SearchResultItem[]
}

export interface SearchResponse {
    groups: SearchResultGroup[]
    query:  string
    total:  number
}

// ── Volunteer Scheduling ──────────────────────────────────────────────────────

export type PlanStatus       = 'draft' | 'published' | 'archived'
export type AssignmentStatus = 'pending' | 'confirmed' | 'declined'

export interface ServicePlan {
    id: number
    title: string
    description: string | null
    scheduled_at: string
    scheduled_at_formatted: string
    scheduled_time: string
    location: string | null
    status: PlanStatus
    notes: string | null
    published_at: string | null
    created_at: string
    created_by: number | null
    published_by: number | null
    total_positions: number | null
    filled_positions: number | null
    fill_rate: number | null
    creator?: { id: number; name: string; avatar: string | null } | null
    plan_positions?: ServicePlanPosition[]
}

export interface ServingPosition {
    id: number
    church_id: number
    department_id: number
    name: string
    description: string | null
    sort_order: number
    is_active: boolean
    department?: { id: number; name: string; icon: string | null; color: string | null } | null
}

export interface ServicePlanPosition {
    id: number
    service_plan_id: number
    serving_position_id: number
    notes: string | null
    sort_order: number
    is_filled: boolean
    serving_position?: ServingPosition | null
    assignments?: VolunteerAssignment[]
}

export interface VolunteerAssignment {
    id: number
    service_plan_position_id: number
    user_id: number
    assigned_by: number | null
    status: AssignmentStatus
    notes: string | null
    responded_at: string | null
    volunteer?: { id: number; name: string; avatar: string | null } | null
}

// ── Communication Center ───────────────────────────────────────────────────────

export type BroadcastStatus       = 'draft' | 'scheduled' | 'sending' | 'sent' | 'failed'
export type BroadcastAudienceType = 'all_members' | 'role' | 'department' | 'event_attendees' | 'volunteers' | 'saved_audience'

export interface Broadcast {
    id: number
    church_id: number
    created_by: number
    title: string
    subject: string
    body: string
    status: BroadcastStatus
    status_label: string
    audience_type: BroadcastAudienceType
    audience_config: Record<string, any> | null
    announcement_id: number | null
    template_id: number | null
    scheduled_at: string | null
    sent_at: string | null
    recipient_count: number
    delivered_count: number
    failed_count: number
    creator?: { id: number; name: string; avatar: string | null }
    announcement?: { id: number; title: string } | null
    created_at: string
    updated_at: string
}

export interface BroadcastRecipient {
    id: number
    broadcast_id: number
    user_id: number
    channel: string
    status: 'pending' | 'sent' | 'failed'
    sent_at: string | null
    failed_at: string | null
    failure_reason: string | null
    user?: { id: number; name: string; avatar: string | null }
}

export interface BroadcastTemplate {
    id: number
    church_id: number
    name: string
    subject: string
    body: string
    category: string | null
    usage_count: number
    created_at: string
}

export interface BroadcastAudience {
    id: number
    church_id: number
    name: string
    description: string | null
    audience_type: 'all_members' | 'role' | 'department' | 'event_attendees' | 'volunteers'
    audience_config: Record<string, any> | null
    member_count: number
    created_at: string
}
