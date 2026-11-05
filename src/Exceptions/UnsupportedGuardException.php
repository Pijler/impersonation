<?php

namespace Pijler\Impersonation\Exceptions;

use InvalidArgumentException;

class UnsupportedGuardException extends InvalidArgumentException
{
    /**
     * Create an exception for a guard that isn't session based.
     */
    public static function for(string $guard): self
    {
        return new self(
            "The [{$guard}] guard can't be used to impersonate because it isn't a session guard. ".
            'Set the "impersonation.guard" config option to a session guard.'
        );
    }
}
