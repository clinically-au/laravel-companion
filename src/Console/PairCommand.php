<?php

declare(strict_types=1);

namespace Clinically\Companion\Console;

use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Services\QrPayloadService;
use Illuminate\Console\Command;

final class PairCommand extends Command
{
    protected $signature = 'companion:pair {agent : The agent ID or name}';

    protected $description = 'Display a QR code for an existing agent (requires the token to be known)';

    public function handle(QrPayloadService $qrService): int
    {
        $identifier = $this->argument('agent');

        $agent = CompanionAgent::where('id', $identifier)
            ->orWhere('name', $identifier)
            ->first();

        if (! $agent) {
            $this->components->error("Agent '{$identifier}' not found.");

            return self::FAILURE;
        }

        if (! $agent->isActive()) {
            $this->components->error('Agent is not active (revoked or expired).');

            return self::FAILURE;
        }

        $this->components->warn(
            'The QR code requires the plain token which is not stored. '
            .'Use this command immediately after creating an agent, or create a new one with companion:agent.'
        );

        $payload = $qrService->buildPayload($agent, '(token-not-available)');
        $this->newLine();
        $this->components->info("Agent: {$agent->name}");
        $this->components->info('Pairing payload (for reference):');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
