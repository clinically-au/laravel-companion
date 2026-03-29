<?php

declare(strict_types=1);

use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Models\CompanionAuditLog;
use Clinically\Companion\Services\AuditService;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->service = new AuditService;
    $this->agent = CompanionAgent::create([
        'name' => 'Test',
        'token_hash' => hash('sha256', 'audit-test'),
        'token_prefix' => 'cmp_audt',
        'scopes' => ['*'],
    ]);
});

it('skips logging when audit is disabled', function () {
    config()->set('companion.audit.enabled', false);

    $request = Request::create('/companion/api/ping', 'GET');
    $this->service->log($this->agent, $request, 200, 10);

    expect(CompanionAuditLog::count())->toBe(0);
});

it('skips logging reads when log_reads is false', function () {
    config()->set('companion.audit.enabled', true);
    config()->set('companion.audit.log_reads', false);

    $request = Request::create('/companion/api/ping', 'GET');
    $this->service->log($this->agent, $request, 200, 10);

    expect(CompanionAuditLog::count())->toBe(0);
});

it('logs write requests', function () {
    config()->set('companion.audit.enabled', true);
    config()->set('companion.audit.log_reads', false);

    $request = Request::create('/companion/api/cache/flush', 'POST', ['confirm' => true]);
    $this->service->log($this->agent, $request, 200, 50);

    expect(CompanionAuditLog::count())->toBe(1);
    expect(CompanionAuditLog::first()->method)->toBe('POST');
});

it('sanitises sensitive keys in payload', function () {
    config()->set('companion.audit.enabled', true);

    $request = Request::create('/companion/api/test', 'POST', [
        'name' => 'visible',
        'api_token' => 'should-be-redacted',
        'current_password' => 'should-be-redacted',
    ]);
    $this->service->log($this->agent, $request, 200, 10);

    $log = CompanionAuditLog::first();
    expect($log->payload['name'])->toBe('visible');
    expect($log->payload['api_token'])->toBe('********');
    expect($log->payload['current_password'])->toBe('********');
});
