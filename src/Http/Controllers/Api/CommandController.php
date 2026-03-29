<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Events\CommandExecuted;
use Clinically\Companion\Events\MutatingAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan as ArtisanFacade;
use Symfony\Component\Console\Command\Command;

final class CommandController extends Controller
{
    public function index(): JsonResponse
    {
        $commands = collect(ArtisanFacade::all())
            ->map(fn (Command $command) => $this->formatCommand($command))
            ->values()
            ->all();

        return $this->respond($commands);
    }

    public function whitelisted(): JsonResponse
    {
        $whitelist = (array) config('companion.whitelisted_commands', []);

        $commands = collect(ArtisanFacade::all())
            ->filter(fn (Command $command) => in_array($command->getName(), $whitelist, true))
            ->map(fn (Command $command) => $this->formatCommand($command))
            ->values()
            ->all();

        return $this->respond($commands);
    }

    public function run(Request $request, string $command): JsonResponse
    {
        $whitelist = (array) config('companion.whitelisted_commands', []);
        $blacklist = (array) config('companion.blacklisted_commands', []);

        // Blacklist always wins
        if (in_array($command, $blacklist, true)) {
            return $this->error(
                "Command '{$command}' is blacklisted and cannot be executed.",
                'command_blacklisted',
                403,
            );
        }

        if (! in_array($command, $whitelist, true)) {
            return $this->error(
                "Command '{$command}' is not in the whitelist.",
                'command_not_whitelisted',
                403,
            );
        }

        $agent = $this->agent($request);

        MutatingAction::dispatch($agent, "commands.execute:{$command}", $request->all());

        /** @var array<string, mixed> $arguments */
        $arguments = (array) $request->input('arguments', []);

        /** @var array<string, mixed> $options */
        $options = (array) $request->input('options', []);

        $exitCode = ArtisanFacade::call($command, array_merge($arguments, $options));
        $output = ArtisanFacade::output();

        CommandExecuted::dispatch($agent, $command, $exitCode);

        return $this->respond([
            'exit_code' => $exitCode,
            'output' => trim($output),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatCommand(Command $command): array
    {
        $definition = $command->getDefinition();

        return [
            'name' => $command->getName(),
            'description' => $command->getDescription(),
            'arguments' => collect($definition->getArguments())->map(fn ($arg) => [
                'name' => $arg->getName(),
                'description' => $arg->getDescription(),
                'required' => $arg->isRequired(),
                'default' => $arg->getDefault(),
            ])->values()->all(),
            'options' => collect($definition->getOptions())->map(fn ($opt) => [
                'name' => $opt->getName(),
                'shortcut' => $opt->getShortcut(),
                'description' => $opt->getDescription(),
                'default' => $opt->getDefault(),
                'value_required' => $opt->isValueRequired(),
            ])->values()->all(),
        ];
    }
}
