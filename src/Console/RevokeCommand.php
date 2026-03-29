<?php

declare(strict_types=1);

namespace Clinically\Companion\Console;

use Clinically\Companion\Events\AgentRevoked;
use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

final class RevokeCommand extends Command
{
    protected $signature = 'companion:revoke {agent : The agent ID or name}';

    protected $description = 'Revoke a Companion agent token';

    public function handle(): int
    {
        $identifier = $this->argument('agent');

        $agent = CompanionAgent::where('id', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        if (! $agent) {
            $this->components->error("Agent '{$identifier}' not found.");

            return self::FAILURE;
        }

        if ($agent->isRevoked()) {
            $this->components->warn("Agent '{$agent->name}' is already revoked.");

            return self::SUCCESS;
        }

        if (! confirm("Revoke agent '{$agent->name}'? This cannot be undone.")) {
            return self::SUCCESS;
        }

        $agent->revoke();

        AgentRevoked::dispatch($agent);

        $this->components->info("Agent '{$agent->name}' has been revoked.");

        return self::SUCCESS;
    }
}
