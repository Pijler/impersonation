<?php

namespace Pijler\Impersonation\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Pijler\Impersonation\Events\ImpersonationStarted;
use Pijler\Impersonation\Events\ImpersonationStopped;
use Pijler\Impersonation\Impersonation;

class RecordImpersonation
{
    /**
     * Record the start of an impersonation.
     */
    public function started(ImpersonationStarted $event): void
    {
        if (! Config::get('impersonation.audit', false)) {
            return;
        }

        Impersonation::resolveModel()::query()->create([
            'id' => $event->id,
            'guard' => $event->guard,
            'reason' => $event->reason,
            'ip_address' => Request::ip(),
            'started_at' => Carbon::now(),
            'actor_id' => $event->actor->getAuthIdentifier(),
            'target_id' => $event->target->getAuthIdentifier(),
        ]);
    }

    /**
     * Record the end of an impersonation.
     */
    public function stopped(ImpersonationStopped $event): void
    {
        if (! Config::get('impersonation.audit', false)) {
            return;
        }

        Impersonation::resolveModel()::query()->whereKey($event->id)->update(['ended_at' => Carbon::now()]);
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ImpersonationStarted::class => 'started',
            ImpersonationStopped::class => 'stopped',
        ];
    }
}
