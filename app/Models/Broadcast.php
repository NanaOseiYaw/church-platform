<?php

namespace App\Models;

use App\Traits\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Broadcast extends Model
{
    use BelongsToChurch, SoftDeletes;

    protected $fillable = [
        'church_id', 'created_by', 'title', 'subject', 'body',
        'status', 'audience_type', 'audience_config',
        'announcement_id', 'template_id',
        'scheduled_at', 'sent_at',
        'recipient_count', 'delivered_count', 'failed_count',
    ];

    protected $casts = [
        'audience_config' => 'array',
        'scheduled_at'    => 'datetime',
        'sent_at'         => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BroadcastTemplate::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeDraft($query)      { return $query->where('status', 'draft'); }
    public function scopeScheduled($query)  { return $query->where('status', 'scheduled'); }
    public function scopeSent($query)       { return $query->where('status', 'sent'); }
    public function scopeFailed($query)     { return $query->where('status', 'failed'); }

    // ── Accessors ──────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft'     => 'Draft',
            'scheduled' => 'Scheduled',
            'sending'   => 'Sending',
            'sent'      => 'Sent',
            'failed'    => 'Failed',
            default     => ucfirst($this->status),
        };
    }
}
