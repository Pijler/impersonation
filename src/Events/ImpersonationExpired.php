<?php

namespace Pijler\Impersonation\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when the maximum duration is reached, before ImpersonationStopped.
 */
class ImpersonationExpired
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
