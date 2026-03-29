<?php

declare(strict_types=1);

namespace Clinically\Companion\Console;

use Clinically\Companion\FeatureRegistry;
use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Models\CompanionAuditLog;
use Illuminate\Console\Command;

final class StatusCommand extends Command
{
    protected $signature = 'companion:status';

    protected $description = 'Show Companion health check and status overview';

    public function handle(FeatureRegistry $features): int
    {
        $this->components->info('Laravel Companion Status');
        $this->newLine();

        // Agent stats
        $totalAgents = CompanionAgent::count();
        $activeAgents = CompanionAgent::active()->count();
        $this->line("  Agents: <fg=green>{$activeAgents} active</> / {$totalAgents} total");

        // Last activity
        $lastAudit = CompanionAuditLog::latest('created_at')->first();
        $lastActivity = $lastAudit ? $lastAudit->created_at->diffForHumans() : 'No activity';
        $this->line("  Last API activity: {$lastActivity}");

        // Audit log size
        $auditCount = CompanionAuditLog::count();
        $this->line("  Audit log entries: {$auditCount}");

        $this->newLine();

        // Feature status
        $this->components->info('Features');
        $featureNames = [
            'environment', 'models', 'routes', 'commands', 'queues',
            'cache', 'config', 'logs', 'schedule', 'migrations',
            'events', 'horizon', 'pulse', 'telescope', 'dashboard',
        ];

        foreach ($featureNames as $feature) {
            $enabled = $features->enabled($feature);
            $badge = $enabled ? '<fg=green>enabled</>' : '<fg=red>disabled</>';
            $this->line("  {$feature}: {$badge}");
        }

        $this->newLine();

        // Config summary
        $this->line('  Path: /'.config('companion.path', 'companion'));
        $this->line('  Rate limit: '.config('companion.rate_limit.api', 120).' req/min');
        $this->line('  Audit logging: '.(config('companion.audit.enabled') ? 'enabled' : 'disabled'));
        $this->line('  Audit retention: '.config('companion.audit.retention_days', 90).' days');

        return self::SUCCESS;
    }
}
