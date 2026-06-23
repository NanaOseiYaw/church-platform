<?php

namespace App\Search\Providers;

use App\Models\File;
use App\Models\User;
use App\Search\Contracts\SearchProvider;
use App\Search\SearchResult;

class MediaSearchProvider implements SearchProvider
{
    public function getType(): string  { return 'media'; }
    public function getLabel(): string { return 'Media & Files'; }

    public function isAvailable(User $user): bool
    {
        return $user->can('media.view');
    }

    public function search(string $query, User $user, int $churchId, int $limit): array
    {
        $builder = File::where('church_id', $churchId)
            ->where(function ($b) use ($query) {
                $b->where('original_name', 'like', "%{$query}%")
                  ->orWhere('name',         'like', "%{$query}%");
            });

        // Non-admins: hide files attached to departments they're not a member of.
        // Mirrors the same constraint applied in MediaController::index() and
        // FilePolicy::view() so all three access paths are consistent.
        if (! $user->can('departments.edit')) {
            $myDeptIds = $user->departments()->pluck('departments.id');
            $builder->where(function ($q) use ($myDeptIds) {
                $q->where('fileable_type', '!=', 'department')
                  ->orWhereNull('fileable_type')
                  ->orWhereIn('fileable_id', $myDeptIds);
            });
        }

        $results = $builder
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $results->map(function (File $file) {
            $iconMap = [
                'image'    => 'image',
                'pdf'      => 'file-text',
                'audio'    => 'music',
                'video'    => 'video',
                'document' => 'file',
            ];

            return new SearchResult(
                id:         $file->id,
                type:       $this->getType(),
                title:      $file->original_name,
                subtitle:   $file->formatted_size,
                meta:       $file->created_at?->format('M j, Y'),
                url:        "/dashboard/files/{$file->id}/download",
                icon:       $iconMap[$file->file_type] ?? 'file',
                badge:      $file->is_public ? 'Public' : null,
                badgeColor: 'emerald',
            );
        })->all();
    }
}
