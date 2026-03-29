<?php

declare(strict_types=1);

use Clinically\Companion\FeatureRegistry;

beforeEach(function () {
    $this->registry = new FeatureRegistry;
});

describe('feature resolution', function () {
    it('resolves simple boolean features', function () {
        config()->set('companion.features.environment', true);
        $this->registry->flush();

        expect($this->registry->enabled('environment'))->toBeTrue();

        config()->set('companion.features.environment', false);
        $this->registry->flush();

        expect($this->registry->enabled('environment'))->toBeFalse();
    });

    it('resolves array features with enabled key', function () {
        config()->set('companion.features.models', ['enabled' => true, 'browse' => true]);
        $this->registry->flush();

        expect($this->registry->enabled('models'))->toBeTrue();
        expect($this->registry->enabled('models.browse'))->toBeTrue();
    });

    it('disables sub-features when parent is disabled', function () {
        config()->set('companion.features.models', ['enabled' => false, 'browse' => true]);
        $this->registry->flush();

        expect($this->registry->enabled('models'))->toBeFalse();
        expect($this->registry->enabled('models.browse'))->toBeFalse();
    });

    it('returns false for sub-features that are disabled', function () {
        config()->set('companion.features.commands', ['enabled' => true, 'execute' => false]);
        $this->registry->flush();

        expect($this->registry->enabled('commands'))->toBeTrue();
        expect($this->registry->enabled('commands.execute'))->toBeFalse();
    });

    it('returns false for unknown features', function () {
        expect($this->registry->enabled('nonexistent'))->toBeFalse();
    });

    it('auto-detects horizon as unavailable when class missing', function () {
        config()->set('companion.features.horizon', ['enabled' => true]);
        $this->registry->flush();

        // Horizon class doesn't exist in test env
        expect($this->registry->enabled('horizon'))->toBeFalse();
    });
});

describe('capabilities', function () {
    it('builds capabilities matrix for agent scopes', function () {
        config()->set('companion.features', [
            'environment' => true,
            'models' => ['enabled' => true, 'browse' => true],
            'routes' => true,
            'commands' => ['enabled' => true, 'execute' => false],
            'dashboard' => true, // should be excluded
        ]);
        $this->registry->flush();

        $capabilities = $this->registry->capabilities(['models:read', 'routes:read', 'environment:read']);

        expect($capabilities)->toHaveKey('environment');
        expect($capabilities['environment']['available'])->toBeTrue();
        expect($capabilities)->not->toHaveKey('dashboard');
        expect($capabilities['models']['available'])->toBeTrue();
    });

    it('respects wildcard scopes', function () {
        config()->set('companion.features', [
            'environment' => true,
            'models' => ['enabled' => true, 'browse' => true],
        ]);
        $this->registry->flush();

        $capabilities = $this->registry->capabilities(['*']);

        expect($capabilities['environment']['read'])->toBeTrue();
        expect($capabilities['models']['read'])->toBeTrue();
    });
});

describe('custom features', function () {
    it('registers and resolves custom features', function () {
        $this->registry->registerFeature('custom', fn () => null);

        expect($this->registry->enabled('custom'))->toBeTrue();
        expect($this->registry->customFeatures())->toHaveKey('custom');
    });
});
