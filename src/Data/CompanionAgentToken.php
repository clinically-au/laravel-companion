<?php

declare(strict_types=1);

namespace Clinically\Companion\Data;

use Clinically\Companion\Models\CompanionAgent;

final readonly class CompanionAgentToken
{
    public function __construct(
        public CompanionAgent $agent,
        public string $plainToken,
    ) {}
}
