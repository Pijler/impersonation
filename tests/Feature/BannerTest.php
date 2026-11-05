<?php

use Illuminate\Support\Facades\Blade;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

test('it should not render without an impersonation', function () {
    expect(trim(Blade::render('<x-impersonation::banner />')))->toBe('');
});

test('it should render the target and the stop button', function () {
    allowImpersonation();

    login(User::factory()->create());

    Impersonation::start(User::factory()->create(['name' => 'Ana <b>Maria</b>']));

    expect(Blade::render('<x-impersonation::banner />'))
        ->toContain('You are impersonating Ana &lt;b&gt;Maria&lt;/b&gt;.')
        ->toContain(route('impersonation.stop'))
        ->toContain('name="_method" value="DELETE"');
});

test('it should render during an impersonated request', function () {
    allowImpersonation();

    login(User::factory()->create());

    Impersonation::start(User::factory()->create(['name' => 'Ana Maria']));

    nextRequest();

    $this->get('/banner')->assertOk()->assertSee('You are impersonating Ana Maria.');
});
