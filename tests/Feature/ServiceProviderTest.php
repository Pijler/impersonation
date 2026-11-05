<?php

use Pijler\Impersonation\ServiceProvider;

test('it should register the service provider', function () {
    expect(app()->getProvider(ServiceProvider::class))->toBeInstanceOf(ServiceProvider::class);
});
