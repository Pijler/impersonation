<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\Sanctum;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

beforeEach(function () {
    config(['impersonation.guard' => 'web']);

    $this->actor = User::factory()->create();

    $this->target = User::factory()->create();

    allowImpersonation();

    login($this->actor);

    nextRequest();
});

test('it should run SPA requests as the target', function () {
    Impersonation::start($this->target);

    nextRequest();

    $this->getJson('/spa/me')->assertOk()->assertExactJson([
        'user' => $this->target->id,
        'token' => true,
    ]);
});

test('it should start from a route that uses the sanctum guard', function () {
    $this->getJson('/spa/me')->assertJsonPath('user', $this->actor->id);

    Impersonation::start($this->target);

    expect(data_get(Impersonation::state(), 'guard'))->toBe('web');
});

test('it should stop in the middle of a SPA request and answer as the actor', function () {
    Impersonation::start($this->target);

    nextRequest();

    $this->getJson('/spa/stop')->assertOk()->assertJsonPath('user', $this->actor->id);
});

test('it should not apply to requests authenticated by an API token', function () {
    Impersonation::start($this->target);

    auth('web')->logout();

    nextRequest();

    Sanctum::actingAs($other = User::factory()->create());

    $this->getJson('/spa/me')->assertOk()->assertJsonPath('user', $other->id);
});

test('it should work with the abilities middleware', function () {
    Route::middleware(['web', 'auth:sanctum', 'impersonation', CheckAbilities::class.':anything'])
        ->get('/spa/abilities', function (Request $request) {
            return response()->json(['user' => $request->user()->getKey()]);
        });

    Impersonation::start($this->target);

    nextRequest();

    $this->getJson('/spa/abilities')->assertOk()->assertJsonPath('user', $this->target->id);
});

test('it should work with the stateful middleware and keep the hash of the actor', function () {
    config(['sanctum.middleware.authenticate_session' => AuthenticateSession::class]);

    Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum', 'impersonation'])
        ->get('/spa/stateful', function (Request $request) {
            return response()->json(['user' => $request->user()->getKey()]);
        });

    $this->withHeader('Referer', 'http://localhost');

    $this->getJson('/spa/stateful')->assertOk()->assertJsonPath('user', $this->actor->id);

    $hash = session('password_hash_web');

    expect($hash)->not->toBeNull();

    Impersonation::start($this->target);

    collect(range(1, 3))->each(function () use ($hash) {
        nextRequest();

        $this->getJson('/spa/stateful')->assertOk()->assertJsonPath('user', $this->target->id);

        expect(session('password_hash_web'))->toBe($hash);
    });

    Impersonation::stop();

    nextRequest();

    $this->getJson('/spa/stateful')->assertOk()->assertJsonPath('user', $this->actor->id);
});
