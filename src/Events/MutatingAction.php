<?php

declare(strict_types=1);

namespace Clinically\Companion\Events;

use Clinically\Companion\Models\CompanionAgent;
use Illuminate\Foundation\Events\Dispatchable;

final class MutatingAction
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly CompanionAgent $agent,
        public readonly string $action,
        public readonly array $payload = [],
    ) {}
}
