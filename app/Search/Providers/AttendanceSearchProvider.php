<?php

namespace App\Search\Providers;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Search\Contracts\SearchProvider;
use App\Search\SearchResult;

class AttendanceSearchProvider implements SearchProvider
{
    public function getType(): string  { return 'attendance'; }
    public function getLabel(): string { return 'Attendance'; }

    public function isAvailable(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function search(string $query, User $user, int $churchId, int $limit): array
    {
        $builder = AttendanceSession::where('church_id', $churchId)
            ->where(function ($b) use ($query) {
                $b->where('title',       'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('type',        'like', "%{$query}%");
            });

        // Coordinators are scoped to their own department sessions
        if ($user->isCoordinator() && ! $user->isChurchAdmin()) {
            $deptIds = $user->departments()->pluck('departments.id');
            $builder->whereIn('department_id', $deptIds);
        }

        $results = $builder
            ->orderByDesc('scheduled_at')
            ->limit($limit)
            ->get();

        return $results->map(function (AttendanceSession $session) {
            $badgeColors = [
                'completed' => 'emerald',
                'active'    => 'blue',
                'planned'   => 'neutral',
                'cancelled' => 'rose',
            ];

            return new SearchResult(
                id:         $session->id,
                type:       $this->getType(),
                title:      $session->title,
                subtitle:   $session->type_label,
                meta:       $session->scheduled_at?->format('M j, Y'),
                url:        "/dashboard/attendance/{$session->id}",
                icon:       'calendar-check',
                badge:      ucfirst($session->status),
                badgeColor: $badgeColors[$session->status] ?? 'neutral',
            );
        })->all();
    }
}
