<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Church-CRM extension for a User — one optional row per church member.
 *
 * `church_id` is denormalized here (matching users.church_id) following the
 * platform's universal pattern of direct tenant scoping on every model.
 * It must always equal $user->church_id; the MembersController enforces this
 * by deriving it from the User at updateOrCreate time.
 *
 * `gender` and `marital_status` are plain strings; allowed values are
 * validated at the controller layer via Rule::in([...]).
 */
class MemberProfile extends Model
{
    protected $fillable = [
        'user_id',
        'church_id',
        'date_of_birth',
        'gender',
        'marital_status',
        'address',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'membership_date',
        'baptism_date',
        'salvation_date',
    ];

    protected $casts = [
        'date_of_birth'   => 'date',
        'membership_date' => 'date',
        'baptism_date'    => 'date',
        'salvation_date'  => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
