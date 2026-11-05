<?php

use Illuminate\Support\Facades\Route;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->create();

    $this->target = User::factory()->create();

    login($this->actor);

    nextRequest();
});

test('it should register the named routes', function () {
    expect(Route::has('impersonation.start'))->toBeTrue();

    expect(Route::has('impersonation.stop'))->toBeTrue();
});

test('it should start through the route', function () {
    allowImpersonation();

    $this->post("/impersonation/{$this->target->id}", ['reason' => 'Support ticket'])->assertRedirect('/');

    expect(Impersonation::target()->is($this->target))->toBeTrue();
});

test('it should use the configured redirect', function () {
    allowImpersonation();

    config(['impersonation.redirect' => '/dashboard']);

    $this->post("/impersonation/{$this->target->id}")->assertRedirect('/dashboard');
});

test('it should answer JSON requests without a redirect', function () {
    allowImpersonation();

    $this->postJson("/impersonation/{$this->target->id}")->assertNoContent();

    nextRequest();

    $this->deleteJson('/impersonation')->assertNoContent();
});

test('it should deny the route when the gate is not defined', function () {
    $this->post("/impersonation/{$this->target->id}")->assertForbidden();

    expect(Impersonation::active())->toBeFalse();
});

test('it should give the same answer for a user that does not exist', function () {
    allowImpersonation();

    $this->post('/impersonation/999999')->assertForbidden();
});

test('it should validate the reason', function () {
    allowImpersonation();

    $this->postJson("/impersonation/{$this->target->id}", ['reason' => str_repeat('a', 256)])
        ->assertUnprocessable();

    expect(Impersonation::active())->toBeFalse();
});

test('it should stop through the route', function () {
    allowImpersonation();

    Impersonation::start($this->target);

    nextRequest();

    $this->delete('/impersonation')->assertRedirect('/');

    expect(Impersonation::active())->toBeFalse();

    expect(auth()->id())->toBe($this->actor->id);
});

test('it should require authentication when the group does', function () {
    auth()->logout();

    nextRequest();

    $this->postJson("/impersonation/{$this->target->id}")->assertUnauthorized();
});
