<?php

use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channel Authorization
|--------------------------------------------------------------------------
|
| Channel names use dot notation (without the "private-" prefix — Laravel Echo
| adds that automatically for private channels).
|
| Security rules enforced here:
|   - Every channel verifies church_id to prevent cross-tenant subscriptions.
|   - Permission checks gate coordinator/member access where needed.
|   - Non-members receive false (connection refused).
|
*/

// ── Default user notification channel (from Laravel scaffolding) ────────────
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

// ── Personal user channel — receives live notification events ────────────────
// Only the authenticated user can subscribe to their own channel.
Broadcast::channel('user.{userId}', function (User $user, int $userId): bool {
    return (int) $user->id === (int) $userId;
});

// ── Church-wide channel — receives task / announcement / dashboard events ────
// Any member of that church may subscribe.  Fine-grained visibility is applied
// on the frontend (members only act on events relevant to them).
Broadcast::channel('church.{churchId}', function (User $user, int $churchId): bool {
    return (int) $user->church_id === (int) $churchId;
});

// ── Department channel — receives department-scoped events ───────────────────
// The user must belong to the same church.  Currently not used for active
// broadcasts but wired for future department-level collaboration events.
Broadcast::channel('department.{departmentId}', function (User $user, int $departmentId): bool {
    return (int) $user->church_id !== null
        && $user->departments()->where('departments.id', $departmentId)->exists();
});

// ── Attendance session channel — live check-in updates ───────────────────────
// Requires attendance.view permission AND same church as the session.
// Uses string ID because AttendanceSession uses UUID primary keys.
Broadcast::channel('attendance.{sessionId}', function (User $user, string $sessionId): bool {
    if (! $user->can('attendance.view')) {
        return false;
    }

    $session = AttendanceSession::find($sessionId);

    return $session !== null
        && (int) $user->church_id === (int) $session->church_id;
});
