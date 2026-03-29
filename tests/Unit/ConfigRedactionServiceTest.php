<?php

declare(strict_types=1);

use Clinically\Companion\Services\ConfigRedactionService;

beforeEach(function () {
    $this->service = new ConfigRedactionService;
});

it('redacts keys matching patterns', function () {
    config()->set('companion.config_redaction.patterns', ['/password/i', '/secret/i']);
    config()->set('companion.config_redaction.always_redact', []);
    config()->set('companion.config_redaction.never_redact', []);

    $result = $this->service->redact([
        'db_password' => 'secret123',
        'app_name' => 'My App',
        'api_secret' => 'abc',
    ]);

    expect($result['db_password'])->toBe('********');
    expect($result['app_name'])->toBe('My App');
    expect($result['api_secret'])->toBe('********');
});

it('respects always_redact with wildcards', function () {
    config()->set('companion.config_redaction.patterns', []);
    config()->set('companion.config_redaction.always_redact', ['database.connections.*.password']);
    config()->set('companion.config_redaction.never_redact', []);

    $result = $this->service->redact([
        'connections' => [
            'mysql' => [
                'password' => 'secret',
                'host' => 'localhost',
            ],
        ],
    ], 'database');

    expect($result['connections']['mysql']['password'])->toBe('********');
    expect($result['connections']['mysql']['host'])->toBe('localhost');
});

it('respects never_redact overrides', function () {
    config()->set('companion.config_redaction.patterns', ['/key$/i']);
    config()->set('companion.config_redaction.always_redact', []);
    config()->set('companion.config_redaction.never_redact', ['app.key_name']);

    // The 'key_name' leaf matches /key$/i but the full key is in never_redact
    $result = $this->service->redactValue('app.key_name', 'some_value');

    expect($result)->toBe('some_value');
});

it('redacts nested arrays recursively', function () {
    config()->set('companion.config_redaction.patterns', ['/password/i']);
    config()->set('companion.config_redaction.always_redact', []);
    config()->set('companion.config_redaction.never_redact', []);

    $result = $this->service->redact([
        'level1' => [
            'level2' => [
                'password' => 'deep_secret',
                'name' => 'safe',
            ],
        ],
    ]);

    expect($result['level1']['level2']['password'])->toBe('********');
    expect($result['level1']['level2']['name'])->toBe('safe');
});
