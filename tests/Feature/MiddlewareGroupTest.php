<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Pijler\Impersonation\Http\Middleware\HandleImpersonation;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

beforeEach(function () {
    app(Kernel::class)->appendMiddlewareToGroup('web', HandleImpersonation::class);

    Route::bind('appended', function () {
        return auth()->id();
    });

    Route::middleware(['web', 'auth', 'auth.session'])->group(function () {
        Route::get('/group/me', function () {
            return response()->json(['user' => auth()->id()]);
        });

        Route::get('/group/binding/{appended}', function (string $appended) {
            return response()->json(['user' => (int) $appended]);
        });

        Route::get('/group/can/{user}', function (User $user) {
            return response()->json(['user' => $user->getKey()]);
        })->middleware('can:view,user');
    });

    $this->actor = User::factory()->create(['password' => 'actor-password']);

    $this->target = User::factory()->create(['password' => 'target-password']);

    allowImpersonation();

    login($this->actor);

    nextRequest();
});

test('it should run the request as the target when appended to the web group', function () {
    Impersonation::start($this->target);

    nextRequest();

    $this->get('/group/me')->assertOk()->assertJsonPath('user', $this->target->id);
});

test('it should keep the hash of the actor when appended to the web group', function () {
    $this->get('/group/me')->assertOk();

    $hash = session('password_hash_web');

    Impersonation::start($this->target);

    collect(range(1, 3))->each(function () use ($hash) {
        nextRequest();

        $this->get('/group/me')->assertOk()->assertJsonPath('user', $this->target->id);

        expect(session('password_hash_web'))->toBe($hash);
    });
});

test('it should resolve route bindings as the target when appended to the web group', function () {
    Impersonation::start($this->target);

    nextRequest();

    $this->get('/group/binding/any')->assertOk()->assertJsonPath('user', $this->target->id);
});

test('it should authorize as the target when appended to the web group', function () {
    Gate::define('view', function ($user, $model) {
        return $user->is($model);
    });

    Impersonation::start($this->target);

    nextRequest();

    $this->get("/group/can/{$this->target->id}")->assertOk();

    nextRequest();

    $this->get("/group/can/{$this->actor->id}")->assertForbidden();
});
