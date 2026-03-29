<?php

declare(strict_types=1);

namespace Clinically\Companion\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

final class AgentAuthFailed
{
    use Dispatchable;

    public function __construct(
        public readonly string $tokenPrefix,
        public readonly string $reason,
        public readonly Request $request,
    ) {}
}
