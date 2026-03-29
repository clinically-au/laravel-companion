<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\Models\CompanionAgent;
use Clinically\Companion\Services\QrPayloadService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class QrCode extends Component
{
    public string $agentId;

    public string $plainToken;

    public function mount(CompanionAgent|string $agent, string $token): void
    {
        $this->agentId = $agent instanceof CompanionAgent ? $agent->id : $agent;
        $this->plainToken = $token;
    }

    public function render(): View
    {
        $agent = CompanionAgent::findOrFail($this->agentId);

        /** @var QrPayloadService $qrService */
        $qrService = app(QrPayloadService::class);

        return view('companion::livewire.qr-code', [ // @phpstan-ignore argument.type
            'agent' => $agent,
            'qrSvg' => $qrService->generateQrSvg($agent, $this->plainToken),
            'payload' => $qrService->buildPayload($agent, $this->plainToken),
        ]);
    }
}
