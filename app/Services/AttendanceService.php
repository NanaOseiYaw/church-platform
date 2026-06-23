<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\User;
use App\Models\VolunteerAssignment;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AttendanceService
{
    // ── Expected Attendees ─────────────────────────────────────────────────────

    /**
     * Derive the list of people expected to attend a session:
     *   – If session has an event   → users who RSVPed "going" or "maybe"
     *   – If session has a dept     → all active department members
     *   – Otherwise                 → all church members (paginated search only)
     *
     * Returns a flat Collection of stdObjects with id, name, avatar.
     */
    public function getExpectedAttendees(AttendanceSession $session): Collection
    {
        // Priority 1: linked service plan — seed from non-declined volunteer roster
        if ($session->service_plan_id) {
            $volunteerIds = VolunteerAssignment::whereHas(
                'planPosition',
                fn ($q) => $q->where('service_plan_id', $session->service_plan_id),
            )
            ->where('status', '!=', 'declined')
            ->pluck('user_id')
            ->unique();

            return User::whereIn('id', $volunteerIds)
                ->where('is_active', true)
                ->select('id', 'name', 'avatar')
                ->orderBy('name')
                ->get();
        }

        // Priority 2: linked event — users who RSVPed going/maybe
        if ($session->event_id && $session->event) {
            return $session->event
                ->rsvps()
                ->whereIn('event_rsvp.status', ['going', 'maybe'])
                ->select('users.id', 'users.name', 'users.avatar')
                ->orderBy('users.name')
                ->get();
        }

        // Priority 3: linked department — all active department members
        if ($session->department_id && $session->department) {
            return $session->department
                ->members()
                ->where('users.is_active', true)
                ->select('users.id', 'users.name', 'users.avatar')
                ->orderBy('users.name')
                ->get();
        }

        // Priority 4: general session — all active church members
        return User::where('church_id', $session->church_id)
            ->where('is_active', true)
            ->select('id', 'name', 'avatar')
            ->orderBy('name')
            ->get();
    }

    /**
     * Build the merged attendee+status list for the Show page.
     * Each entry: { user_id, name, avatar, status, notes, checked_in_at,
     *               checked_in_at_formatted, source, attendance_id }
     */
    public function buildAttendeeList(AttendanceSession $session): array
    {
        $expected   = $this->getExpectedAttendees($session);
        $attendance = $session->attendances()
            ->whereNotNull('user_id')
            ->get()
            ->keyBy('user_id');

        return $expected->map(function ($user) use ($attendance) {
            $record = $attendance->get($user->id);

            return [
                'user_id'                => $user->id,
                'name'                   => $user->name,
                'avatar'                 => $user->avatar,
                'status'                 => $record?->status ?? null,
                'notes'                  => $record?->notes ?? null,
                'checked_in_at'          => $record?->checked_in_at?->toJSON(),
                'checked_in_at_formatted'=> $record?->checked_in_at?->format('g:i A'),
                'source'                 => $record?->source ?? null,
                'attendance_id'          => $record?->id ?? null,
            ];
        })->values()->all();
    }

    // ── Bulk Save ──────────────────────────────────────────────────────────────

    /**
     * Upsert attendance records for a session.
     * Creates new rows or updates existing ones (session_id + user_id unique).
     */
    public function bulkSave(
        AttendanceSession $session,
        array             $records,
        int               $recordedBy,
    ): int {
        $saved = 0;

        DB::transaction(function () use ($session, $records, $recordedBy, &$saved) {
            foreach ($records as $record) {
                $checkedInAt = in_array($record['status'], ['present', 'late'], true)
                    ? now()
                    : null;

                Attendance::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'user_id'    => $record['user_id'],
                    ],
                    [
                        'church_id'     => $session->church_id,
                        'status'        => $record['status'],
                        'checked_in_at' => $checkedInAt,
                        'notes'         => $record['notes'] ?? null,
                        'recorded_by'   => $recordedBy,
                        'source'        => $record['source'] ?? 'manual',
                    ]
                );

                $saved++;
            }
        });

        return $saved;
    }

    // ── Statistics ────────────────────────────────────────────────────────────

    /**
     * Aggregate stats for the attendance dashboard index.
     */
    public function getDashboardStats(int $churchId): array
    {
        $thisWeekStart  = now()->startOfWeek();
        $thisMonthStart = now()->startOfMonth();

        $weekSessions = AttendanceSession::forChurch($churchId)
            ->where('scheduled_at', '>=', $thisWeekStart)
            ->count();

        $monthlyAttendances = Attendance::where('church_id', $churchId)
            ->whereHas('session', fn ($q) => $q->where('scheduled_at', '>=', $thisMonthStart))
            ->count();

        $monthlyPresent = Attendance::where('church_id', $churchId)
            ->where('status', 'present')
            ->whereHas('session', fn ($q) => $q->where('scheduled_at', '>=', $thisMonthStart))
            ->count();

        // Last 8 completed sessions for trend widget
        $recentSessions = AttendanceSession::forChurch($churchId)
            ->where('status', 'completed')
            ->orderByDesc('scheduled_at')
            ->withCount([
                'attendances',
                'attendances as present_count' => fn ($q) => $q->where('status', 'present'),
            ])
            ->take(8)
            ->get();

        $avgRate = $recentSessions->filter(fn ($s) => $s->attendances_count > 0)
            ->avg(fn ($s) => ($s->present_count / $s->attendances_count) * 100);

        return [
            'sessions_this_week'    => $weekSessions,
            'total_records_month'   => $monthlyAttendances,
            'present_count_month'   => $monthlyPresent,
            'avg_attendance_rate'   => $avgRate ? (int) round($avgRate) : null,
            'recent_session_rates'  => $recentSessions->map(fn ($s) => [
                'label' => $s->scheduled_at->format('j M'),
                'rate'  => $s->attendances_count > 0
                    ? (int) round(($s->present_count / $s->attendances_count) * 100)
                    : 0,
            ])->values()->all(),
        ];
    }

    /**
     * Member attendance history with lifetime stats.
     */
    public function getMemberStats(User $member): array
    {
        $base = Attendance::where('user_id', $member->id)
            ->where('church_id', $member->church_id);

        $total   = (clone $base)->count();
        $present = (clone $base)->where('status', 'present')->count();
        $late    = (clone $base)->where('status', 'late')->count();
        $excused = (clone $base)->where('status', 'excused')->count();
        $absent  = (clone $base)->where('status', 'absent')->count();

        return [
            'total'   => $total,
            'present' => $present,
            'late'    => $late,
            'excused' => $excused,
            'absent'  => $absent,
            'rate'    => $total > 0 ? (int) round((($present + $late) / $total) * 100) : null,
        ];
    }

    // ── Session Lifecycle ─────────────────────────────────────────────────────

    /**
     * Activate a session and optionally auto-generate a check-in token (QR readiness).
     */
    public function activateSession(AttendanceSession $session): AttendanceSession
    {
        $session->update([
            'status'            => 'active',
            'check_in_enabled'  => true,
            'check_in_token'    => $session->check_in_token ?? (string) Str::uuid(),
        ]);

        return $session->fresh();
    }

    /**
     * Complete a session, recording the ended_at timestamp.
     */
    public function completeSession(AttendanceSession $session): AttendanceSession
    {
        $session->update([
            'status'    => 'completed',
            'ended_at'  => $session->ended_at ?? now(),
        ]);

        return $session->fresh();
    }
}
