<?php

declare(strict_types=1);

namespace Clinically\Companion\Livewire;

use Clinically\Companion\Services\TokenService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Modelable;
use Livewire\Component;

final class ScopePicker extends Component
{
    /** @var list<string> */
    #[Modelable]
    public array $selected = [];

    public function applyPreset(string $preset): void
    {
        /** @var TokenService $tokenService */
        $tokenService = app(TokenService::class);
        $resolved = $tokenService->resolvePreset($preset);

        /** @var list<string> $availableScopes */
        $availableScopes = (array) config('companion.scopes', []);

        $this->selected = $this->expandWildcards($resolved, $availableScopes);
    }

    /**
     * Expand wildcard patterns (e.g. '*', '*:read') to matching available scopes.
     *
     * @param  list<string>  $scopes
     * @param  list<string>  $availableScopes
     * @return list<string>
     */
    private function expandWildcards(array $scopes, array $availableScopes): array
    {
        $expanded = [];

        foreach ($scopes as $scope) {
            if (! str_contains($scope, '*')) {
                $expanded[] = $scope;

                continue;
            }

            $pattern = '/^'.str_replace('\*', '[^:]*', preg_quote($scope, '/')).'$/';

            foreach ($availableScopes as $available) {
                if (preg_match($pattern, $available)) {
                    $expanded[] = $available;
                }
            }
        }

        return array_values(array_unique($expanded));
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
