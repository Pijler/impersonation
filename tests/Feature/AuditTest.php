<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pijler\Impersonation\Impersonation;
use Pijler\Impersonation\Models\ImpersonationLog;
use Workbench\App\Models\User;

beforeEach(function () {
    $this->actor = User::factory()->create();

    $this->target = User::factory()->create();

    allowImpersonation();

    login($this->actor);
});

afterEach(function () {
    Impersonation::useImpersonationLogModel(ImpersonationLog::class);
});

test('it should not record by default', function () {
    Impersonation::start($this->target);

    Impersonation::stop();

    expect(ImpersonationLog::query()->count())->toBe(0);
});

test('it should record the start', function () {
    config(['impersonation.audit' => true]);

    $this->freezeSecond();

    request()->server->set('REMOTE_ADDR', '203.0.113.10');

    Impersonation::start($this->target, 'Support ticket');

    $log = ImpersonationLog::query()->sole();

    expect($log->id)->toBe(data_get(Impersonation::state(), 'id'));

    expect($log->guard)->toBe('web');

    expect($log->reason)->toBe('Support ticket');

    expect($log->ip_address)->toBe('203.0.113.10');

    expect($log->actor_id)->toBe((string) $this->actor->id);

    expect($log->target_id)->toBe((string) $this->target->id);

    expect($log->started_at->equalTo(now()))->toBeTrue();

    expect($log->ended_at)->toBeNull();
});

test('it should record the end', function () {
    config(['impersonation.audit' => true]);

    $this->freezeSecond();

    Impersonation::start($this->target);

    $this->travel(5)->minutes();

    Impersonation::stop();

    expect(ImpersonationLog::query()->sole()->ended_at->equalTo(now()))->toBeTrue();
});

test('it should record the end when the impersonation expires', function () {
    config(['impersonation.audit' => true, 'impersonation.max_duration' => 30]);

    Impersonation::start($this->target);

    nextRequest();

    $this->travel(31)->minutes();

    $this->get('/me')->assertJsonPath('user', $this->actor->id);

    expect(ImpersonationLog::query()->sole()->ended_at)->not->toBeNull();
});

test('it should use the default model', function () {
    expect(Impersonation::resolveModel())->toBeInstanceOf(ImpersonationLog::class);
});

test('it should resolve a custom model', function () {
    Impersonation::useImpersonationLogModel(CustomImpersonationLog::class);

    expect(Impersonation::resolveModel())->toBeInstanceOf(CustomImpersonationLog::class);
});

test('it should record with a custom model', function () {
    Schema::create('custom_impersonation_logs', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('guard');
        $table->string('actor_id');
        $table->string('target_id');
        $table->string('reason')->nullable();
        $table->string('ip_address', 45)->nullable();
        $table->timestamp('started_at');
        $table->timestamp('ended_at')->nullable();
    });

    config(['impersonation.audit' => true]);

    Impersonation::useImpersonationLogModel(CustomImpersonationLog::class);

    Impersonation::start($this->target, 'Support ticket');

    expect(CustomImpersonationLog::query()->sole()->ended_at)->toBeNull();

    Impersonation::stop();

    expect(CustomImpersonationLog::query()->sole()->ended_at)->not->toBeNull();

    expect(ImpersonationLog::query()->count())->toBe(0);
});

class CustomImpersonationLog extends ImpersonationLog
{
    /**
     * The table associated with the model.
     */
    protected $table = 'custom_impersonation_logs';
}
