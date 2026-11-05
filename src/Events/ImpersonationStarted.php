<?php

namespace Pijler\Impersonation\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

class ImpersonationStarted
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
        public readonly ?string $reason = null,
    ) {}
}
