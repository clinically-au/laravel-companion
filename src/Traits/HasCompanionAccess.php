<?php

declare(strict_types=1);

namespace Clinically\Companion\Traits;

use Clinically\Companion\Data\CompanionAgentToken;
use Clinically\Companion\Events\AgentRevoked;
use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Models\CompanionAuditLog;
use Clinically\Companion\Services\TokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin Model
 */
trait HasCompanionAccess
{
    /**
     * @return HasMany<CompanionAgent, $this>
     */
    public function companionAgents(): HasMany
    {
        return $this->hasMany(CompanionAgent::class, 'created_by');
    }

    /**
     * Check if this user can access the Companion dashboard.
     */
    public function canAccessCompanion(): bool
    {
        return Gate::allows('viewCompanion', [$this]);
    }

    /**
     * Create a new Companion agent token for this user.
     *
     * @param  list<string>  $scopes
     * @param  list<string>|null  $ipAllowlist
     */
    public function createCompanionAgent(
        string $name,
        array $scopes,
        ?Carbon $expiresAt = null,
        ?array $ipAllowlist = null,
    ): CompanionAgentToken {
        /** @var TokenService $tokenService */
        $tokenService = app(TokenService::class);

        return $tokenService->createAgent(
            name: $name,
            scopes: $scopes,
            expiresAt: $expiresAt,
            createdBy: $this->getKey(),
            ipAllowlist: $ipAllowlist,
        );
    }

    /**
     * Revoke a companion agent.
     */
    public function revokeCompanionAgent(string|CompanionAgent $agent): void
    {
        if (is_string($agent)) {
            $agent = CompanionAgent::findOrFail($agent);
        }

        $agent->revoke();

        AgentRevoked::dispatch($agent, $this->getKey());
    }

    /**
     * Get a query builder for audit logs of agents created by this user.
     *
     * @return Builder<CompanionAuditLog>
     */
    public function companionAuditLog(): Builder
    {
        $agentIds = $this->companionAgents()->pluck('id');

        return CompanionAuditLog::whereIn('agent_id', $agentIds);
    }
}
