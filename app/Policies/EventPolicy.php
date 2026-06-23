<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('events.view');
    }

    /**
     * A member can view an event if:
     *  1. They belong to the same church.
     *  2. The event's visibility allows it:
     *       public / members_only → any church member
     *       department_only       → must be in the event's department
     *  3. Admins with events.edit bypass the visibility check (can preview cancelled/dept events).
     */
    public function view(User $user, Event $event): bool
    {
        if (! $user->can('events.view')) {
            return false;
        }

        if ($user->church_id !== $event->church_id) {
            return false;
        }

        // Admins see everything (drafts, cancelled, dept-only)
        if ($user->can('events.edit')) {
            return true;
        }

        if ($event->visibility === 'department_only' && $event->department_id) {
            return $event->department
                ?->members()
                ->where('users.id', $user->id)
                ->exists() ?? false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('events.create');
    }

    /**
     * Church admins can edit any event.
     * Coordinators can edit their own events.
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->church_id !== $event->church_id) {
            return false;
        }

        if ($user->can('events.delete')) {
            return true; // church admin level
        }

        return $user->can('events.edit')
            && $event->created_by === $user->id;
    }

    /** Only church admins (events.delete permission) can hard-delete. */
    public function delete(User $user, Event $event): bool
    {
        return $user->can('events.delete')
            && $user->church_id === $event->church_id;
    }

    /**
     * Any member with events.rsvp can RSVP to an event they can view,
     * as long as RSVP is enabled on the event.
     */
    public function rsvp(User $user, Event $event): bool
    {
        return $user->can('events.rsvp')
            && $user->church_id === $event->church_id
            && $event->rsvp_enabled
            && $event->status !== 'cancelled'
            && $event->status !== 'completed';
    }
}
