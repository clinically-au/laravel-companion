<?php

declare(strict_types=1);

use Clinically\Companion\Testing\CompanionTestHelpers;

uses(CompanionTestHelpers::class);

it('requires confirmation for cache flush', function () {
    $agent = $this->createTestAgent(scopes: ['cache:write']);

    $response = $this->withCompanionAgent($agent)
        ->postJson('/companion/api/cache/flush');

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'confirmation_required');
});

it('blocks cache key read when prefix not allowed', function () {
    config()->set('companion.cache.allowed_prefixes', ['app:']);

    $agent = $this->createTestAgent(scopes: ['cache:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/cache/laravel_session:abc');

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'key_not_allowed');
});

it('allows cache key read when prefix matches', function () {
    config()->set('companion.cache.allowed_prefixes', ['app:']);
    cache()->put('app:test', 'hello', 60);

    $agent = $this->createTestAgent(scopes: ['cache:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/cache/app:test');

    $response->assertOk();
    $response->assertJsonPath('data.value', 'hello');
});

it('allows all keys when no prefix configured', function () {
    config()->set('companion.cache.allowed_prefixes', []);
    cache()->put('anything', 'value', 60);

    $agent = $this->createTestAgent(scopes: ['cache:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/cache/anything');

    $response->assertOk();
});
