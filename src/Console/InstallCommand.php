<?php

declare(strict_types=1);

namespace Clinically\Companion\Console;

use Clinically\Companion\Services\TokenService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

final class InstallCommand extends Command
{
    protected $signature = 'companion:install';

    protected $description = 'Install Companion: publish config, run migrations, create first agent';

    public function handle(TokenService $tokenService): int
    {
        info('Installing Laravel Companion...');

        // 1. Publish config
        $this->call('vendor:publish', [
            '--tag' => 'companion-config',
            '--force' => false,
        ]);
        $this->components->info('Config published.');

        // 2. Publish migrations
        $this->call('vendor:publish', [
            '--tag' => 'companion-migrations',
            '--force' => false,
        ]);
        $this->components->info('Migrations published.');

        // 3. Run migrations
        if (confirm('Run migrations now?', true)) {
            $this->call('migrate');
            $this->components->info('Migrations complete.');
        }

        // 4. Create first agent
        if (confirm('Create your first agent token?', true)) {
            $name = text(
                label: 'Agent name',
                placeholder: "Wojt's iPhone",
                required: true,
            );

            $scopes = $this->selectScopes($tokenService);

            $result = $tokenService->createAgent(
                name: $name,
                scopes: $scopes,
                expiresAt: now()->addDays((int) config('companion.agents.default_expiry_days', 90)),
            );

            $this->newLine();
            $this->components->info("Agent '{$name}' created.");
            $this->newLine();

            warning('Save this token — it will NOT be shown again:');
            $this->newLine();
            $this->line("  <fg=green>{$result->plainToken}</>");
            $this->newLine();

            note('Scan the QR code with the Companion mobile app to pair.');
        }

        // 5. Production hardening checklist
        $this->newLine();
        $this->components->info('Production hardening checklist:');
        $this->line('  [ ] Define the viewCompanion gate in AppServiceProvider');
        $this->line('  [ ] Set COMPANION_DOMAIN if using a separate domain');
        $this->line('  [ ] Review features config — disable what you don\'t need');
        $this->line('  [ ] Review whitelisted_commands — remove any you\'re uncomfortable with');
        $this->line('  [ ] Consider IP allowlisting for agent tokens');
        $this->line('  [ ] Add companion:prune-audit to your scheduler');
        $this->line('  [ ] Ensure HTTPS is enforced (tokens are bearer credentials)');
        $this->newLine();

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
