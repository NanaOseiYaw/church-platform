<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServingPositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'department_id' => $this->department_id,
            'name'        => $this->name,
            'description' => $this->description,
            'sort_order'  => $this->sort_order,
            'is_active'   => $this->is_active,
            'department'  => $this->whenLoaded('department', fn () => $this->department ? [
                'id'    => $this->department->id,
                'name'  => $this->department->name,
                'icon'  => $this->department->icon,
                'color' => $this->department->color,
            ] : null),
        ];
    }
}
