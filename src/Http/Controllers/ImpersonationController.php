<?php

namespace Pijler\Impersonation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Pijler\Impersonation\Exceptions\ImpersonationDeniedException;
use Pijler\Impersonation\Impersonation;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationController
{
    /**
     * Start impersonating the given user.
     */
    public function start(Request $request, string $target): Response
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $user = Impersonation::guard()->getProvider()->retrieveById($target);

        // The same answer as a forbidden target, so the route can't be used to
        // find out which users exist.
        if (is_null($user)) {
            throw ImpersonationDeniedException::unauthorized();
        }

        Impersonation::start($user, data_get($data, 'reason'));

        return $this->response($request);
    }

    /**
     * Stop the current impersonation.
     */
    public function stop(Request $request): Response
    {
        Impersonation::stop();

        return $this->response($request);
    }

    /**
     * Create the response for the given request.
     */
    private function response(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->to(Config::get('impersonation.redirect', '/'));
    }
}
