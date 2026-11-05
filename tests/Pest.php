<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Allow every user to impersonate any other user.
 */
function allowImpersonation(): void
{
    Gate::define('impersonate', function () {
        return true;
    });
}

/**
 * Log the user in through the session, like a real login does.
 */
function login(Authenticatable $user, string $guard = 'web'): void
{
    Auth::guard($guard)->login($user);
}

/**
 * Forget the resolved guards, so the next request reads the user from the session.
 */
function nextRequest(): void
{
    Auth::forgetGuards();
}
