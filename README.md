<p align="center">
<img src="https://raw.githubusercontent.com/Pijler/docs/main/assets/pijler-icon-dark.svg" height="100" alt="Pijler logo">
</p>

<h2><p align="center">Impersonation</p></h2>

### 🚀 Introduction

Secure user impersonation for Laravel, with per-request authorization, audit events, and session time limits.

The actor stays logged in on the session, and the target only applies for the duration of each request. Permission is checked again on every request, so revoking it ends the impersonation right away.

> [!WARNING]
> This package is under development and has no stable release yet.

### 📦 Installation

```bash
composer require pijler/impersonation
```

Add the middleware to the routes that should run as the target. In most applications, that's the `web` group:

```php
// bootstrap/app.php
use Pijler\Impersonation\Http\Middleware\HandleImpersonation;

->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [HandleImpersonation::class]);
})
```

It's also available as the `impersonation` route middleware. The package sets its priority, so it always runs after `auth` and `auth.session`, and before route bindings and `can`.

Then define who can impersonate whom. **Without this gate, every impersonation is denied.**

```php
// app/Providers/AppServiceProvider.php
Gate::define('impersonate', function (User $actor, User $target) {
    return $actor->is_admin && ! $target->is_admin;
});
```

An `impersonate` method on the model's policy works as well.

To publish the config file:

```bash
php artisan vendor:publish --tag=impersonation-config
```

### 🎭 Usage

```php
use Pijler\Impersonation\Impersonation;

Impersonation::start($user, reason: 'Support ticket #123');

Impersonation::stop();

Impersonation::active(); // bool
Impersonation::actor();  // The user who is impersonating
Impersonation::target(); // The user being impersonated
```

`start` takes effect on the next request, so redirect after calling it. It throws `ImpersonationDeniedException`, rendered as a 403, when:

- The gate denies, or the target doesn't belong to the guard's provider.
- The actor is trying to impersonate themselves.
- There's already an impersonation in the session.

The impersonation ends by itself when the target doesn't exist anymore, the gate stops allowing it, the time limit is reached, or the user logs out. When this happens, safe requests continue as the actor, while requests that may change data receive a 403 so that a form meant for the target never runs as the actor.

The session ID is regenerated when the impersonation starts and ends.

#### Routes

`Route::impersonation()` registers `POST /impersonation/{target}` (`impersonation.start`, with an optional `reason` field) and `DELETE /impersonation` (`impersonation.stop`). Register it inside your own middleware group:

```php
Route::middleware(['web', 'auth'])->group(function () {
    Route::impersonation();
});
```

They redirect to the `redirect` config option, or answer with a 204 to requests that expect JSON.

#### Protecting routes

Use `impersonation.protect` on the routes that can't be accessed during an impersonation, like changing a password or deleting the account. They return a 403.

```php
Route::put('/password', UpdatePasswordController::class)->middleware('impersonation.protect');
```

> [!IMPORTANT]
> Protect your logout route as well, or ask the actor to stop first. Laravel's `Auth::logout()` runs on the current user, so during an impersonation it rotates the **target's** remember token, which signs the target out of their remembered devices.

#### Time limit

Set `max_duration`, in minutes, to end impersonations automatically. The `ImpersonationExpired` event is dispatched when it's reached, followed by `ImpersonationStopped`.

#### Read-only mode

Set `read_only` to `true` to return a 403 for any method other than `GET`, `HEAD` and `OPTIONS` during an impersonation. The routes in `exempt_routes` are still reachable, and the package's stop route is listed by default. **If you stop the impersonation on your own route, add it to that list.**

#### Audit

To record every impersonation (actor, target, reason, IP address, start and end), publish the migration and enable the `audit` option:

```bash
php artisan vendor:publish --tag=impersonation-migrations
php artisan migrate
```

The records are available through the `Pijler\Impersonation\Models\ImpersonationLog` model. `ended_at` stays empty when the session expires before the impersonation ends.

To use your own model, extend it and register it on a service provider. To use another table, set `$table` on your model and change the published migration.

```php
use Pijler\Impersonation\Impersonation;

public function boot(): void
{
    Impersonation::useImpersonationLogModel(CustomImpersonationLog::class);
}
```

#### Interface

For Blade, add the banner to your layout. It shows who is being impersonated and, when `Route::impersonation()` is registered, a button to stop.

```blade
<x-impersonation::banner />
```

To customize it, publish the view with the `impersonation-views` tag.

For Inertia, share the data as a closure, so that it's only evaluated after the middleware runs. It returns `null` when there's no impersonation, and only exposes the ID and the name of the users.

```php
Inertia::share('impersonation', function () {
    return Impersonation::data();
});
```

### 🔔 Events

| Event                   | Dispatched                                                    |
| ----------------------- | ------------------------------------------------------------- |
| `ImpersonationStarted`  | When an impersonation starts. Includes the reason.            |
| `ImpersonationApplying` | On every request, right before the guard switches users.      |
| `ImpersonationExpired`  | When the time limit is reached, before `ImpersonationStopped`. |
| `ImpersonationStopped`  | Whenever an impersonation ends, for any reason.               |

They all have `id`, `actor`, `target` and `guard` properties. On `ImpersonationStopped`, `actor` and `target` are `null` when the user doesn't exist anymore.

#### Ignoring the switch on your listeners

Switching users dispatches Laravel's `Authenticated` event, so listeners that treat it as a login should use `ImpersonationApplying` to ignore it. For [`pijler/user-devices`](https://github.com/Pijler/user-devices):

```php
Event::listen(ImpersonationApplying::class, function () {
    DeviceCreator::ignoreListener();
});
```

### 🛡️ Guards

The default guard is used, and it must be a session guard. To use another one, set the `guard` config option or pass it when starting:

```php
Impersonation::start($user, guard: 'admin');
```

There's a single impersonation per session. **The actor and the target must belong to the same guard**, so an `Admin` on the `admin` guard can't impersonate a `User` on the `web` guard.

#### Sanctum SPA

`auth:sanctum` changes the default guard to one that isn't session based, so set the session guard explicitly:

```php
// config/impersonation.php
'guard' => 'web',
```

Add the middleware to the routes that use `auth:sanctum`. `tokenCan` and the `abilities` middleware keep working for the target. Requests authenticated with an API token are never impersonated, because they don't use the session.

### ⚡ Octane

The impersonation is kept only in the session and in the current request, never in static properties or singletons, so nothing leaks between requests. The only static property holds the model class, which is set once when the application boots.

### ⚠️ Known limitations

- **`auth.session`:** the middleware switches back to the actor after the response is created, so that `auth.session` keeps the actor's password hash and doesn't log them out. As a result, **`StreamedResponse` callbacks run as the actor**, because they run after the middleware. Don't use the authenticated user inside them on routes that are reachable during an impersonation.
- **`password.confirm`:** the confirmation is checked against the target's password, which the actor doesn't know. Consider protecting these routes with `impersonation.protect`.

### 🔄 Migrating from `pijler/laravel-common`

| `pijler/laravel-common`                                | `pijler/impersonation`                       |
| ------------------------------------------------------ | -------------------------------------------- |
| `impersonate` middleware                               | `impersonation` middleware                   |
| `protect.impersonate` middleware                       | `impersonation.protect` middleware           |
| `canImpersonate` and `canBeImpersonated` gates         | `impersonate` gate                           |
| `HasImpersonate` trait                                 | Not needed                                   |
| `$user->impersonate()`                                 | `Impersonation::start($user)`                |
| `$user->stopImpersonated()`                            | `Impersonation::stop()`                      |
| `$user->isImpersonated()`                              | `Impersonation::active()`                    |
| `Session::get('session::super::user')`                 | `Impersonation::actor()`                     |
| `Session::get('session::user::impersonate')`           | `Impersonation::target()`                    |

The two gates become one that receives both users:

```php
Gate::define('impersonate', function (User $actor, User $target) {
    return Gate::forUser($actor)->allows('canImpersonate')
        && Gate::forUser($target)->allows('canBeImpersonated');
});
```

Keep in mind that:

- **`protect.impersonate` redirected back, while `impersonation.protect` returns a 403.** Handle the 403 if your interface relied on the redirect.
- **`$user->impersonate()` silently did nothing when denied, while `start` throws an exception.**
- **`start` needs an authenticated actor who passes the gate**, and nested impersonation is blocked.
- **`DeviceCreator::ignoreListener()` isn't called automatically anymore.** See [Ignoring the switch on your listeners](#ignoring-the-switch-on-your-listeners).
- **Impersonations in progress aren't carried over**, because the session keys changed. They need to be started again.

### 🧪 Testing

```bash
composer test
```

### 📝 License

Open-source under the [MIT license](LICENSE).

### 🚀 Thanks!
