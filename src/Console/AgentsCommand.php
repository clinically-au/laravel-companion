<?php

declare(strict_types=1);

namespace Clinically\Companion\Console;

use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Console\Command;

final class AgentsCommand extends Command
{
    protected $signature = 'companion:agents';

    protected $description = 'List all Companion agents';

    public function handle(): int
    {
        $agents = CompanionAgent::orderByDesc('created_at')->get();

        if ($agents->isEmpty()) {
            $this->components->info('No agents found. Create one with companion:agent.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Status', 'Scopes', 'Last Seen', 'Expires', 'Created'],
            $agents->map(fn (CompanionAgent $agent) => [
                substr($agent->id, 0, 8).'...',
                $agent->name,
                $this->statusBadge($agent),
                count($agent->scopes).' scopes',
                $agent->last_seen_at?->diffForHumans() ?? 'Never',
                $agent->expires_at?->toDateString() ?? 'Never',
                $agent->created_at->toDateString(),
            ]),
        );

        $active = $agents->filter(fn (CompanionAgent $a) => $a->isActive())->count();
        $this->newLine();
        $this->components->info("{$active} active / {$agents->count()} total agents");

        return self::SUCCESS;
    }

    private function statusBadge(CompanionAgent $agent): string
    {
        if ($agent->isRevoked()) {
            return '<fg=red>Revoked</>';
        }

        if ($agent->isExpired()) {
            return '<fg=yellow>Expired</>';
        }

        return '<fg=green>Active</>';
    }
}
