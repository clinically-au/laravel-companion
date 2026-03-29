<?php

declare(strict_types=1);

use Clinically\Companion\Models\CompanionAgent;

it('detects active agents', function () {
    $agent = CompanionAgent::create([
        'name' => 'Test',
        'token_hash' => hash('sha256', 'test'),
        'token_prefix' => 'cmp_test1234',
        'scopes' => ['*'],
    ]);

    expect($agent->isActive())->toBeTrue();
    expect($agent->isRevoked())->toBeFalse();
    expect($agent->isExpired())->toBeFalse();
});

it('detects revoked agents', function () {
    $agent = CompanionAgent::create([
        'name' => 'Test',
        'token_hash' => hash('sha256', 'test2'),
        'token_prefix' => 'cmp_test5678',
        'scopes' => ['*'],
        'revoked_at' => now(),
    ]);

    expect($agent->isActive())->toBeFalse();
    expect($agent->isRevoked())->toBeTrue();
});

it('detects expired agents', function () {
    $agent = CompanionAgent::create([
        'name' => 'Test',
        'token_hash' => hash('sha256', 'test3'),
        'token_prefix' => 'cmp_test9012',
        'scopes' => ['*'],
        'expires_at' => now()->subDay(),
    ]);

    expect($agent->isActive())->toBeFalse();
    expect($agent->isExpired())->toBeTrue();
});

describe('scope checking', function () {
    it('checks direct scope', function () {
        $agent = CompanionAgent::create([
            'name' => 'Test',
            'token_hash' => hash('sha256', 'test4'),
            'token_prefix' => 'cmp_testscop',
            'scopes' => ['models:read', 'routes:read'],
        ]);

        expect($agent->hasScope('models:read'))->toBeTrue();
        expect($agent->hasScope('models:browse'))->toBeFalse();
    });

    it('supports wildcard scope', function () {
        $agent = CompanionAgent::create([
            'name' => 'Admin',
            'token_hash' => hash('sha256', 'test5'),
            'token_prefix' => 'cmp_testadmn',
            'scopes' => ['*'],
        ]);

        expect($agent->hasScope('anything:here'))->toBeTrue();
    });

    it('supports partial wildcard like *:read', function () {
        $agent = CompanionAgent::create([
            'name' => 'Reader',
            'token_hash' => hash('sha256', 'test6'),
            'token_prefix' => 'cmp_testread',
            'scopes' => ['*:read'],
        ]);

        expect($agent->hasScope('models:read'))->toBeTrue();
        expect($agent->hasScope('models:write'))->toBeFalse();
    });
});

describe('IP allowlist', function () {
    it('allows all IPs when no allowlist', function () {
        $agent = CompanionAgent::create([
            'name' => 'Test',
            'token_hash' => hash('sha256', 'test7'),
            'token_prefix' => 'cmp_testipaa',
            'scopes' => ['*'],
            'ip_allowlist' => null,
        ]);

        expect($agent->isIpAllowed('1.2.3.4'))->toBeTrue();
    });

    it('restricts to allowlisted IPs', function () {
        $agent = CompanionAgent::create([
            'name' => 'Test',
            'token_hash' => hash('sha256', 'test8'),
            'token_prefix' => 'cmp_testiprr',
            'scopes' => ['*'],
            'ip_allowlist' => ['10.0.0.1', '10.0.0.2'],
        ]);

        expect($agent->isIpAllowed('10.0.0.1'))->toBeTrue();
        expect($agent->isIpAllowed('10.0.0.3'))->toBeFalse();
    });
});
