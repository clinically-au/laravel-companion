<?php

declare(strict_types=1);

namespace Clinically\Companion\Console;

use Clinically\Companion\Services\TokenService;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class AgentCommand extends Command
{
    protected $signature = 'companion:agent';

    protected $description = 'Create a new Companion agent token';

    public function handle(TokenService $tokenService): int
    {
        if ($tokenService->maxAgentsReached()) {
            $this->components->error('Maximum number of agents reached. Revoke an existing agent first.');

            return self::FAILURE;
        }

        $name = text(
            label: 'Agent name',
            placeholder: 'My Device',
            required: true,
        );

        $scopes = $this->selectScopes($tokenService);

        $expiryDays = (int) text(
            label: 'Token expiry (days, 0 for never)',
            default: (string) config('companion.agents.default_expiry_days', 90),
        );

        $result = $tokenService->createAgent(
            name: $name,
            scopes: $scopes,
            expiresAt: $expiryDays > 0 ? now()->addDays($expiryDays) : null,
        );

        $this->newLine();
        info("Agent '{$name}' created successfully.");
        $this->newLine();

        warning('Save this token — it will NOT be shown again:');
        $this->newLine();
        $this->line("  <fg=green>{$result->plainToken}</>");
        $this->newLine();

        if ($result->agent->expires_at) {
            note("Expires: {$result->agent->expires_at->toDateTimeString()}");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function selectScopes(TokenService $tokenService): array
    {
        $presets = (array) config('companion.scope_presets', []);
        $presetOptions = array_keys($presets);
        $presetOptions[] = 'custom';

        $choice = $this->choice('Scope preset', $presetOptions, 0);

        if ($choice === 'custom') {
            $allScopes = $tokenService->validScopes();

            /** @var list<string> */
            return multiselect(
                label: 'Select scopes',
                options: array_combine($allScopes, $allScopes),
                required: true,
            );
        }

        return $tokenService->resolvePreset($choice);
    }
}
