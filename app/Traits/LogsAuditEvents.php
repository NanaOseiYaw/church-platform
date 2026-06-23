<?php

namespace App\Traits;

use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Convenience mixin for controllers and services.
 * Delegates to AuditLogService — keeps controller code concise.
 */
trait LogsAuditEvents
{
    protected function auditLog(
        string $action,
        ?Model $target   = null,
        array  $old      = [],
        array  $new      = [],
        array  $metadata = [],
    ): void {
        app(AuditLogService::class)->record($action, $target, $old, $new, $metadata);
    }
}
