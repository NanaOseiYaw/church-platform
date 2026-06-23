<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'location'    => $this->location,
            'status'      => $this->status,
            'notes'       => $this->notes,
            'created_by'  => $this->created_by,
            'published_by' => $this->published_by,

            // ISO 8601 dates
            'scheduled_at' => $this->scheduled_at?->toJSON(),
            'published_at' => $this->published_at?->toJSON(),
            'created_at'   => $this->created_at?->toJSON(),

            // Pre-formatted for display
            'scheduled_at_formatted' => $this->scheduled_at?->format('D, j M Y'),
            'scheduled_time'         => $this->scheduled_at?->format('g:i A'),

            // Stats — available when plan_positions and their assignments are loaded
            'total_positions'  => $this->total_positions  ?? null,
            'filled_positions' => $this->filled_positions ?? null,
            'fill_rate' => ($this->total_positions ?? 0) > 0
                ? (int) round(($this->filled_positions / $this->total_positions) * 100)
                : 0,

            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id'     => $this->creator->id,
                'name'   => $this->creator->name,
                'avatar' => $this->creator->avatar,
            ] : null),

            'plan_positions' => $this->whenLoaded('planPositions', fn () =>
                ServicePlanPositionResource::collection($this->planPositions)
            ),

            'attendance_session' => $this->whenLoaded('attendanceSession', fn () => $this->attendanceSession ? [
                'id'     => $this->attendanceSession->id,
                'title'  => $this->attendanceSession->title,
                'status' => $this->attendanceSession->status,
                'scheduled_at_formatted' => $this->attendanceSession->scheduled_at?->format('D, j M Y'),
            ] : null),
        ];
    }
}
