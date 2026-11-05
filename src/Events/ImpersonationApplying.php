<?php

namespace Pijler\Impersonation\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched on every request, right before the guard switches to the target.
 *
 * The switch fires Laravel's Authenticated event, so listeners that shouldn't
 * treat it as a real login can use this event to ignore it.
 */
class ImpersonationApplying
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $id,
        public readonly Authenticatable $actor,
        public readonly Authenticatable $target,
        public readonly string $guard,
    ) {}
}
