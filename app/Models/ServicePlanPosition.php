<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlanPosition extends Model
{
    protected $fillable = [
        'service_plan_id', 'serving_position_id', 'notes', 'sort_order',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class, 'service_plan_id');
    }

    public function servingPosition(): BelongsTo
    {
        return $this->belongsTo(ServingPosition::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class, 'service_plan_position_id');
    }

    public function isFilled(): bool
    {
        return $this->assignments()
            ->where('status', '!=', 'declined')
            ->exists();
    }
}
