<?php

use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->create();

    allowImpersonation();

    login($this->actor);

    nextRequest();
});

test('it should allow the route without an impersonation', function () {
    $this->get('/protected')->assertOk();
});

test('it should return 403 during an impersonation', function () {
    Impersonation::start(User::factory()->create());

    nextRequest();

    $this->get('/protected')->assertForbidden();
});
