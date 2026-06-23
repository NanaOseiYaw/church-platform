<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\AttendanceSession;
use App\Models\Department;
use App\Models\Event;
use App\Models\Sermon;
use App\Models\ServicePlan;
use App\Models\ServingPosition;
use App\Models\Task;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic observer that logs 'created' and 'deleted' events for all
 * auditable models. Models are registered in AppServiceProvider::boot().
 *
 * Fires on created + deleted only. All 'updated' events and named
 * business actions (publish, assign, archive) use explicit service calls.
 */
class AuditableObserver
{
    /**
     * Map model class → dot-separated action prefix.
     * Verb ('created' or 'deleted') is appended automatically.
     */
    private static array $prefixMap = [
        Announcement::class     => 'announcement',
        Event::class            => 'event',
        Task::class             => 'task',
        Department::class       => 'department',
        ServingPosition::class  => 'schedule.position',
        ServicePlan::class      => 'schedule.plan',
        AttendanceSession::class => 'attendance.session',
        Sermon::class           => 'sermon',
    ];

    public function created(Model $model): void
    {
        $prefix = self::$prefixMap[get_class($model)] ?? null;
        if (! $prefix) return;

        $metadata = [];
        if (isset($model->department_id) && $model->department_id) {
            $metadata['department_id'] = (int) $model->department_id;
        }

        app(AuditLogService::class)->record(
            action:   "{$prefix}.created",
            target:   $model,
            metadata: $metadata,
        );
    }

    public function deleted(Model $model): void
    {
        $prefix = self::$prefixMap[get_class($model)] ?? null;
        if (! $prefix) return;

        $metadata = [];
        if (isset($model->department_id) && $model->department_id) {
            $metadata['department_id'] = (int) $model->department_id;
        }

        app(AuditLogService::class)->record(
            action:   "{$prefix}.deleted",
            target:   $model,
            metadata: $metadata,
        );
    }
}
