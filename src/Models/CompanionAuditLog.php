<?php

declare(strict_types=1);

namespace Clinically\Companion\Models;

use Clinically\Companion\Support\CompanionConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $agent_id
 * @property string $action
 * @property string $method
 * @property string $path
 * @property array<string, mixed>|null $payload
 * @property int $response_code
 * @property string $ip
 * @property string|null $user_agent
 * @property int $duration_ms
 * @property Carbon $created_at
 */
final class CompanionAuditLog extends Model
{
    use HasUlids;
    use MassPrunable;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_code' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return CompanionConfig::table('audit_logs');
    }

    /**
     * @return BelongsTo<CompanionAgent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(CompanionAgent::class, 'agent_id');
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = (int) config('companion.audit.retention_days', 90);

        return self::where('created_at', '<', now()->subDays($days));
    }
}
