<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Normalises a single Task model into a stable frontend data contract.
 *
 * – All nullable date fields are pre-formatted so Vue never calls new Date().
 * – is_overdue is computed server-side so Vue never compares Date objects.
 * – Relations are included only when already eager-loaded.
 */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Identity ─────────────────────────────────────────────────────────
            'id'            => $this->id,
            'title'         => $this->title,
            'description'   => $this->description,
            'priority'      => $this->priority,
            'status'        => $this->status,
            'assigned_to'   => $this->assigned_to,
            'department_id' => $this->department_id,

            // ── Dates — ISO 8601 ─────────────────────────────────────────────────
            'due_at'       => $this->due_at?->toJSON(),
            'completed_at' => $this->completed_at?->toJSON(),
            'created_at'   => $this->created_at?->toJSON(),

            // ── Dates — pre-formatted for display ────────────────────────────────
            // "Mon, 2 Jun 2026"
            'due_at_formatted'       => $this->due_at?->format('D, j M Y'),
            // "2 Jun 2026" (compact, used in cards)
            'due_at_short'           => $this->due_at?->format('j M Y'),
            // "2 Jun 2026, 10:30 AM"
            'completed_at_formatted' => $this->completed_at?->format('j M Y, g:i A'),
            'created_at_formatted'   => $this->created_at?->format('j M Y, g:i A'),

            // ── Computed server-side (Vue never touches Date objects) ─────────────
            'is_overdue' => $this->due_at !== null
                && $this->due_at->isPast()
                && ! in_array($this->status, ['completed', 'cancelled'], true),

            // ── Relations ────────────────────────────────────────────────────────
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id'     => $this->assignee->id,
                'name'   => $this->assignee->name,
                'avatar' => $this->assignee->avatar,
            ] : null),

            'assigner' => $this->whenLoaded('assigner', fn () => $this->assigner ? [
                'id'   => $this->assigner->id,
                'name' => $this->assigner->name,
            ] : null),

            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id'    => $this->department->id,
                'name'  => $this->department->name,
                'icon'  => $this->department->icon,
                'color' => $this->department->color,
            ] : null),

            // Guarded with $this->when() so that when comments are not eager-loaded
            // the key becomes a MissingValue and is filtered out by resolve/filter,
            // instead of creating an AnonymousResourceCollection with $collection=null
            // which crashes on json_encode (ResourceCollection::toArray line 102).
            'comments' => $this->when(
                $this->relationLoaded('comments'),
                fn () => TaskCommentResource::collection($this->comments),
            ),

            'comments_count' => $this->when(
                $this->relationLoaded('comments'),
                fn () => $this->comments->count(),
                $this->comments_count ?? null,
            ),

            // File attachments — key omitted unless eager-loaded (see AnnouncementResource)
            'files' => $this->when(
                $this->relationLoaded('files'),
                fn () => FileResource::collection($this->files),
            ),
        ];
    }
}
