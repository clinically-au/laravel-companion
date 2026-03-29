<?php

declare(strict_types=1);

use Clinically\Companion\Testing\CompanionTestHelpers;

uses(CompanionTestHelpers::class);

it('lists whitelisted commands', function () {
    $agent = $this->createTestAgent(scopes: ['commands:list']);

    $response = $this->withCompanionAgent($agent)
        ->getJson('/companion/api/commands/whitelisted');

    $response->assertOk();
});

it('blocks blacklisted command execution', function () {
    $agent = $this->createTestAgent(scopes: ['commands:execute']);

    $response = $this->withCompanionAgent($agent)
        ->postJson('/companion/api/commands/migrate/run');

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'command_blacklisted');
});

it('blocks non-whitelisted command execution', function () {
    $agent = $this->createTestAgent(scopes: ['commands:execute']);

    $response = $this->withCompanionAgent($agent)
        ->postJson('/companion/api/commands/inspire/run');

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'command_not_whitelisted');
});

it('executes whitelisted command', function () {
    $agent = $this->createTestAgent(scopes: ['commands:execute']);

    $response = $this->withCompanionAgent($agent)
        ->postJson('/companion/api/commands/cache:clear/run');

    $response->assertOk();
    $response->assertJsonStructure(['data' => ['exit_code', 'output']]);
});

it('denies command execution without execute scope', function () {
    $agent = $this->createTestAgent(scopes: ['commands:list']);

    $response = $this->withCompanionAgent($agent)
        ->postJson('/companion/api/commands/cache:clear/run');

    $response->assertForbidden();
    $response->assertJsonPath('error.code', 'scope_denied');
});
