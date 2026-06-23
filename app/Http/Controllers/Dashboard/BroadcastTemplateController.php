<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Models\BroadcastTemplate;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastTemplateController extends Controller
{
    use ResolvesChurchData, LogsAuditEvents;

    /** GET /dashboard/communication/templates */
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('communication.view'), 403);

        $templates = BroadcastTemplate::orderByDesc('created_at')
            ->get(['id', 'name', 'subject', 'body', 'category', 'usage_count', 'created_at']);

        return Inertia::render('Dashboard/Communication/Templates/Index', [
            'templates' => $templates,
            'canManage' => $request->user()->can('communication.manage'),
        ]);
    }

    /** POST /dashboard/communication/templates */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'subject'  => 'required|string|max:200',
            'body'     => 'required|string',
            'category' => 'nullable|string|max:80',
        ]);

        $template = BroadcastTemplate::create([
            ...$data,
            'church_id'  => $this->resolvedChurchId(),
            'created_by' => $request->user()->id,
        ]);

        $this->auditLog('communication.template_created', $template);

        return back()->with('success', 'Template saved.');
    }

    /** PUT /dashboard/communication/templates/{template} */
    public function update(Request $request, BroadcastTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('communication.manage'), 403);

        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'subject'  => 'required|string|max:200',
            'body'     => 'required|string',
            'category' => 'nullable|string|max:80',
        ]);

        $template->update($data);

        $this->auditLog('communication.template_updated', $template);

        return back()->with('success', 'Template updated.');
    }

    /** DELETE /dashboard/communication/templates/{template} */
    public function destroy(BroadcastTemplate $template): RedirectResponse
    {
        abort_unless(request()->user()->can('communication.manage'), 403);

        $this->auditLog('communication.template_deleted', $template);
        $template->delete();

        return back()->with('success', 'Template deleted.');
    }
}
