<?php

namespace Pijler\Impersonation;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Pijler\Impersonation\Events\ImpersonationStarted;
use Pijler\Impersonation\Events\ImpersonationStopped;
use Pijler\Impersonation\Exceptions\ImpersonationDeniedException;
use Pijler\Impersonation\Exceptions\UnsupportedGuardException;
use Pijler\Impersonation\Models\ImpersonationLog;

/**
 * The impersonation lives only in the session and in the current request, never
 * in this class, so nothing leaks between requests on workers like Octane.
 */
class Impersonation
{
    /**
     * The session key that holds the impersonation.
     */
    public const SESSION_KEY = 'impersonation';

    /**
     * The request attribute that holds the users switched on the current request.
     */
    private const REQUEST_KEY = 'impersonation.applied';

    /**
     * The fully qualified class name of the impersonation log model.
     *
     * This model is responsible for persisting the audit records in the
     * database. You can override this to use a custom implementation.
     */
    public static string $impersonationLogModel = ImpersonationLog::class;

    /**
     * Specify a custom impersonation log model class.
     */
    public static function useImpersonationLogModel(string $model): void
    {
        static::$impersonationLogModel = $model;
    }

    /**
     * Resolve a new instance of the configured impersonation log model.
     */
    public static function resolveModel(): ImpersonationLog
    {
        return resolve(static::$impersonationLogModel);
    }

    /**
     * Start impersonating the given user.
     *
     * It takes effect on the next request that goes through the middleware.
     */
    public static function start(Authenticatable $target, ?string $reason = null, ?string $guard = null): void
    {
        $name = static::guardName($guard);

        $guard = static::guard($name);

        if (static::active()) {
            throw ImpersonationDeniedException::nested();
        }

        $actor = $guard->user();

        if (is_null($actor)) {
            throw ImpersonationDeniedException::guest();
        }

        // The target is loaded through the guard's provider because that's how the
        // next requests will find it. A model of another provider could otherwise
        // be authorized as itself and then applied as the user that has its ID.
        $resolved = $guard->getProvider()->retrieveById($target->getAuthIdentifier());

        if (is_null($resolved) || ! $target instanceof $resolved) {
            throw ImpersonationDeniedException::unauthorized();
        }

        if (static::matches($actor, $resolved->getAuthIdentifier())) {
            throw ImpersonationDeniedException::self();
        }

        if (! static::allows($actor, $resolved)) {
            throw ImpersonationDeniedException::unauthorized();
        }

        Session::regenerate(true);

        Session::put(self::SESSION_KEY, [
            'guard' => $name,
            'id' => $id = Str::uuid()->toString(),
            'actor' => $actor->getAuthIdentifier(),
            'started_at' => Carbon::now()->timestamp,
            'target' => $resolved->getAuthIdentifier(),
        ]);

        ImpersonationStarted::dispatch($id, $actor, $resolved, $name, $reason);
    }

    /**
     * Stop the current impersonation, if there's one.
     */
    public static function stop(): void
    {
        $state = static::state();

        if (is_null($state)) {
            return;
        }

        $actor = static::actor();

        $target = static::target();

        static::restore();

        Session::forget(self::SESSION_KEY);

        Session::regenerate(true);

        ImpersonationStopped::dispatch(data_get($state, 'id'), $actor, $target, data_get($state, 'guard'));
    }

    /**
     * Determine if there's an impersonation in the current session.
     */
    public static function active(): bool
    {
        return ! is_null(static::state());
    }

    /**
     * Get the user who is impersonating.
     */
    public static function actor(): ?Authenticatable
    {
        return static::resolve('actor');
    }

    /**
     * Get the user being impersonated.
     */
    public static function target(): ?Authenticatable
    {
        return static::resolve('target');
    }

    /**
     * Get the moment the current impersonation expires, if it does.
     */
    public static function expiresAt(): ?Carbon
    {
        $state = static::state();

        $duration = Config::get('impersonation.max_duration');

        if (is_null($state) || is_null($duration)) {
            return null;
        }

        return Carbon::createFromTimestamp(data_get($state, 'started_at'))->addMinutes((int) $duration);
    }

    /**
     * Get the data to share with the frontend, like Inertia's shared props.
     */
    public static function data(): ?array
    {
        $state = static::state();

        if (is_null($state)) {
            return null;
        }

        return [
            'expires_at' => static::expiresAt()?->toIso8601String(),
            'read_only' => (bool) Config::get('impersonation.read_only', false),
            'actor' => static::present(static::actor(), data_get($state, 'actor')),
            'target' => static::present(static::target(), data_get($state, 'target')),
            'started_at' => Carbon::createFromTimestamp(data_get($state, 'started_at'))->toIso8601String(),
        ];
    }

    /**
     * Get the state of the current impersonation.
     */
    public static function state(): ?array
    {
        return Session::get(self::SESSION_KEY);
    }

    /**
     * Get the session guard used to impersonate.
     */
    public static function guard(?string $name = null): SessionGuard
    {
        $name = static::guardName($name);

        $guard = Auth::guard($name);

        if (! $guard instanceof SessionGuard) {
            throw UnsupportedGuardException::for($name);
        }

        return $guard;
    }

    /**
     * Determine if the actor is allowed to impersonate the target.
     */
    public static function allows(Authenticatable $actor, Authenticatable $target): bool
    {
        return Gate::forUser($actor)->allows('impersonate', [$target]);
    }

    /**
     * Determine if the user has the given identifier.
     */
    public static function matches(?Authenticatable $user, mixed $id): bool
    {
        return ! is_null($user) && (string) $user->getAuthIdentifier() === (string) $id;
    }

    /**
     * Switch the guards of the current request to the target.
     *
     * The session isn't touched, so the actor is the one logged in on the next
     * request. The default guard is switched as well when it's resolving the
     * actor through the session guard, which is what "auth:sanctum" does.
     */
    public static function apply(SessionGuard $guard, Authenticatable $actor, Authenticatable $target): void
    {
        $default = Auth::guard();

        $guards = collect([$guard])->when(
            $default !== $guard && static::matches($default->user(), $actor->getAuthIdentifier()),
            function ($guards) use ($default) {
                return $guards->push($default);
            },
        );

        // Sanctum identifies SPA requests by the token it sets on the actor. The
        // target needs it as well, or "tokenCan" and the "abilities" middleware
        // would fail on every impersonated request.
        if (method_exists($actor, 'currentAccessToken') && method_exists($target, 'withAccessToken')) {
            if (! is_null($token = $actor->currentAccessToken())) {
                $target->withAccessToken($token);
            }
        }

        $guards->each->setUser($target);

        request()->attributes->set(self::REQUEST_KEY, compact('actor', 'target', 'guards'));
    }

    /**
     * Determine if the guards were already switched on the current request.
     */
    public static function applied(): bool
    {
        return request()->attributes->has(self::REQUEST_KEY);
    }

    /**
     * Switch the guards of the current request back to the actor.
     *
     * Guards that aren't on the target anymore are left alone, because the
     * request logged the user out or logged another user in.
     */
    public static function restore(): void
    {
        $applied = request()->attributes->get(self::REQUEST_KEY);

        if (is_null($applied)) {
            return;
        }

        request()->attributes->remove(self::REQUEST_KEY);

        $id = data_get($applied, 'target')->getAuthIdentifier();

        data_get($applied, 'guards')
            ->filter(function ($guard) use ($id) {
                return $guard->hasUser() && static::matches($guard->user(), $id);
            })
            ->each->setUser(data_get($applied, 'actor'));
    }

    /**
     * Get the name of the session guard used to impersonate.
     */
    private static function guardName(?string $name): string
    {
        return $name ?? Config::get('impersonation.guard') ?? Auth::getDefaultDriver();
    }

    /**
     * Resolve one of the users of the current impersonation.
     */
    private static function resolve(string $role): ?Authenticatable
    {
        $state = static::state();

        if (is_null($state)) {
            return null;
        }

        $applied = request()->attributes->get(self::REQUEST_KEY);

        return data_get($applied, $role, function () use ($state, $role) {
            return static::guard(data_get($state, 'guard'))->getProvider()->retrieveById(data_get($state, $role));
        });
    }

    /**
     * Get the data of a user that is safe to share with the frontend.
     */
    private static function present(?Authenticatable $user, mixed $id): array
    {
        return ['id' => $id, 'name' => data_get($user, 'name')];
    }
}
