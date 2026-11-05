<?php

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
