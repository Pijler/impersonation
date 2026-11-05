<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

Route::get('/', function () {
    return response()->json(['ok' => true]);
});

Route::get('/login', function () {
    return response()->json(['ok' => true]);
})->name('login');

Route::post('/logout', function (Request $request) {
    auth()->logout();

    $request->session()->invalidate();

    return response()->json(['user' => auth()->id()]);
});

Route::middleware(['auth', 'impersonation'])->group(function () {
    $user = function (Request $request) {
        return response()->json([
            'user' => $request->user()->getKey(),
            'actor' => Impersonation::actor()?->getAuthIdentifier(),
            'target' => Impersonation::target()?->getAuthIdentifier(),
        ]);
    };

    Route::get('/me', $user);

    Route::post('/me', $user);

    Route::get('/session', $user)->middleware('auth.session');

    Route::get('/protected', $user)->middleware('impersonation.protect');

    Route::get('/can-see/{user}', function (User $user) {
        return response()->json(['user' => $user->getKey()]);
    })
        ->middleware('can:view,user');

    Route::post('/stop', function (Request $request) {
        Impersonation::stop();

        return response()->json(['user' => $request->user()->getKey()]);
    });

    Route::post('/logout-impersonated', function (Request $request) {
        auth()->logout();

        return response()->json(['user' => auth()->id()]);
    });

    Route::get('/banner', function () {
        return Blade::render('<x-impersonation::banner />');
    });

    Route::impersonation();
});

Route::middleware(['auth', 'impersonation', 'auth.session'])
    ->withoutMiddleware(SubstituteBindings::class)
    ->get('/session-reversed', function (Request $request) {
        return response()->json(['user' => $request->user()->getKey()]);
    });

Route::bind('current', function () {
    return auth()->id();
});

Route::middleware(['auth', 'impersonation'])->get('/binding/{current}', function (string $current) {
    return response()->json(['user' => (int) $current]);
});

Route::middleware(['auth', 'can:view,user', 'impersonation'])->get('/can-see-reversed/{user}', function (User $user) {
    return response()->json(['user' => $user->getKey()]);
});

Route::middleware(['auth:sanctum', 'impersonation'])->prefix('/spa')->group(function () {
    Route::get('/me', function (Request $request) {
        return response()->json([
            'user' => $request->user()->getKey(),
            'token' => $request->user()->tokenCan('anything'),
        ]);
    });

    Route::get('/stop', function (Request $request) {
        Impersonation::stop();

        return response()->json(['user' => $request->user()->getKey()]);
    });
});
