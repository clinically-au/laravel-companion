# clinically/laravel-companion

## Quick Reference

- **Type**: Laravel package (library)
- **PHP**: 8.2+ with strict_types
- **Laravel**: 11+
- **Namespace**: `Clinically\Companion\`
- **License**: MIT (public OSS)

## Architecture

### Package Boundary

**Package owns**: core logic, contracts, events, DTOs, config defaults, views, middleware, artisan commands
**Consuming app owns**: models (overridable via config), migrations (published stubs), gate definition, User model

### Integration Surface

- **Contracts**: `CompanionSerializable` interface for model browser opt-in
- **Events**: `AgentCreated`, `AgentRevoked`, `AgentAuthenticated`, `AgentAuthFailed`, `CommandExecuted`, `MutatingAction`
- **Config**: model classes, table names, feature flags, scopes resolved from config
- **Facade**: `Companion::registerFeature()` for custom feature groups
- **Trait**: `HasCompanionAccess` on User model for convenience helpers

### Key Classes

| Class | Purpose |
|---|---|
| `CompanionServiceProvider` | Auto-discovered, registers everything |
| `FeatureRegistry` | Resolves feature enabled/disabled state, builds capabilities matrix |
| `CompanionConfig` | Config-driven model/table resolution |
| `TokenService` | Token generation, hashing, validation |
| `CompanionAuthMiddleware` | Bearer token auth for API routes |
| `CompanionScopeMiddleware` | Per-route scope enforcement |

## Project Structure

```
src/
├── CompanionServiceProvider.php
├── Companion.php (facade)
├── FeatureRegistry.php
├── Models/ (CompanionAgent, CompanionAuditLog)
├── Data/ (DTOs)
├── Contracts/ (CompanionSerializable)
├── Events/ (6 event classes)
├── Http/
│   ├── Middleware/ (Auth, Scope, Audit, Feature)
│   └── Controllers/
│       ├── Api/ (15 controllers)
│       └── Dashboard/ (4 controllers)
├── Livewire/ (7 components)
├── Console/ (7 commands)
├── Services/ (Token, ModelBrowser, LogParser, ConfigRedaction, QrPayload, Audit)
├── Support/ (CompanionConfig)
├── Traits/ (HasCompanionAccess)
└── Testing/ (CompanionTestHelpers)
```

## Conventions

- `declare(strict_types=1)` in all PHP files
- `final class` unless extension is intended (CompanionAgent uses Macroable, so not final)
- All parameters and return types fully typed
- `readonly` properties where appropriate
- Models/tables resolved via `CompanionConfig` — never hardcoded
- No `env()` calls in package code — use config values
- Migrations are publishable stubs only (never `loadMigrationsFrom`)
- Features conditionally register routes — disabled features have zero runtime cost
- Livewire components use free Flux components only

## Commands

- `composer test` — run Pest tests
- `composer analyse` — run PHPStan level 6
- `composer format` — run Pint
