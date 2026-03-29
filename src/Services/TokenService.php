<?php

declare(strict_types=1);

namespace Clinically\Companion\Services;

use Clinically\Companion\Data\CompanionAgentToken;
use Clinically\Companion\Events\AgentCreated;
use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class TokenService
{
    /**
     * Generate a new agent token and persist the agent.
     *
     * @param  list<string>  $scopes
     * @param  list<string>|null  $ipAllowlist
     */
    public function createAgent(
        string $name,
        array $scopes,
        ?Carbon $expiresAt = null,
        ?int $createdBy = null,
        ?array $ipAllowlist = null,
    ): CompanionAgentToken {
        $prefix = (string) config('companion.agents.token_prefix', 'cmp_');
        $ulid = strtolower((string) Str::ulid());
        $random = Str::random(64);
        $plainToken = "{$prefix}{$ulid}|{$random}";

        $agent = CompanionAgent::create([
            'name' => $name,
            'token_hash' => $this->hash($plainToken),
            'token_prefix' => substr($plainToken, 0, 16),
            'scopes' => $scopes,
            'ip_allowlist' => $ipAllowlist,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        AgentCreated::dispatch($agent, $createdBy);

        return new CompanionAgentToken($agent, $plainToken);
    }

    /**
     * Look up an agent by plain token.
     */
    public function findByToken(string $plainToken): ?CompanionAgent
    {
        $hash = $this->hash($plainToken);

        return CompanionAgent::where('token_hash', $hash)->first();
    }

    /**
     * Hash a token using SHA-256.
     */
    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Resolve scopes from a preset name.
     *
     * @return list<string>
     */
    public function resolvePreset(string $preset): array
    {
        $presets = (array) config('companion.scope_presets', []);

        if (! isset($presets[$preset])) {
            return [];
        }

        /** @var list<string> */
        return $presets[$preset];
    }

    /**
     * Get all valid scopes defined in config.
     *
     * @return list<string>
     */
    public function validScopes(): array
    {
        /** @var list<string> */
        return (array) config('companion.scopes', []);
    }

    /**
     * Check if the maximum number of agents has been reached.
     */
    public function maxAgentsReached(): bool
    {
        $max = (int) config('companion.agents.max_agents', 20);

        return CompanionAgent::active()->count() >= $max;
    }
}
