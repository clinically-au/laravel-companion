<?php

declare(strict_types=1);

use Clinically\Companion\Testing\CompanionTestHelpers;

uses(CompanionTestHelpers::class);

it('allows access with correct scope', function () {
    $agent = $this->createTestAgent(scopes: ['environment:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/environment');

    $response->assertOk();
});

it('denies access with wrong scope', function () {
    $agent = $this->createTestAgent(scopes: ['models:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/environment');

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'scope_denied');
});

it('allows access with wildcard scope', function () {
    $agent = $this->createTestAgent(scopes: ['*']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/environment');

    $response->assertOk();
});

it('allows access with partial wildcard *:read', function () {
    $agent = $this->createTestAgent(scopes: ['*:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/environment');

    $response->assertOk();
});
