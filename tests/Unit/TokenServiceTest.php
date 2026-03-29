<?php

declare(strict_types=1);

use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Services\TokenService;

beforeEach(function () {
    $this->service = new TokenService;
});

describe('token generation', function () {
    it('creates an agent with a hashed token', function () {
        $result = $this->service->createAgent('Test Agent', ['models:read']);

        expect($result->plainToken)->toStartWith('cmp_');
        expect($result->agent)->toBeInstanceOf(CompanionAgent::class);
        expect($result->agent->token_hash)->not->toBe($result->plainToken);
        expect($result->agent->name)->toBe('Test Agent');
        expect($result->agent->scopes)->toBe(['models:read']);
    });

    it('stores the token prefix for identification', function () {
        $result = $this->service->createAgent('Test', ['*']);

        expect($result->agent->token_prefix)->toBe(substr($result->plainToken, 0, 8));
    });

    it('can find an agent by plain token', function () {
        $result = $this->service->createAgent('Test', ['*']);

        $found = $this->service->findByToken($result->plainToken);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($result->agent->id);
    });

    it('returns null for invalid token', function () {
        expect($this->service->findByToken('invalid'))->toBeNull();
    });
});

describe('scope presets', function () {
    it('resolves read-only preset', function () {
        $scopes = $this->service->resolvePreset('read-only');

        expect($scopes)->toContain('models:read');
        expect($scopes)->not->toContain('commands:execute');
    });

    it('returns empty array for unknown preset', function () {
        expect($this->service->resolvePreset('nonexistent'))->toBe([]);
    });
});

describe('agent limits', function () {
    it('detects when max agents reached', function () {
        config()->set('companion.agents.max_agents', 2);

        $this->service->createAgent('Agent 1', ['*']);
        $this->service->createAgent('Agent 2', ['*']);

        expect($this->service->maxAgentsReached())->toBeTrue();
    });

    it('does not count revoked agents', function () {
        config()->set('companion.agents.max_agents', 2);

        $result = $this->service->createAgent('Agent 1', ['*']);
        $result->agent->revoke();

        $this->service->createAgent('Agent 2', ['*']);

        expect($this->service->maxAgentsReached())->toBeFalse();
    });
});
