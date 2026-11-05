<?php

namespace Pijler\Impersonation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pijler\Impersonation\Impersonation;
use Symfony\Component\HttpFoundation\Response;

class ProtectFromImpersonation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && Impersonation::active()) {
            abort(403, trans('This page is not available during an impersonation.'));
        }

        return $next($request);
    }
}
