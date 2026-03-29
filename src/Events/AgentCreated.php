<?php

declare(strict_types=1);

namespace Clinically\Companion\Events;

use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Foundation\Events\Dispatchable;

final class AgentCreated
{
    use Dispatchable;

    public function __construct(
        public readonly CompanionAgent $agent,
        public readonly ?int $creatorId = null,
    ) {}
}
