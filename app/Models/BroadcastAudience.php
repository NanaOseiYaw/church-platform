<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastAudience extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'name', 'description',
        'audience_type', 'audience_config', 'member_count',
    ];

    protected $casts = [
        'audience_config' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
