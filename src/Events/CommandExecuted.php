<?php

declare(strict_types=1);

namespace Clinically\Companion\Events;

use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Foundation\Events\Dispatchable;

final class CommandExecuted
{
    use Dispatchable;

    public function __construct(
        public readonly CompanionAgent $agent,
        public readonly string $command,
        public readonly int $exitCode,
    ) {}
}
