<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a User model into a frontend-safe member DTO.
 *
 * Pre-formatted fields so Vue components never need to call new Date() on
 * raw backend strings.
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'email'     => $this->email,
            'avatar'    => $this->avatar,
            'is_active' => (bool) ($this->is_active ?? true),
            'phone'     => $this->phone    ?? null,
            'timezone'  => $this->timezone ?? null,

            // ── Dates ──────────────────────────────────────────────────────────
            'created_at'           => $this->created_at?->toJSON(),
            'created_at_formatted' => $this->created_at?->format('j M Y') ?? '—',

            // ── Counts (present only when loaded via withCount) ─────────────
            'departments_count' => $this->when(
                isset($this->departments_count),
                fn () => (int) $this->departments_count,
            ),

            // ── Roles (eager-loaded) ────────────────────────────────────────
            'roles' => $this->whenLoaded('roles', fn () =>
                $this->roles->map(fn ($r) => ['name' => $r->name])->values()
            ),

            // ── Departments (eager-loaded) — includes formatted pivot ───────
            'departments' => $this->whenLoaded('departments', fn () =>
                $this->departments->map(fn ($d) => [
                    'id'            => $d->id,
                    'name'          => $d->name,
                    'icon'          => $d->icon,
                    'color'         => $d->color,
                    'is_active'     => (bool) $d->is_active,
                    'members_count' => (int) ($d->members_count ?? 0),
                    'coordinator'   => $d->coordinator ? [
                        'id'   => $d->coordinator->id,
                        'name' => $d->coordinator->name,
                    ] : null,
                    'pivot' => $d->pivot ? [
                        'role'                => $d->pivot->role,
                        'joined_at'           => $d->pivot->joined_at,
                        'joined_at_formatted' => $d->pivot->joined_at
                            ? Carbon::parse($d->pivot->joined_at)->format('j M Y')
                            : null,
                    ] : null,
                ])->values()
            ),

            // ── CRM profile (eager-loaded) ──────────────────────────────────
            // Self-editable fields are visible to any authenticated viewer.
            // Church-journey fields (membership/baptism/salvation dates) are
            // admin-only for both writing AND reading — non-admins never
            // receive those keys in the API response.
            'profile' => $this->whenLoaded('profile', fn () =>
                $this->profile ? [
                    'date_of_birth'                  => $this->profile->date_of_birth?->toDateString(),
                    'gender'                         => $this->profile->gender,
                    'marital_status'                 => $this->profile->marital_status,
                    'address'                        => $this->profile->address,
                    'emergency_contact_name'         => $this->profile->emergency_contact_name,
                    'emergency_contact_relationship' => $this->profile->emergency_contact_relationship,
                    'emergency_contact_phone'        => $this->profile->emergency_contact_phone,
                    // Admin-only read fields — omitted for non-admins
                    'membership_date' => $this->when(
                        request()->user()?->can('members.edit'),
                        $this->profile->membership_date?->toDateString(),
                    ),
                    'baptism_date'    => $this->when(
                        request()->user()?->can('members.edit'),
                        $this->profile->baptism_date?->toDateString(),
                    ),
                    'salvation_date'  => $this->when(
                        request()->user()?->can('members.edit'),
                        $this->profile->salvation_date?->toDateString(),
                    ),
                ] : null
            ),
        ];
    }
}
