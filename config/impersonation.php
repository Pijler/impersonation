<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    |
    | The session guard used to impersonate. When null, the default guard is
    | used. Set it explicitly when routes change the default guard to one that
    | isn't session based, like "auth:sanctum" does ("web" for Sanctum SPAs).
    |
    */

    'guard' => null,

    /*
    |--------------------------------------------------------------------------
    | Maximum Duration
    |--------------------------------------------------------------------------
    |
    | The maximum duration of an impersonation, in minutes. When it's reached,
    | the impersonation ends and the ImpersonationExpired event is dispatched.
    | Use null for no limit.
    |
    */

    'max_duration' => null,

    /*
    |--------------------------------------------------------------------------
    | Read-Only Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, requests that may change data (any method other than GET,
    | HEAD and OPTIONS) receive a 403 response during an impersonation.
    |
    */

    'read_only' => false,

    /*
    |--------------------------------------------------------------------------
    | Exempt Routes
    |--------------------------------------------------------------------------
    |
    | The names of the routes that accept unsafe methods when the read-only mode
    | is enabled or when the impersonation has just ended. Your route that stops
    | the impersonation must be listed here if it's not the package's one.
    |
    */

    'exempt_routes' => ['impersonation.stop'],

    /*
    |--------------------------------------------------------------------------
    | Redirect
    |--------------------------------------------------------------------------
    |
    | Where the routes registered by Route::impersonation() redirect after they
    | start or stop an impersonation.
    |
    */

    'redirect' => '/',

    /*
    |--------------------------------------------------------------------------
    | Audit
    |--------------------------------------------------------------------------
    |
    | When enabled, every impersonation is recorded on the "impersonation_logs"
    | table. Publish the migration with the "impersonation-migrations" tag first.
    |
    */

    'audit' => false,

];
