<?php

namespace Pijler\Impersonation\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Pijler\Impersonation\Events\ImpersonationApplying;
use Pijler\Impersonation\Events\ImpersonationExpired;
use Pijler\Impersonation\Impersonation;
use Symfony\Component\HttpFoundation\Response;

class HandleImpersonation
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || Impersonation::applied()) {
            return $next($request);
        }

        $state = Impersonation::state();

        if (is_null($state)) {
            return $next($request);
        }

        $guard = Impersonation::guard(data_get($state, 'guard'));

        $actor = $guard->user();

        // The session belongs to someone else now, so this isn't their impersonation.
        if (! Impersonation::matches($actor, data_get($state, 'actor'))) {
            Impersonation::stop();

            return $next($request);
        }

        $target = $guard->getProvider()->retrieveById(data_get($state, 'target'));

        if (! $this->canContinue($actor, $target, $state)) {
            Impersonation::stop();

            $this->abortUnlessExempt($request, trans('The impersonation has ended.'));

            return $next($request);
        }

        if (Config::get('impersonation.read_only', false)) {
            $this->abortUnlessExempt($request, trans('This action is not available during a read-only impersonation.'));
        }

        ImpersonationApplying::dispatch(data_get($state, 'id'), $actor, $target, data_get($state, 'guard'));

        Impersonation::apply($guard, $actor, $target);

        $response = $next($request);

        // "auth.session" stores the password hash of the current user after the
        // response. Going back to the actor here keeps the hash that the next
        // request compares against, which would otherwise log the actor out.
        Impersonation::restore();

        return $response;
    }

    /**
     * Determine if the impersonation is still valid for this request.
     */
    private function canContinue(Authenticatable $actor, ?Authenticatable $target, array $state): bool
    {
        if (is_null($target)) {
            return false;
        }

        $expiresAt = Impersonation::expiresAt();

        if (! is_null($expiresAt) && $expiresAt->lessThanOrEqualTo(Carbon::now())) {
            ImpersonationExpired::dispatch(data_get($state, 'id'), $actor, $target, data_get($state, 'guard'));

            return false;
        }

        return Impersonation::allows($actor, $target);
    }

    /**
     * Abort requests that may change data, unless the route is exempt.
     *
     * When an impersonation ends mid-request, a form that was meant for the target
     * must not run as the actor, who is usually someone with more privileges.
     */
    private function abortUnlessExempt(Request $request, string $message): void
    {
        if ($request->isMethodSafe()) {
            return;
        }

        if ($request->routeIs(...Config::get('impersonation.exempt_routes', []))) {
            return;
        }

        abort(403, $message);
    }
}
