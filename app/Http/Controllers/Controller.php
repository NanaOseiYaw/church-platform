<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Return the currently-resolved church ID from the container.
     *
     * Aborts with 403 when no church context has been resolved — this should
     * never happen for authenticated dashboard routes because ResolveTenant
     * always binds a church ID from the authenticated user.
     */
    protected function resolvedChurchId(): int
    {
        $id = app('church.id');
        abort_if($id === null, 403, 'No church context resolved.');
        return (int) $id;
    }
}
