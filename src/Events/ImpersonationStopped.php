<?php

namespace Pijler\Impersonation\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched whenever an impersonation ends, for any reason.
 *
 * The actor or the target are null when they don't exist anymore.
 */
class ImpersonationStopped
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?Authenticatable $actor,
        public readonly ?Authenticatable $target,
        public readonly string $guard,
    ) {}
}
