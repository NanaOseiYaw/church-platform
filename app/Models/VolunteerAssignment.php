<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VolunteerAssignment extends Model
{
    use BelongsToChurch;

    const STATUSES = ['pending', 'confirmed', 'declined'];

    protected $fillable = [
        'church_id', 'service_plan_position_id', 'user_id',
        'assigned_by', 'status', 'notes', 'responded_at',
    ];

    protected $casts = ['responded_at' => 'datetime'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function planPosition(): BelongsTo
    {
        return $this->belongsTo(ServicePlanPosition::class, 'service_plan_position_id');
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopePending(Builder $query): Builder   { return $query->where('status', 'pending'); }
    public function scopeConfirmed(Builder $query): Builder { return $query->where('status', 'confirmed'); }
    public function scopeDeclined(Builder $query): Builder  { return $query->where('status', 'declined'); }
}
