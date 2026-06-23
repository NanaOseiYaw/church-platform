<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's attendance record at a single AttendanceSession.
 *
 * @property string      $id              UUID
 * @property int         $church_id
 * @property int         $session_id
 * @property int|null    $user_id
 * @property string|null $guest_name
 * @property string      $status          present|absent|late|excused
 * @property \Carbon\Carbon|null $checked_in_at
 * @property \Carbon\Carbon|null $check_out_at
 * @property string|null $notes
 * @property int|null    $recorded_by
 * @property string      $source          manual|qr|self_checkin|imported
 * @property int|null    $attendance_score
 * @property array|null  $metadata
 */
class Attendance extends Model
{
    use HasUuids, BelongsToChurch;

    protected $fillable = [
        'church_id', 'session_id', 'user_id', 'guest_name',
        'status', 'checked_in_at', 'check_out_at',
        'notes', 'recorded_by', 'source',
        'attendance_score', 'metadata',
    ];

    protected $casts = [
        'checked_in_at' => 'datetime',
        'check_out_at'  => 'datetime',
        'metadata'      => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // ── Query scopes ───────────────────────────────────────────────────────────

    public function scopePresent(Builder $query): Builder
    {
        return $query->where('status', 'present');
    }

    public function scopeAbsent(Builder $query): Builder
    {
        return $query->where('status', 'absent');
    }

    public function scopeLate(Builder $query): Builder
    {
        return $query->where('status', 'late');
    }

    public function scopeExcused(Builder $query): Builder
    {
        return $query->where('status', 'excused');
    }

    public function scopeForMember(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession(Builder $query, int $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    // ── Accessors ──────────────────────────────────────────────────────────────

    /** Attendee display name — member name or guest name or "Unknown". */
    public function getDisplayNameAttribute(): string
    {
        return $this->member?->name ?? $this->guest_name ?? 'Unknown';
    }
}
