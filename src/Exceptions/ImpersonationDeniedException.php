<?php

namespace Pijler\Impersonation\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class ImpersonationDeniedException extends AuthorizationException
{
    /**
     * Create an exception for a request without an authenticated user.
     */
    public static function guest(): self
    {
        return new self(trans('You must be logged in to impersonate a user.'));
    }

    /**
     * Create an exception for an attempt to impersonate during an impersonation.
     */
    public static function nested(): self
    {
        return new self(trans('You cannot impersonate a user during an impersonation.'));
    }

    /**
     * Create an exception for an attempt to impersonate yourself.
     */
    public static function self(): self
    {
        return new self(trans('You cannot impersonate yourself.'));
    }

    /**
     * Create an exception for a target the actor can't impersonate.
     */
    public static function unauthorized(): self
    {
        return new self(trans('You are not allowed to impersonate this user.'));
    }
}
