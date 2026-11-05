<?php

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Pijler\Impersonation\Events\ImpersonationApplying;
use Pijler\Impersonation\Events\ImpersonationExpired;
use Pijler\Impersonation\Events\ImpersonationStopped;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->create();

    $this->target = User::factory()->create();

    allowImpersonation();

    login($this->actor);

    nextRequest();
});

/**
 * Start the impersonation as the actor, like a previous request would.
 */
function impersonate(User $target): void
{
    Impersonation::start($target);

    nextRequest();
}

test('it should run the request as the target', function () {
    impersonate($this->target);

    $this->get('/me')->assertOk()->assertExactJson([
        'user' => $this->target->id,
        'actor' => $this->actor->id,
        'target' => $this->target->id,
    ]);
});

test('it should not change the user without an impersonation', function () {
    $this->get('/me')->assertOk()->assertJsonPath('user', $this->actor->id);
});

test('it should keep the actor in the session across requests', function () {
    impersonate($this->target);

    $this->get('/me')->assertJsonPath('user', $this->target->id);

    expect(session(auth()->guard()->getName()))->toBe($this->actor->id);

    nextRequest();

    $this->get('/me')->assertJsonPath('user', $this->target->id);
});

test('it should give the actor back after the request', function () {
    impersonate($this->target);

    $this->get('/me');

    expect(auth()->id())->toBe($this->actor->id);

    expect(Impersonation::applied())->toBeFalse();
});

test('it should run route bindings and authorization as the target', function () {
    Gate::define('view', function ($user, $model) {
        return $user->is($model);
    });

    impersonate($this->target);

    $this->get("/can-see/{$this->target->id}")->assertOk();

    nextRequest();

    $this->get("/can-see/{$this->actor->id}")->assertForbidden();
});

test('it should resolve route bindings as the target', function () {
    impersonate($this->target);

    $this->get('/binding/any')->assertOk()->assertJsonPath('user', $this->target->id);
});

test('it should authorize as the target wherever the middleware is listed', function () {
    Gate::define('view', function ($user, $model) {
        return $user->is($model);
    });

    impersonate($this->target);

    $this->get("/can-see-reversed/{$this->target->id}")->assertOk();

    nextRequest();

    $this->get("/can-see-reversed/{$this->actor->id}")->assertForbidden();
});

test('it should check the permission again on every request', function () {
    impersonate($this->target);

    $this->get('/me')->assertJsonPath('user', $this->target->id);

    Gate::define('impersonate', function () {
        return false;
    });

    nextRequest();

    $this->get('/me')->assertJsonPath('user', $this->actor->id);

    expect(Impersonation::active())->toBeFalse();
});

test('it should end when the target does not exist anymore', function () {
    Event::fake([ImpersonationStopped::class]);

    impersonate($this->target);

    $this->target->delete();

    $this->get('/me')->assertOk()->assertJsonPath('user', $this->actor->id);

    expect(Impersonation::active())->toBeFalse();

    Event::assertDispatched(function (ImpersonationStopped $event) {
        return is_null($event->target) && $event->actor->is($this->actor);
    });
});

test('it should not run unsafe requests as the actor when the impersonation ends', function () {
    impersonate($this->target);

    Gate::define('impersonate', function () {
        return false;
    });

    $this->post('/me')->assertForbidden();

    expect(Impersonation::active())->toBeFalse();
});

test('it should end when the session belongs to another user', function () {
    impersonate($this->target);

    $other = User::factory()->create();

    login($other);

    nextRequest();

    $this->get('/me')->assertJsonPath('user', $other->id);

    expect(Impersonation::active())->toBeFalse();
});

test('it should dispatch the applying event before switching the user', function () {
    $users = [];

    Event::listen(ImpersonationApplying::class, function (ImpersonationApplying $event) use (&$users) {
        data_set($users, 'actor', $event->actor->id);

        data_set($users, 'target', $event->target->id);

        data_set($users, 'current', auth()->id());
    });

    $authenticated = [];

    Event::listen(Authenticated::class, function (Authenticated $event) use (&$authenticated, &$users) {
        $authenticated[] = [$event->user->id, filled($users)];
    });

    impersonate($this->target);

    $this->get('/me')->assertOk();

    expect($users)->toBe([
        'actor' => $this->actor->id,
        'target' => $this->target->id,
        'current' => $this->actor->id,
    ]);

    expect($authenticated)->toContain([$this->target->id, true]);
});

test('it should stop in the middle of a request and answer as the actor', function () {
    impersonate($this->target);

    $this->post('/stop')->assertOk()->assertJsonPath('user', $this->actor->id);

    expect(Impersonation::active())->toBeFalse();

    nextRequest();

    $this->get('/me')->assertJsonPath('user', $this->actor->id);
});

test('it should end when logging out in an impersonated request', function () {
    Event::fake([ImpersonationStopped::class]);

    impersonate($this->target);

    $this->post('/logout-impersonated')->assertOk()->assertJsonPath('user', null);

    expect(Impersonation::active())->toBeFalse();

    expect(auth()->check())->toBeFalse();

    Event::assertDispatchedTimes(ImpersonationStopped::class);
});

test('it should end when logging out without the middleware', function () {
    Event::fake([ImpersonationStopped::class]);

    impersonate($this->target);

    $this->post('/logout')->assertOk();

    Event::assertDispatchedTimes(ImpersonationStopped::class);
});

test('it should not keep anything between requests after stopping', function () {
    impersonate($this->target);

    $this->get('/me');

    Impersonation::stop();

    nextRequest();

    $this->get('/me')->assertExactJson([
        'user' => $this->actor->id,
        'actor' => null,
        'target' => null,
    ]);
});

test('it should expire after the maximum duration', function () {
    Event::fake([ImpersonationExpired::class, ImpersonationStopped::class]);

    config(['impersonation.max_duration' => 30]);

    impersonate($this->target);

    $this->travel(29)->minutes();

    $this->get('/me')->assertJsonPath('user', $this->target->id);

    Event::assertNotDispatched(ImpersonationExpired::class);

    $this->travel(1)->minutes();

    nextRequest();

    $this->get('/me')->assertJsonPath('user', $this->actor->id);

    expect(Impersonation::active())->toBeFalse();

    Event::assertDispatched(ImpersonationExpired::class);

    Event::assertDispatched(ImpersonationStopped::class);
});

test('it should not expire without a maximum duration', function () {
    impersonate($this->target);

    $this->travel(10)->years();

    $this->get('/me')->assertJsonPath('user', $this->target->id);
});

test('it should block unsafe methods in read-only mode', function () {
    config(['impersonation.read_only' => true]);

    impersonate($this->target);

    $this->post('/me')->assertForbidden();

    nextRequest();

    $this->get('/me')->assertOk()->assertJsonPath('user', $this->target->id);

    expect(Impersonation::active())->toBeTrue();
});

test('it should not block unsafe methods in read-only mode without an impersonation', function () {
    config(['impersonation.read_only' => true]);

    $this->post('/me')->assertOk();
});

test('it should allow stopping in read-only mode', function () {
    config(['impersonation.read_only' => true]);

    impersonate($this->target);

    $this->delete('/impersonation')->assertRedirect('/');

    expect(Impersonation::active())->toBeFalse();
});

test('it should allow stopping after the impersonation expired', function () {
    config(['impersonation.max_duration' => 30]);

    impersonate($this->target);

    $this->travel(31)->minutes();

    $this->delete('/impersonation')->assertRedirect('/');

    expect(Impersonation::active())->toBeFalse();
});
