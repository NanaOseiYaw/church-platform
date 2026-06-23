<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'task_id'    => $this->task_id,
            'user_id'    => $this->user_id,
            'body'       => $this->body,
            'created_at' => $this->created_at?->toJSON(),

            // Author (when loaded)
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id'     => $this->author->id,
                'name'   => $this->author->name,
                'avatar' => $this->author->avatar,
            ] : null),
        ];
    }
}
