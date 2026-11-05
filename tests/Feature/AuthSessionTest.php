<?php

use Illuminate\Support\Facades\Auth;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->create(['password' => 'actor-password']);

    $this->target = User::factory()->create(['password' => 'target-password']);

    allowImpersonation();

    login($this->actor);

    nextRequest();
});

test('it should keep the hash of the actor in the session', function (string $uri) {
    $this->get($uri)->assertOk();

    $hash = session('password_hash_web');

    Impersonation::start($this->target);

    collect(range(1, 3))->each(function () use ($uri, $hash) {
        nextRequest();

        $this->get($uri)->assertOk()->assertJsonPath('user', $this->target->id);

        expect(session('password_hash_web'))->toBe($hash);
    });

    expect(session(Auth::guard()->getName()))->toBe($this->actor->id);
})->with(['middleware listed first' => '/session', 'middleware listed last' => '/session-reversed']);

test('it should keep the actor logged in after the impersonation', function () {
    $this->get('/session')->assertOk();

    Impersonation::start($this->target);

    nextRequest();

    $this->get('/session')->assertJsonPath('user', $this->target->id);

    Impersonation::stop();

    nextRequest();

    $this->get('/session')->assertOk()->assertJsonPath('user', $this->actor->id);
});

test('it should log the actor out when their password changes', function () {
    $this->get('/session')->assertOk();

    Impersonation::start($this->target);

    $this->actor->update(['password' => 'new-password']);

    nextRequest();

    $this->get('/session')->assertRedirect('/login');

    expect(Impersonation::active())->toBeFalse();
});
