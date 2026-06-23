<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Concerns\ResolvesChurchData;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServingPositionResource;
use App\Models\ServingPosition;
use App\Traits\LogsAuditEvents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServingPositionController extends Controller
{
    use ResolvesChurchData, LogsAuditEvents;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServingPosition::class);

        $positions = ServingPosition::with('department:id,name,icon,color')
            ->orderBy('department_id')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Dashboard/Scheduling/Positions/Index', [
            'positions'   => ServingPositionResource::collection($positions),
            'departments' => $this->activeDepartments(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ServingPosition::class);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:500'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
        ]);

        ServingPosition::create([
            ...$validated,
            'church_id'  => $this->resolvedChurchId(),
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Position created.');
    }

    public function update(Request $request, ServingPosition $pos): RedirectResponse
    {
        $this->authorize('update', $pos);

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name'          => ['required', 'string', 'max:120'],
            'description'   => ['nullable', 'string', 'max:500'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            'is_active'     => ['nullable', 'boolean'],
        ]);

        $pos->update($validated);
        $this->auditLog('schedule.position.updated', $pos, [], [], ['department_id' => $pos->department_id]);

        return back()->with('success', 'Position updated.');
    }

    public function destroy(ServingPosition $pos): RedirectResponse
    {
        $this->authorize('delete', $pos);

        $pos->delete();

        return back()->with('success', 'Position deleted.');
    }
}
