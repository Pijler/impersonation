<?php

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Pijler\Impersonation\Events\ImpersonationStarted;
use Pijler\Impersonation\Events\ImpersonationStopped;
use Pijler\Impersonation\Exceptions\ImpersonationDeniedException;
use Pijler\Impersonation\Exceptions\UnsupportedGuardException;
use Pijler\Impersonation\Impersonation;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->create();

    $this->target = User::factory()->create();
});

test('it should start impersonating', function () {
    allowImpersonation();

    login($this->actor);

    Impersonation::start($this->target);

    expect(Impersonation::active())->toBeTrue();

    expect(Impersonation::actor()->is($this->actor))->toBeTrue();

    expect(Impersonation::target()->is($this->target))->toBeTrue();
});

test('it should keep the actor authenticated when starting', function () {
    allowImpersonation();

    login($this->actor);

    Impersonation::start($this->target);

    expect(auth()->id())->toBe($this->actor->id);
});

test('it should deny by default when the gate is not defined', function () {
    login($this->actor);

    expect(function () {
        Impersonation::start($this->target);
    })->toThrow(ImpersonationDeniedException::class);

    expect(Impersonation::active())->toBeFalse();
});

test('it should pass the actor and the target to the gate', function () {
    $received = [];

    Gate::define('impersonate', function ($actor, $target) use (&$received) {
        $received = [$actor->id, $target->id];

        return true;
    });

    login($this->actor);

    Impersonation::start($this->target);

    expect($received)->toBe([$this->actor->id, $this->target->id]);
});

test('it should accept a policy instead of the gate', function () {
    Gate::policy(User::class, ImpersonationTestPolicy::class);

    login($this->actor);

    Impersonation::start($this->target);

    expect(Impersonation::active())->toBeTrue();

    Impersonation::stop();

    $protected = User::factory()->create(['email' => 'protected@example.com']);

    expect(function () use ($protected) {
        Impersonation::start($protected);
    })->toThrow(ImpersonationDeniedException::class);
});

test('it should not start when the gate denies', function () {
    Gate::define('impersonate', function () {
        return false;
    });

    login($this->actor);

    expect(function () {
        Impersonation::start($this->target);
    })->toThrow(ImpersonationDeniedException::class);

    expect(Impersonation::active())->toBeFalse();
});

test('it should not start without an authenticated user', function () {
    allowImpersonation();

    expect(function () {
        Impersonation::start($this->target);
    })->toThrow(ImpersonationDeniedException::class);
});

test('it should not allow impersonating yourself', function () {
    allowImpersonation();

    login($this->actor);

    expect(function () {
        Impersonation::start($this->actor);
    })->toThrow(ImpersonationDeniedException::class);
});

test('it should block nested impersonation', function () {
    allowImpersonation();

    login($this->actor);

    Impersonation::start($this->target);

    $state = Impersonation::state();

    expect(function () {
        Impersonation::start(User::factory()->create());
    })->toThrow(ImpersonationDeniedException::class);

    expect(Impersonation::state())->toBe($state);
});

test('it should not start for a user of another provider with the same identifier', function () {
    allowImpersonation();

    login($this->actor);

    $other = new GenericUser(['id' => $this->target->id]);

    expect(function () use ($other) {
        Impersonation::start($other);
    })->toThrow(ImpersonationDeniedException::class);
});

test('it should not start for a target that does not exist', function () {
    allowImpersonation();

    login($this->actor);

    $this->target->delete();

    expect(function () {
        Impersonation::start($this->target);
    })->toThrow(ImpersonationDeniedException::class);
});

test('it should regenerate the session ID when starting', function () {
    allowImpersonation();

    login($this->actor);

    $id = Session::getId();

    Impersonation::start($this->target);

    expect(Session::getId())->not->toBe($id);
});

test('it should regenerate the session ID when stopping', function () {
    allowImpersonation();

    login($this->actor);

    Impersonation::start($this->target);

    $id = Session::getId();

    Impersonation::stop();

    expect(Session::getId())->not->toBe($id);

    expect(Impersonation::active())->toBeFalse();
});

test('it should not touch the session when stopping without an impersonation', function () {
    Event::fake();

    login($this->actor);

    $id = Session::getId();

    Impersonation::stop();

    expect(Session::getId())->toBe($id);

    Event::assertNotDispatched(ImpersonationStopped::class);
});

test('it should dispatch the started event', function () {
    Event::fake([ImpersonationStarted::class]);

    allowImpersonation();

    login($this->actor);

    Impersonation::start($this->target, 'Support ticket');

    Event::assertDispatched(function (ImpersonationStarted $event) {
        return $event->actor->is($this->actor)
            && $event->target->is($this->target)
            && $event->guard === 'web'
            && $event->reason === 'Support ticket'
            && $event->id === data_get(Impersonation::state(), 'id');
    });
});

test('it should dispatch the stopped event', function () {
    Event::fake([ImpersonationStopped::class]);

    allowImpersonation();

    login($this->actor);

    Impersonation::start($this->target);

    $id = data_get(Impersonation::state(), 'id');

    Impersonation::stop();

    Event::assertDispatched(function (ImpersonationStopped $event) use ($id) {
        return $event->id === $id
            && $event->guard === 'web'
            && $event->actor->is($this->actor)
            && $event->target->is($this->target);
    });
});

test('it should reject guards that are not session based', function () {
    allowImpersonation();

    login($this->actor);

    expect(function () {
        Impersonation::start($this->target, guard: 'sanctum');
    })->toThrow(UnsupportedGuardException::class);
});

test('it should return no users without an impersonation', function () {
    expect(Impersonation::active())->toBeFalse();

    expect(Impersonation::actor())->toBeNull();

    expect(Impersonation::target())->toBeNull();

    expect(Impersonation::data())->toBeNull();
});

test('it should share only the safe data', function () {
    config(['impersonation.max_duration' => 30, 'impersonation.read_only' => true]);

    $this->freezeSecond();

    allowImpersonation();

    login($this->actor);

    Impersonation::start($this->target);

    expect(Impersonation::data())->toEqual([
        'actor' => ['id' => $this->actor->id, 'name' => $this->actor->name],
        'target' => ['id' => $this->target->id, 'name' => $this->target->name],
        'read_only' => true,
        'expires_at' => now()->addMinutes(30)->toIso8601String(),
        'started_at' => now()->toIso8601String(),
    ]);
});

class ImpersonationTestPolicy
{
    /**
     * Determine if the actor can impersonate the target.
     */
    public function impersonate(User $actor, User $target): bool
    {
        return $target->email !== 'protected@example.com';
    }
}
