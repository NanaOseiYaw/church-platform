<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Audit entries are write-once.
    public $timestamps = true;

    protected $fillable = [
        'church_id', 'user_id', 'action',
        'model_type', 'model_id', 'target_name',
        'old_values', 'new_values', 'metadata',
        'ip_address', 'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata'   => 'array',
    ];

    public function church(): BelongsTo { return $this->belongsTo(Church::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }

    /**
     * @deprecated Use AuditLogService::record() instead.
     * Kept for backward compatibility during migration.
     */
    public static function record(
        int     $churchId,
        ?int    $userId,
        string  $action,
        array   $oldValues  = [],
        array   $newValues  = [],
        ?object $request    = null,
        ?string $modelType  = null,
        ?int    $modelId    = null,
        ?string $targetName = null,
        array   $metadata   = [],
    ): static {
        return static::create([
            'church_id'   => $churchId,
            'user_id'     => $userId,
            'action'      => $action,
            'model_type'  => $modelType,
            'model_id'    => $modelId,
            'target_name' => $targetName,
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'metadata'    => $metadata  ?: null,
            'ip_address'  => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
        ]);
    }
}
