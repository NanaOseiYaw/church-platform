<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable frontend contract for a single AttendanceSession.
 *
 * – All dates are pre-formatted so Vue never calls new Date().
 * – Stats counts (present_count, etc.) are included when loaded via withCount().
 * – Relations (department, event, creator) included only when loaded.
 */
class AttendanceSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ─────────────────────────────────────────────────────
            'id'               => $this->id,
            'church_id'        => $this->church_id,
            'department_id'    => $this->department_id,
            'event_id'         => $this->event_id,
            'service_plan_id'  => $this->service_plan_id,
            'title'            => $this->title,
            'type'             => $this->type,
            'type_label'       => $this->type_label,
            'description'      => $this->description,
            'status'           => $this->status,
            'check_in_enabled' => (bool) $this->check_in_enabled,
            // Token is included so the UI can show the check-in URL (future QR)
            'check_in_token'   => $this->check_in_token,

            // ── Dates — ISO 8601 ─────────────────────────────────────────────
            'scheduled_at' => $this->scheduled_at?->toJSON(),
            'ended_at'     => $this->ended_at?->toJSON(),
            'created_at'   => $this->created_at?->toJSON(),

            // ── Dates — pre-formatted for display ────────────────────────────
            'scheduled_at_formatted' => $this->scheduled_at?->format('D, M j, Y'),  // "Sun, Jun 1, 2026"
            'scheduled_time'         => $this->scheduled_at?->format('g:i A'),      // "10:00 AM"
            'scheduled_date_short'   => $this->scheduled_at?->format('j M Y'),      // "1 Jun 2026"
            'scheduled_month'        => $this->scheduled_at?->format('M'),          // "Jun"
            'scheduled_day'          => $this->scheduled_at?->day,                  // 1
            'ended_at_formatted'     => $this->ended_at?->format('g:i A'),

            // ── Attendance stats (populated via withCount) ────────────────────
            'attendances_count' => $this->attendances_count ?? null,
            'present_count'     => $this->present_count     ?? null,
            'absent_count'      => $this->absent_count      ?? null,
            'late_count'        => $this->late_count        ?? null,
            'excused_count'     => $this->excused_count     ?? null,
            'attendance_rate'   => $this->attendance_rate,

            // ── Relations ────────────────────────────────────────────────────
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id'    => $this->department->id,
                'name'  => $this->department->name,
                'icon'  => $this->department->icon,
                'color' => $this->department->color,
            ] : null),

            'event' => $this->whenLoaded('event', fn () => $this->event ? [
                'id'                 => $this->event->id,
                'title'              => $this->event->title,
                'start_at_formatted' => $this->event->start_at?->format('D, M j, Y'),
            ] : null),

            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id'     => $this->creator->id,
                'name'   => $this->creator->name,
                'avatar' => $this->creator->avatar,
            ] : null),

            'service_plan' => $this->whenLoaded('servicePlan', fn () => $this->servicePlan ? [
                'id'                     => $this->servicePlan->id,
                'title'                  => $this->servicePlan->title,
                'status'                 => $this->servicePlan->status,
                'scheduled_at_formatted' => $this->servicePlan->scheduled_at?->format('D, j M Y'),
            ] : null),
        ];
    }
}
