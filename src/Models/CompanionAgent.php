<?php

declare(strict_types=1);

namespace Clinically\Companion\Models;

use Clinically\Companion\Support\CompanionConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $token_hash
 * @property string $token_prefix
 * @property list<string> $scopes
 * @property list<string>|null $ip_allowlist
 * @property Carbon|null $last_seen_at
 * @property string|null $last_ip
 * @property string|null $last_user_agent
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CompanionAgent extends Model
{
    use HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'token_hash',
        'token_prefix',
        'scopes',
        'ip_allowlist',
        'last_seen_at',
        'last_ip',
        'last_user_agent',
        'expires_at',
        'revoked_at',
        'created_by',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'ip_allowlist' => 'array',
            'metadata' => 'array',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return CompanionConfig::table('agents');
    }

    /**
     * @return HasMany<CompanionAuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(CompanionAuditLog::class, 'agent_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes;

        if (in_array('*', $scopes, true)) {
            return true;
        }

        if (in_array($scope, $scopes, true)) {
            return true;
        }

        // Check wildcard patterns like '*:read'
        $parts = explode(':', $scope);
        if (count($parts) === 2) {
            return in_array("*:{$parts[1]}", $scopes, true);
        }

        return false;
    }

    /**
     * Check if the given IP is allowed for this agent.
     * Returns true if no allowlist is configured.
     */
    public function isIpAllowed(string $ip): bool
    {
        if ($this->ip_allowlist === null || $this->ip_allowlist === []) {
            return true;
        }

        return in_array($ip, $this->ip_allowlist, true);
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }
}
