<?php

declare(strict_types=1);

namespace Clinically\Companion\Testing;

use Clinically\Companion\Data\CompanionAgentToken;
use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Services\TokenService;
use Illuminate\Testing\TestResponse;

trait CompanionTestHelpers
{
    /**
     * Create a test agent with the given scopes.
     *
     * @param  list<string>|null  $scopes
     */
    protected function createTestAgent(?array $scopes = null, string $name = 'Test Agent'): CompanionAgentToken
    {
        /** @var TokenService $tokenService */
        $tokenService = app(TokenService::class);

        return $tokenService->createAgent(
            name: $name,
            scopes: $scopes ?? ['*'],
        );
    }

    /**
     * Make a request with a companion agent token.
     *
     * @return $this
     */
    protected function withCompanionAgent(CompanionAgentToken|CompanionAgent $agent): static
    {
        $token = $agent instanceof CompanionAgentToken
            ? $agent->plainToken
            : throw new \InvalidArgumentException('A CompanionAgentToken with the plain token is required. Use createTestAgent().');

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Assert a companion API response has the standard envelope.
     */
    protected function assertCompanionResponse(TestResponse $response): void
    {
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'environment',
                'laravel_version',
                'php_version',
                'companion_version',
                'timestamp',
            ],
        ]);
    }

    /**
     * Assert a companion API error response.
     */
    protected function assertCompanionError(TestResponse $response, string $code, int $status): void
    {
        $response->assertStatus($status);
        $response->assertJsonPath('error.code', $code);
    }
}
