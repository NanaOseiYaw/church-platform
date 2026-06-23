<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BroadcastTemplate extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'name', 'subject', 'body', 'category', 'usage_count',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class, 'template_id');
    }
}
