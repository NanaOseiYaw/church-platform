<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    protected $fillable = [
        'church_id',
        'fund_id',
        'stripe_session_id',
        'amount_cents',
        'currency',
        'donor_email',
        'donor_name',
        'status',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }
}
