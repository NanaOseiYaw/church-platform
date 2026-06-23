<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An AttendanceSession groups individual attendance records under a single event.
 * Examples: "Sunday Morning Service – 1 Jun 2026", "Youth Meeting – 28 May 2026".
 *
 * @property int         $id
 * @property int         $church_id
 * @property int|null    $department_id
 * @property int|null    $event_id
 * @property int|null    $created_by
 * @property string      $title
 * @property string      $type            service|meeting|rehearsal|outreach|volunteer|other
 * @property string|null $description
 * @property \Carbon\Carbon $scheduled_at
 * @property \Carbon\Carbon|null $ended_at
 * @property string      $status          planned|active|completed|cancelled
 * @property bool        $check_in_enabled
 * @property string|null $check_in_token
 * @property array|null  $metadata
 *
 * Loaded via withCount():
 * @property int $attendances_count
 * @property int $present_count
 * @property int $absent_count
 * @property int $late_count
 * @property int $excused_count
 */
class AttendanceSession extends Model
{
    use BelongsToChurch;

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'church_id', 'department_id', 'event_id', 'service_plan_id', 'created_by',
        'title', 'type', 'description',
        'scheduled_at', 'ended_at',
        'status', 'check_in_enabled', 'check_in_token',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at'      => 'datetime',
        'ended_at'          => 'datetime',
        'check_in_enabled'  => 'boolean',
        'metadata'          => 'array',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'session_id');
    }

    // ── Query scopes ───────────────────────────────────────────────────────────

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', 'planned');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>=', now())
                     ->whereIn('status', ['planned', 'active']);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('scheduled_at', '>=', now()->subDays($days))
                     ->orderByDesc('scheduled_at');
    }

    /** Filter sessions the given coordinator is allowed to manage. */
    public function scopeForCoordinator(Builder $query, User $user): Builder
    {
        $deptIds = $user->departments()->pluck('departments.id');

        return $query->where(function (Builder $q) use ($user, $deptIds) {
            // Admin sees everything; coordinator sees only their depts
            if ($user->hasPermissionTo('attendance.manage') && ! $user->isCoordinator()) {
                return;
            }
            $q->whereIn('department_id', $deptIds)
              ->orWhereNull('department_id');
        });
    }

    // ── Computed helpers ───────────────────────────────────────────────────────

    /**
     * Attendance rate as a 0-100 integer (uses withCount-loaded attributes).
     * Returns null when no attendance has been recorded yet.
     */
    public function getAttendanceRateAttribute(): ?int
    {
        $total = $this->attendances_count ?? null;

        if ($total === null || $total === 0) {
            return null;
        }

        $present = $this->present_count ?? 0;

        return (int) round(($present / $total) * 100);
    }

    /** Human-readable type label. */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'service'    => 'Service',
            'meeting'    => 'Meeting',
            'rehearsal'  => 'Rehearsal',
            'outreach'   => 'Outreach',
            'volunteer'  => 'Volunteer',
            default      => 'Other',
        };
    }
}
