<?php

declare(strict_types=1);

namespace Clinically\Companion;

final class FeatureRegistry
{
    /**
     * Auto-detected integrations and their required classes.
     *
     * @var array<string, string>
     */
    private const AUTO_DETECT = [
        'horizon' => 'Laravel\Horizon\Horizon',
        'pulse' => 'Laravel\Pulse\Pulse',
        'telescope' => 'Laravel\Telescope\Telescope',
    ];

    /**
     * Custom features registered by consuming apps.
     *
     * @var array<string, callable>
     */
    private array $customFeatures = [];

    /**
     * Resolved cache to avoid re-computing within a request.
     *
     * @var array<string, bool>
     */
    private array $resolved = [];

    /**
     * Check if a feature (or sub-feature) is enabled.
     *
     * Accepts dot notation: 'models', 'models.browse', 'horizon.write'
     */
    public function enabled(string $feature): bool
    {
        if (isset($this->resolved[$feature])) {
            return $this->resolved[$feature];
        }

        $result = $this->resolve($feature);
        $this->resolved[$feature] = $result;

        return $result;
    }

    /**
     * Register a custom feature with its route registrar.
     */
    public function registerFeature(string $name, callable $registrar): void
    {
        $this->customFeatures[$name] = $registrar;
        unset($this->resolved[$name]);
    }

    /**
     * Get all custom feature registrars.
     *
     * @return array<string, callable>
     */
    public function customFeatures(): array
    {
        return $this->customFeatures;
    }

    /**
     * Build the full capabilities matrix for a given agent's scopes.
     *
     * @param  list<string>  $agentScopes
     * @return array<string, array<string, bool>>
     */
    public function capabilities(array $agentScopes): array
    {
        $capabilities = [];
        $features = config('companion.features', []);

        foreach ($features as $feature => $value) {
            if ($feature === 'dashboard') {
                continue;
            }

            $available = $this->enabled($feature);
            $entry = ['available' => $available];

            if ($available) {
                $entry = array_merge($entry, $this->resolveOperations($feature, $value, $agentScopes));
            }

            $capabilities[$feature] = $entry;
        }

        foreach (array_keys($this->customFeatures) as $feature) {
            $capabilities[$feature] = [
                'available' => $this->enabled($feature),
            ];
        }

        return $capabilities;
    }

    /**
     * Flush the resolved cache.
     */
    public function flush(): void
    {
        $this->resolved = [];
    }

    private function resolve(string $feature): bool
    {
        $parts = explode('.', $feature);
        $root = $parts[0];
        $subFeature = $parts[1] ?? null;

        // Check custom features
        if (isset($this->customFeatures[$root])) {
            return true;
        }

        $config = config("companion.features.{$root}");

        if ($config === null) {
            return false;
        }

        // Simple boolean
        if (is_bool($config)) {
            // Sub-features inherit from parent when parent is a simple boolean
            if ($subFeature !== null) {
                return $config;
            }

            return $config && $this->passesAutoDetect($root);
        }

        // Array config
        if (is_array($config)) {
            $parentEnabled = ($config['enabled'] ?? true) && $this->passesAutoDetect($root);

            if (! $parentEnabled) {
                return false;
            }

            if ($subFeature === null) {
                return true;
            }

            return (bool) ($config[$subFeature] ?? true);
        }

        return false;
    }

    private function passesAutoDetect(string $feature): bool
    {
        if (! isset(self::AUTO_DETECT[$feature])) {
            return true;
        }

        return class_exists(self::AUTO_DETECT[$feature]);
    }

    /**
     * Resolve individual operation booleans for a feature, combining
     * global config with the agent's scopes.
     *
     * @param  list<string>  $agentScopes
     * @return array<string, bool>
     */
    private function resolveOperations(string $feature, mixed $config, array $agentScopes): array
    {
        $operations = [];

        // Default read operation
        $operations['read'] = $this->agentHasScope($agentScopes, "{$feature}:read");

        if (is_array($config)) {
            foreach ($config as $key => $value) {
                if ($key === 'enabled') {
                    continue;
                }

                $scopeName = $this->operationToScope($feature, $key);
                $operations[$key] = (bool) $value && $this->agentHasScope($agentScopes, $scopeName);
            }
        }

        return $operations;
    }

    /**
     * Map a feature operation to its scope name.
     */
    private function operationToScope(string $feature, string $operation): string
    {
        // Special mappings
        $map = [
            'commands.execute' => 'commands:execute',
            'commands.list' => 'commands:list',
            'models.browse' => 'models:browse',
            'logs.stream' => 'logs:read',
        ];

        $key = "{$feature}.{$operation}";

        return $map[$key] ?? "{$feature}:{$operation}";
    }

    /**
     * Check if the agent has the required scope.
     *
     * Supports wildcards: '*' grants everything, '*:read' grants all read scopes.
     *
     * @param  list<string>  $agentScopes
     */
    private function agentHasScope(array $agentScopes, string $required): bool
    {
        if (in_array('*', $agentScopes, true)) {
            return true;
        }

        if (in_array($required, $agentScopes, true)) {
            return true;
        }

        // Check wildcard patterns like '*:read'
        $requiredParts = explode(':', $required);
        if (count($requiredParts) === 2) {
            $wildcardScope = "*:{$requiredParts[1]}";
            if (in_array($wildcardScope, $agentScopes, true)) {
                return true;
            }
        }

        return false;
    }
}
