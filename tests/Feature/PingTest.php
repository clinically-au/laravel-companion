<?php

declare(strict_types=1);

use Clinically\Companion\Testing\CompanionTestHelpers;

uses(CompanionTestHelpers::class);

it('returns ok with valid token', function () {
    $agent = $this->createTestAgent();

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/ping');

    $response->assertOk();
    $response->assertJsonPath('data.status', 'ok');
    $this->assertCompanionResponse($response);
});

it('returns 401 without token', function () {
    $response = $this->getJson('/companion/api/ping');

    $response->assertUnauthorized();
    $response->assertJsonPath('error.code', 'token_missing');
});

it('returns 401 with invalid token', function () {
    $response = $this->withHeader('Authorization', 'Bearer invalid_token')
        ->getJson('/companion/api/ping');

    $response->assertUnauthorized();
    $response->assertJsonPath('error.code', 'token_invalid');
});

it('returns 401 with revoked token', function () {
    $agent = $this->createTestAgent();
    $agent->agent->revoke();

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/ping');

    $response->assertUnauthorized();
    $response->assertJsonPath('error.code', 'token_revoked');
});

it('returns 401 with expired token', function () {
    $agent = $this->createTestAgent();
    $agent->agent->update(['expires_at' => now()->subDay()]);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/ping');

    $response->assertUnauthorized();
    $response->assertJsonPath('error.code', 'token_expired');
});
