<?php

namespace App\Http\Controllers\Dashboard;

use App\Events\TaskWasAssigned;
use App\Events\TaskWasCompleted;
use App\Events\TaskWasUpdated;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Traits\LogsAuditEvents;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TasksController extends Controller
{
    use ResolvesChurchData, LogsAuditEvents;

    public function __construct(private readonly TaskService $tasks) {}

    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $user = $request->user();

        $tasks = $this->tasks->paginate($user, $request->only(
            'tab', 'search', 'department_id', 'priority'
        ))->through(fn ($t) => TaskResource::make($t)->toArray($request));

        return Inertia::render('Dashboard/Tasks/Index', [
            'tasks'       => $tasks,
            'departments' => $this->activeDepartments(),
            'filters'     => $request->only('tab', 'search', 'department_id', 'priority'),
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $this->authorize('create', Task::class);

        return Inertia::render('Dashboard/Tasks/Create', [
            'members'     => $this->churchMembers(),
            'departments' => $this->activeDepartments(),
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $task  = $this->tasks->create($request->validated(), $actor);

        // Always log creation
        $this->auditLog('task.created', $task, [], [], ['department_id' => $task->department_id]);

        // Notify the assignee (if not a self-assignment)
        if ($task->assigned_to && $task->assigned_to !== $actor->id) {
            TaskWasAssigned::dispatch($task, $actor);
            $this->auditLog(
                'task.assigned',
                $task,
                [],
                ['assigned_to' => $task->assigned_to],
                ['department_id' => $task->department_id],
            );
        }

        return redirect()
            ->route('dashboard.tasks.show', $task)
            ->with('success', "\u{201c}{$task->title}\u{201d} created.");
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(Request $request, Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'assignee:id,name,avatar',
            'assigner:id,name,avatar',
            'department:id,name,icon,color',
            'comments' => fn ($q) => $q->with('author:id,name,avatar')->latest(),
            'files.uploader:id,name,avatar',
        ]);

        $user = $request->user();

        return Inertia::render('Dashboard/Tasks/Show', [
            'task'       => TaskResource::make($task),
            'canEdit'    => $user->can('update', $task),
            'canDelete'  => $user->can('delete', $task),
            'canStatus'  => $user->can('updateStatus', $task),
            'canComment' => $user->can('comment', $task),
            'canUpload'  => $user->can('create', \App\Models\File::class),
        ]);
    }

    // ── Edit ───────────────────────────────────────────────────────────────────

    public function edit(Task $task): Response
    {
        $this->authorize('update', $task);

        return Inertia::render('Dashboard/Tasks/Edit', [
            'task'        => TaskResource::make($task),
            'members'     => $this->churchMembers(),
            'departments' => $this->activeDepartments(),
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $actor = $request->user();
        $this->tasks->update($task, $request->validated());

        TaskWasUpdated::dispatch($task->fresh(), $actor);

        $this->auditLog('task.updated', $task, [], [], ['department_id' => $task->department_id]);

        return redirect()
            ->route('dashboard.tasks.show', $task)
            ->with('success', 'Task updated.');
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $deptId = $task->department_id;
        $title  = $task->title;
        $this->auditLog('task.deleted', $task, [], [], ['department_id' => $deptId]);
        $this->tasks->delete($task);

        return redirect()
            ->route('dashboard.tasks.index')
            ->with('success', "\u{201c}{$title}\u{201d} deleted.");
    }

    // ── Update Status ──────────────────────────────────────────────────────────

    public function updateStatus(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('updateStatus', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::in(Task::STATUSES)],
        ]);

        $actor = $request->user();
        $this->tasks->updateStatus($task, $validated['status']);

        if ($validated['status'] === 'completed') {
            TaskWasCompleted::dispatch($task->fresh(), $actor);
        } else {
            TaskWasUpdated::dispatch($task->fresh(), $actor);
        }

        $action = $validated['status'] === 'completed' ? 'task.completed' : 'task.status.changed';
        $this->auditLog(
            $action,
            $task,
            [],
            ['status' => $validated['status']],
            ['department_id' => $task->department_id],
        );

        return back()->with('success', 'Status updated.');
    }

    // ── Comments ───────────────────────────────────────────────────────────────

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);

        $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $this->tasks->addComment($task, $request->user(), $request->string('body')->toString());

        $this->auditLog('task.comment.added', $task, [], [], ['department_id' => $task->department_id]);

        return back()->with('success', 'Comment added.');
    }

    public function deleteComment(Task $task, TaskComment $comment): RedirectResponse
    {
        $this->authorize('deleteComment', $comment);

        $this->tasks->deleteComment($comment);

        return back()->with('success', 'Comment deleted.');
    }

}
