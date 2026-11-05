<?php

namespace Pijler\Impersonation\Listeners;

use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\Events\Logout;
use Pijler\Impersonation\Impersonation;

class EndImpersonationOnLogout
{
    /**
     * Handle the event.
     */
    public function handle(CurrentDeviceLogout|Logout $event): void
    {
        if (data_get(Impersonation::state(), 'guard') === $event->guard) {
            Impersonation::stop();
        }
    }
}
