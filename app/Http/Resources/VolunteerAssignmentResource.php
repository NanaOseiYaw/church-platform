<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VolunteerAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'service_plan_position_id' => $this->service_plan_position_id,
            'user_id'                 => $this->user_id,
            'assigned_by'             => $this->assigned_by,
            'status'                  => $this->status,
            'notes'                   => $this->notes,
            'responded_at'            => $this->responded_at?->toJSON(),
            'volunteer' => $this->whenLoaded('volunteer', fn () => $this->volunteer ? [
                'id'     => $this->volunteer->id,
                'name'   => $this->volunteer->name,
                'avatar' => $this->volunteer->avatar,
            ] : null),
        ];
    }
}
