<?php

namespace App\Listeners\Notifications;

use App\Events\DepartmentMemberRoleWasChanged;
use App\Events\DepartmentMemberWasAdded;
use App\Notifications\AppNotification;
use Illuminate\Events\Dispatcher;

class DepartmentNotificationSubscriber
{
    /**
     * Notify a user when they are added to a department.
     * Self-additions (admin adding themselves) are still notified
     * because they may have been added by someone else's action.
     */
    public function handleMemberAdded(DepartmentMemberWasAdded $event): void
    {
        // Don't notify if the actor added themselves
        if ($event->member->id === $event->actor->id) {
            return;
        }

        $roleLabel = ucfirst(str_replace('_', ' ', $event->role));

        $event->member->notify(new AppNotification(
            notifType: AppNotification::TYPE_DEPT_MEMBER_ADDED,
            title:     "Added to {$event->department->name}",
            body:      "{$event->actor->name} added you as {$roleLabel} in {$event->department->name}",
            actionUrl: "/dashboard/departments/{$event->department->id}",
            actor:     ['name' => $event->actor->name, 'avatar' => $event->actor->avatar],
        ));
    }

    /**
     * Notify a user when their department role is changed.
     * Skip if the user changed their own role (shouldn't happen but be safe).
     */
    public function handleRoleChanged(DepartmentMemberRoleWasChanged $event): void
    {
        if ($event->member->id === $event->actor->id) {
            return;
        }

        $roleLabel = ucfirst(str_replace('_', ' ', $event->newRole));

        $event->member->notify(new AppNotification(
            notifType: AppNotification::TYPE_DEPT_ROLE_CHANGED,
            title:     'Role updated in ' . $event->department->name,
            body:      "{$event->actor->name} changed your role to {$roleLabel}",
            actionUrl: "/dashboard/departments/{$event->department->id}",
            actor:     ['name' => $event->actor->name, 'avatar' => $event->actor->avatar],
        ));
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            DepartmentMemberWasAdded::class       => 'handleMemberAdded',
            DepartmentMemberRoleWasChanged::class => 'handleRoleChanged',
        ];
    }
}
