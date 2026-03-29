<?php

declare(strict_types=1);

namespace Clinically\Companion\Console;

use Clinically\Companion\Models\CompanionAuditLog;
use Illuminate\Console\Command;

final class PruneAuditCommand extends Command
{
    protected $signature = 'companion:prune-audit';

    protected $description = 'Prune audit log entries older than the configured retention period';

    public function handle(): int
    {
        $days = (int) config('companion.audit.retention_days', 90);

        $count = CompanionAuditLog::where('created_at', '<', now()->subDays($days))->delete();

        $this->components->info("Pruned {$count} audit log entries older than {$days} days.");

        return self::SUCCESS;
    }
}
