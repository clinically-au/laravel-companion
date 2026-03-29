<?php

declare(strict_types=1);

use Clinically\Companion\FeatureRegistry;
use Clinically\Companion\Testing\CompanionTestHelpers;

uses(CompanionTestHelpers::class);

it('reports disabled features in capabilities response', function () {
    config()->set('companion.features.environment', false);
    app(FeatureRegistry::class)->flush();

    $agent = $this->createTestAgent(scopes: ['*']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/capabilities');

    $response->assertOk();
    $response->assertJsonPath('data.features.environment.available', false);
});

it('reports enabled features in capabilities response', function () {
    config()->set('companion.features.environment', true);
    app(FeatureRegistry::class)->flush();

    $agent = $this->createTestAgent(scopes: ['environment:read']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/capabilities');

    $response->assertOk();
    $response->assertJsonPath('data.features.environment.available', true);
    $response->assertJsonPath('data.features.environment.read', true);
});

it('reports horizon as unavailable when package not installed', function () {
    $agent = $this->createTestAgent(scopes: ['*']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/capabilities');

    $response->assertOk();
    $response->assertJsonPath('data.features.horizon.available', false);
});
