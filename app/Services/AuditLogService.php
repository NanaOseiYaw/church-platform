<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Record an auditable action.
     *
     * Always silently no-ops on failure — audit errors must never
     * break the main request flow.
     *
     * @param  string      $action   Dot-separated action name, e.g. 'announcement.published'
     * @param  Model|null  $target   The affected Eloquent model (optional)
     * @param  array       $old      Previous values for diff display
     * @param  array       $new      New values for diff display
     * @param  array       $metadata Free-form context. Include 'department_id' for dept-scoped events.
     * @param  User|null   $actor    Defaults to Auth::user()
     * @param  int|null    $churchId Defaults to app('church.id') or actor's church_id
     */
    public function record(
        string  $action,
        ?Model  $target    = null,
        array   $old       = [],
        array   $new       = [],
        array   $metadata  = [],
        ?User   $actor     = null,
        ?int    $churchId  = null,
    ): void {
        try {
            /** @var User|null $user */
            $user = $actor ?? Auth::user();

            $church = $churchId
                ?? (app()->bound('church.id') ? app('church.id') : null)
                ?? $user?->church_id;

            if (! $church) {
                return; // No church context — cannot log
            }

            $targetName = null;
            if ($target) {
                $targetName = $target->name
                    ?? $target->title
                    ?? (string) $target->getKey();
            }

            AuditLog::create([
                'church_id'   => $church,
                'user_id'     => $user?->id,
                'action'      => $action,
                'model_type'  => $target ? get_class($target) : null,
                'model_id'    => $target?->getKey(),
                'target_name' => $targetName,
                'old_values'  => $old      ?: null,
                'new_values'  => $new      ?: null,
                'metadata'    => $metadata ?: null,
                'ip_address'  => request()?->ip(),
                'user_agent'  => request()?->userAgent(),
            ]);
        } catch (\Throwable) {
            // Swallow all exceptions — audit must never break primary flow.
        }
    }
}
