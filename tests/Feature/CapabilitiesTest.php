<?php

declare(strict_types=1);

use Clinically\Companion\Testing\CompanionTestHelpers;

uses(CompanionTestHelpers::class);

it('returns capabilities for admin agent', function () {
    $agent = $this->createTestAgent(scopes: ['*']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/capabilities');

    $response->assertOk();
    $response->assertJsonPath('data.features.environment.available', true);
    $response->assertJsonPath('data.features.models.available', true);
    $this->assertCompanionResponse($response);
});

it('reflects agent scopes in capabilities', function () {
    $agent = $this->createTestAgent(scopes: ['models:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/capabilities');

    $response->assertOk();
    $response->assertJsonPath('data.features.models.read', true);
});
