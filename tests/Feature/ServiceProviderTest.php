<?php

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use Pijler\Impersonation\Http\Middleware\HandleImpersonation;
use Pijler\Impersonation\Http\Middleware\ProtectFromImpersonation;
use Pijler\Impersonation\ServiceProvider;

test('it should register the service provider', function () {
    expect(app()->getProvider(ServiceProvider::class))->toBeInstanceOf(ServiceProvider::class);
});

test('it should register the middleware aliases', function () {
    expect(app('router')->getMiddleware())->toMatchArray([
        'impersonation' => HandleImpersonation::class,
        'impersonation.protect' => ProtectFromImpersonation::class,
    ]);
});

test('it should publish the package files', function (string $tag) {
    expect(LaravelServiceProvider::pathsToPublish(ServiceProvider::class, $tag))->not->toBeEmpty();
})->with(['impersonation-config', 'impersonation-views', 'impersonation-migrations']);
