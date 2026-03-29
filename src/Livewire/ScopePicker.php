<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\Services\TokenService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class ScopePicker extends Component
{
    /** @var list<string> */
    public array $selected = [];

    public function applyPreset(string $preset): void
    {
        /** @var TokenService $tokenService */
        $tokenService = app(TokenService::class);
        $this->selected = $tokenService->resolvePreset($preset);
    }

    public function toggleScope(string $scope): void
    {
        if (in_array($scope, $this->selected, true)) {
            $this->selected = array_values(array_filter(
                $this->selected,
                fn (string $s) => $s !== $scope,
            ));
        } else {
            $this->selected[] = $scope;
        }
    }

    public function render(): View
    {
        return view('companion::livewire.scope-picker', [ // @phpstan-ignore argument.type
            'availableScopes' => (array) config('companion.scopes', []),
            'presets' => array_keys((array) config('companion.scope_presets', [])),
        ]);
    }
}
