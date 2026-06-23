<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Stable frontend contract for a single Attendance record.
 */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ─────────────────────────────────────────────────────
            'id'         => $this->id,
            'session_id' => $this->session_id,
            'user_id'    => $this->user_id,
            'guest_name' => $this->guest_name,

            // ── Status ───────────────────────────────────────────────────────
            'status' => $this->status,
            'source' => $this->source,
            'notes'  => $this->notes,

            // ── Dates — ISO 8601 ─────────────────────────────────────────────
            'checked_in_at'  => $this->checked_in_at?->toJSON(),
            'check_out_at'   => $this->check_out_at?->toJSON(),
            'created_at'     => $this->created_at?->toJSON(),

            // ── Dates — pre-formatted ────────────────────────────────────────
            'checked_in_at_formatted'  => $this->checked_in_at?->format('g:i A'),
            'check_out_at_formatted'   => $this->check_out_at?->format('g:i A'),
            'created_at_formatted'     => $this->created_at?->format('j M Y, g:i A'),

            // ── Relations (only when loaded) ─────────────────────────────────
            'member' => $this->whenLoaded('member', fn () => $this->member ? [
                'id'     => $this->member->id,
                'name'   => $this->member->name,
                'avatar' => $this->member->avatar,
                'email'  => $this->member->email,
            ] : null),

            'session' => $this->whenLoaded('session', fn () => $this->session ? [
                'id'                     => $this->session->id,
                'title'                  => $this->session->title,
                'type'                   => $this->session->type,
                'type_label'             => $this->session->type_label,
                'scheduled_at'           => $this->session->scheduled_at?->toJSON(),
                'scheduled_at_formatted' => $this->session->scheduled_at?->format('D, M j, Y'),
                'scheduled_time'         => $this->session->scheduled_at?->format('g:i A'),
                'scheduled_date_short'   => $this->session->scheduled_at?->format('j M Y'),
                'status'                 => $this->session->status,
            ] : null),
        ];
    }
}
