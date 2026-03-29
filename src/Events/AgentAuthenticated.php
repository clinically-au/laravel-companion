<?php

declare(strict_types=1);

namespace Clinically\Companion\Events;

use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

final class AgentAuthenticated
{
    use Dispatchable;

    public function __construct(
        public readonly CompanionAgent $agent,
        public readonly Request $request,
    ) {}
}
