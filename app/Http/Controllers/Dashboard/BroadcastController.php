<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\Broadcast;
use App\Models\BroadcastTemplate;
use App\Models\BroadcastAudience;
use App\Services\BroadcastService;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    use ResolvesChurchData, LogsAuditEvents;

    public function __construct(private readonly BroadcastService $service) {}

    /** GET /dashboard/communication/broadcasts */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $broadcasts = Broadcast::with('creator:id,name,avatar')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Dashboard/Communication/Broadcasts/Index', [
            'broadcasts' => $broadcasts,
            'filters'    => $request->only('status'),
            'canSend'    => $request->user()->can('communication.send') || $request->user()->can('communication.send_dept'),
            'canDelete'  => $request->user()->can('communication.delete'),
        ]);
    }

    /** GET /dashboard/communication/broadcasts/create */
    public function create(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('communication.send') || $user->can('communication.send_dept'), 403);

        $templates = BroadcastTemplate::orderBy('name')->get(['id', 'name', 'subject', 'body', 'category']);
        $audiences = BroadcastAudience::orderBy('name')->get(['id', 'name', 'audience_type', 'member_count']);

        // Coordinators see only their departments for send_dept restriction
        $depts = $this->activeDepartments();
        if ($user->can('communication.send_dept') && ! $user->can('communication.send')) {
            $coordDeptIds = $user->departments()
                ->wherePivotIn('role', ['coordinator'])
                ->pluck('departments.id');
            $depts = $depts->whereIn('id', $coordDeptIds)->values();
        }

        return Inertia::render('Dashboard/Communication/Broadcasts/Create', [
            'templates'   => $templates,
            'audiences'   => $audiences,
            'departments' => $depts,
            'canSendAll'  => $user->can('communication.send'),
        ]);
    }

    /** POST /dashboard/communication/broadcasts */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('communication.send') || $user->can('communication.send_dept'), 403);

        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'subject'         => 'required|string|max:200',
            'body'            => 'required|string',
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers,saved_audience',
            'audience_config' => 'nullable|array',
            'template_id'     => 'nullable|exists:broadcast_templates,id',
            'scheduled_at'    => 'nullable|date|after:now',
            'action'          => 'required|in:draft,send,schedule',
        ]);

        // send_dept restriction: coordinator may only target their own departments
        if (! $user->can('communication.send') && $data['audience_type'] === 'department') {
            $coordDeptIds = $user->departments()
                ->wherePivotIn('role', ['coordinator'])
                ->pluck('departments.id');

            $targetDeptId = $data['audience_config']['department_id'] ?? null;

            if (! $targetDeptId || ! $coordDeptIds->contains($targetDeptId)) {
                abort(403, 'You may only send messages to departments you coordinate.');
            }
        }

        // Coordinators cannot send to non-department audiences
        if (! $user->can('communication.send') && $data['audience_type'] !== 'department') {
            abort(403, 'You may only send messages to a specific department.');
        }

        $broadcast = Broadcast::create([
            'church_id'       => $this->resolvedChurchId(),
            'created_by'      => $user->id,
            'title'           => $data['title'],
            'subject'         => $data['subject'],
            'body'            => $data['body'],
            'status'          => 'draft',
            'audience_type'   => $data['audience_type'],
            'audience_config' => $data['audience_config'] ?? null,
            'template_id'     => $data['template_id'] ?? null,
            'scheduled_at'    => $data['scheduled_at'] ?? null,
        ]);

        // Increment template usage
        if ($broadcast->template_id) {
            BroadcastTemplate::where('id', $broadcast->template_id)
                ->increment('usage_count');
        }

        $action = $data['action'];

        if ($action === 'send') {
            $this->service->send($broadcast);
            $this->auditLog('communication.broadcast_queued', $broadcast);
            return redirect()
                ->route('dashboard.communication.broadcasts.show', $broadcast)
                ->with('success', 'Broadcast sent.');
        }

        if ($action === 'schedule') {
            $this->service->schedule($broadcast);
            $this->auditLog('communication.broadcast_scheduled', $broadcast);
            return redirect()
                ->route('dashboard.communication.broadcasts.show', $broadcast)
                ->with('success', 'Broadcast scheduled.');
        }

        // draft
        $this->auditLog('communication.broadcast_drafted', $broadcast);
        return redirect()
            ->route('dashboard.communication.broadcasts.show', $broadcast)
            ->with('success', 'Draft saved.');
    }

    /** GET /dashboard/communication/broadcasts/{broadcast}/edit */
    public function edit(Request $request, Broadcast $broadcast): Response
    {
        $user = $request->user();
        abort_unless($user->can('communication.send') || $user->can('communication.send_dept'), 403);
        abort_unless($broadcast->church_id === $this->resolvedChurchId(), 403);
        abort_unless(in_array($broadcast->status, ['draft', 'failed'], true), 422, 'Only drafts and failed broadcasts can be edited.');

        $templates = BroadcastTemplate::orderBy('name')->get(['id', 'name', 'subject', 'body', 'category']);
        $audiences = BroadcastAudience::orderBy('name')->get(['id', 'name', 'audience_type', 'member_count']);

        $depts = $this->activeDepartments();
        if ($user->can('communication.send_dept') && ! $user->can('communication.send')) {
            $coordDeptIds = $user->departments()
                ->wherePivotIn('role', ['coordinator'])
                ->pluck('departments.id');
            $depts = $depts->whereIn('id', $coordDeptIds)->values();
        }

        return Inertia::render('Dashboard/Communication/Broadcasts/Edit', [
            'broadcast'   => $broadcast,
            'templates'   => $templates,
            'audiences'   => $audiences,
            'departments' => $depts,
            'canSendAll'  => $user->can('communication.send'),
        ]);
    }

    /** PUT /dashboard/communication/broadcasts/{broadcast} */
    public function update(Request $request, Broadcast $broadcast): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can('communication.send') || $user->can('communication.send_dept'), 403);
        abort_unless($broadcast->church_id === $this->resolvedChurchId(), 403);
        abort_unless(in_array($broadcast->status, ['draft', 'failed'], true), 422, 'Only drafts and failed broadcasts can be edited.');

        $data = $request->validate([
            'title'           => 'required|string|max:200',
            'subject'         => 'required|string|max:200',
            'body'            => 'required|string',
            'audience_type'   => 'required|in:all_members,role,department,event_attendees,volunteers,saved_audience',
            'audience_config' => 'nullable|array',
            'template_id'     => 'nullable|exists:broadcast_templates,id',
            'scheduled_at'    => 'nullable|date|after:now',
            'action'          => 'required|in:draft,send,schedule',
        ]);

        // send_dept restriction: coordinator may only target their own department
        if (! $user->can('communication.send') && $data['audience_type'] === 'department') {
            $coordDeptIds = $user->departments()
                ->wherePivotIn('role', ['coordinator'])
                ->pluck('departments.id');
            $targetDeptId = $data['audience_config']['department_id'] ?? null;
            if (! $targetDeptId || ! $coordDeptIds->contains($targetDeptId)) {
                abort(403, 'You may only send messages to departments you coordinate.');
            }
        }

        if (! $user->can('communication.send') && $data['audience_type'] !== 'department') {
            abort(403, 'You may only send messages to a specific department.');
        }

        $broadcast->update([
            'title'           => $data['title'],
            'subject'         => $data['subject'],
            'body'            => $data['body'],
            'status'          => 'draft',   // always reset to draft on save
            'audience_type'   => $data['audience_type'],
            'audience_config' => $data['audience_config'] ?? null,
            'template_id'     => $data['template_id'] ?? null,
            'scheduled_at'    => $data['scheduled_at'] ?? null,
        ]);

        $action = $data['action'];

        if ($action === 'send') {
            $this->service->send($broadcast);
            $this->auditLog('communication.broadcast_queued', $broadcast);
            return redirect()
                ->route('dashboard.communication.broadcasts.show', $broadcast)
                ->with('success', 'Broadcast sent.');
        }

        if ($action === 'schedule') {
            $this->service->schedule($broadcast);
            $this->auditLog('communication.broadcast_scheduled', $broadcast);
            return redirect()
                ->route('dashboard.communication.broadcasts.show', $broadcast)
                ->with('success', 'Broadcast scheduled.');
        }

        $this->auditLog('communication.broadcast_updated', $broadcast);
        return redirect()
            ->route('dashboard.communication.broadcasts.show', $broadcast)
            ->with('success', 'Draft updated.');
    }

    /** GET /dashboard/communication/broadcasts/{broadcast} */
    public function show(Request $request, Broadcast $broadcast): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);
        abort_unless($broadcast->church_id === $this->resolvedChurchId(), 403);

        $broadcast->load('creator:id,name,avatar');

        $recipients = $broadcast->recipients()
            ->with('user:id,name,avatar')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $user = $request->user();

        return Inertia::render('Dashboard/Communication/Broadcasts/Show', [
            'broadcast'  => $broadcast->append('status_label'),
            'recipients' => $recipients,
            'filters'    => $request->only('status'),
            'canDelete'  => $user->can('communication.delete'),
            'canEdit'    => $user->can('communication.send') || $user->can('communication.send_dept'),
        ]);
    }

    /** DELETE /dashboard/communication/broadcasts/{broadcast} */
    public function destroy(Broadcast $broadcast): RedirectResponse
    {
        abort_unless(request()->user()->can('communication.delete'), 403);
        abort_unless($broadcast->church_id === $this->resolvedChurchId(), 403);

        $this->auditLog('communication.broadcast_deleted', $broadcast);
        $broadcast->delete();

        return redirect()
            ->route('dashboard.communication.broadcasts.index')
            ->with('success', 'Broadcast deleted.');
    }

    /** POST /dashboard/communication/broadcasts/{broadcast}/preview */
    public function preview(Request $request, Broadcast $broadcast): JsonResponse
    {
        abort_unless($request->user()->can('communication.send') || $request->user()->can('communication.send_dept'), 403);

        $body    = $request->input('body', $broadcast->body);
        $preview = $this->service->previewBody($body, $request->user(), $broadcast->loadMissing('church'));

        return response()->json(['preview' => $preview]);
    }

    /**
     * POST /dashboard/communication/broadcasts/preview-anonymous
     * Preview template variables for a new (not yet saved) broadcast.
     * Substitutes {{member_name}} with the current user's name,
     * {{church_name}} with the church name, {{department_name}} with the
     * department name if audience_type is 'department'.
     */
    public function previewAnonymous(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('communication.send') || $user->can('communication.send_dept'), 403);

        $body      = $request->input('body', '');
        $churchId  = $this->resolvedChurchId();
        $church    = \App\Models\Church::find($churchId);
        $deptName  = '';

        if ($request->input('audience_type') === 'department') {
            $deptId = $request->input('audience_config.department_id');
            if ($deptId) {
                $dept     = \App\Models\Department::find($deptId);
                $deptName = $dept?->name ?? '';
            }
        }

        $preview = str_replace(
            ['{{member_name}}', '{{church_name}}', '{{department_name}}'],
            [$user->name, $church?->name ?? '', $deptName],
            $body,
        );

        return response()->json(['preview' => $preview]);
    }
}
