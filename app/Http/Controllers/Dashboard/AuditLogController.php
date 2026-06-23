<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    use ResolvesChurchData;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('audit.view'), 403);

        $user     = $request->user();
        $churchId = $this->resolvedChurchId();
        $isAdmin  = $user->hasRole('church_admin') || $user->hasRole('super_admin');

        $query = AuditLog::where('church_id', $churchId)
            ->with('user:id,name,avatar')
            ->latest();

        // Coordinators: scope to events tagged with their department(s)
        if (! $isAdmin) {
            $deptIds = $user->departments()->pluck('departments.id')->map(fn ($id) => (int) $id)->toArray();

            if (empty($deptIds)) {
                // Coordinator with no departments sees nothing
                $query->whereRaw('0 = 1');
            } else {
                $placeholders = implode(',', $deptIds);
                $query->whereRaw("json_extract(metadata, '$.department_id') IN ({$placeholders})");
            }
        }

        // ── Filters ───────────────────────────────────────────────────────────

        if ($module = $request->module) {
            $query->where('action', 'like', "{$module}.%");
        }

        if ($action = $request->action) {
            $query->where('action', $action);
        }

        if ($userId = $request->user_id) {
            $query->where('user_id', (int) $userId);
        }

        if ($deptId = $request->department_id) {
            $query->whereRaw("json_extract(metadata, '$.department_id') = ?", [(int) $deptId]);
        }

        if ($from = $request->date_from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->date_to) {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->paginate(25)->withQueryString()->through(fn ($log) => [
            'id'          => $log->id,
            'action'      => $log->action,
            'model_type'  => $log->model_type,
            'model_id'    => $log->model_id,
            'target_name' => $log->target_name,
            'old_values'  => $log->old_values,
            'new_values'  => $log->new_values,
            'metadata'    => $log->metadata,
            'ip_address'  => $log->ip_address,
            'created_at'  => $log->created_at?->toISOString(),
            'actor'       => $log->user
                ? ['id' => $log->user->id, 'name' => $log->user->name, 'avatar' => $log->user->avatar]
                : null,
        ]);

        // Distinct actors who have audit entries for this church (for filter dropdown)
        $actors = AuditLog::where('audit_logs.church_id', $churchId)
            ->whereNotNull('audit_logs.user_id')
            ->join('users', 'audit_logs.user_id', '=', 'users.id')
            ->distinct()
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]);

        $modules = [
            'auth', 'member', 'department', 'announcement', 'event',
            'task', 'attendance', 'file', 'media', 'notification',
            'schedule', 'sermon', 'settings', 'role', 'admin',
        ];

        return Inertia::render('Dashboard/Audit/Index', [
            'logs'        => $logs,
            'filters'     => $request->only('module', 'action', 'user_id', 'department_id', 'date_from', 'date_to'),
            'actors'      => $actors,
            'modules'     => $modules,
            'departments' => $this->activeDepartments(),
            'isAdmin'     => $isAdmin,
        ]);
    }
}
