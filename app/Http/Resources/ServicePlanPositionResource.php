<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicePlanPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'service_plan_id'     => $this->service_plan_id,
            'serving_position_id' => $this->serving_position_id,
            'notes'               => $this->notes,
            'sort_order'          => $this->sort_order,
            'is_filled' => $this->when(
                $this->relationLoaded('assignments'),
                fn () => $this->assignments->where('status', '!=', 'declined')->count() > 0,
                false,
            ),
            'serving_position' => $this->whenLoaded('servingPosition', fn () =>
                ServingPositionResource::make($this->servingPosition)
            ),
            'assignments' => $this->whenLoaded('assignments', fn () =>
                VolunteerAssignmentResource::collection($this->assignments)
            ),
        ];
    }
}
