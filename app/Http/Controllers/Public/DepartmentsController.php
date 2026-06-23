<?php

namespace App\Http\Controllers\Public;

use App\Enums\DepartmentVisibility;
use App\Http\Controllers\Controller;
use App\Models\Department;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentsController extends Controller
{
    public function __invoke(): Response
    {
        // BelongsToChurch global scope automatically filters by app('church.id').
        // Only active, publicly visible departments are shown on the public site.
        $ministries = Department::query()
            ->where('is_active', true)
            ->where('visibility', DepartmentVisibility::PUBLIC)
            ->with('coordinator:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Department $d) => [
                'id'          => $d->id,
                'name'        => $d->name,
                'description' => $d->description ?? '',
                'icon'        => $d->icon ?? 'users',
                'leader'      => $d->coordinator?->name ?? '',
                'color'       => $d->color ?? 'brand',
            ])
            ->values()
            ->all();

        return Inertia::render('Public/Ministries', [
            'ministries' => $ministries,
        ]);
    }
}
