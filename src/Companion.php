<?php

declare(strict_types=1);

namespace Clinically\Companion;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool enabled(string $feature)
 * @method static void registerFeature(string $name, callable $registrar)
 * @method static array<string, array<string, bool>> capabilities(list<string> $agentScopes)
 * @method static array<string, callable> customFeatures()
 * @method static void flush()
 *
 * @see FeatureRegistry
 */
final class Companion extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureRegistry::class;
    }
}
