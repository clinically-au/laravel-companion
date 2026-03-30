# clinically/laravel-companion

A structured JSON API for mobile and external tooling to inspect, monitor, and manage Laravel applications. Ships with an admin dashboard (Livewire + Flux) for managing authenticated agents.

Designed as the server-side companion to a native iOS app, but protocol-agnostic — any HTTP client can consume the API.

> **See it in action:** [laravel-companion-demo](https://github.com/clinically-au/laravel-companion-demo) — a full Laravel 13 app that integrates every feature with 121 passing tests, an API Explorer, and Swagger UI docs.

## Features

- **Token-based agent authentication** with SHA-256 hashed tokens, scopes, expiry, IP allowlists
- **Feature toggles** — enable/disable endpoint groups; disabled features have zero runtime cost
- **Model browser** — introspect Eloquent models (via [laravel-introspect](https://github.com/Capevace/laravel-introspect)) and browse records with filtering, sorting, pagination
- **Route inspector** — list all registered routes with middleware, parameters, actions
- **Artisan command runner** — execute whitelisted commands with blacklist safety net
- **Queue management** — view failed jobs, retry, delete, flush
- **Cache inspector** — read keys, forget keys, flush
- **Config viewer** — full config tree with automatic sensitive value redaction
- **Log viewer** — parsed log entries with SSE live tail streaming
- **Scheduler viewer** — all scheduled commands with next due dates
- **Migration status** — batched migration history
- **Event/listener map** — registered events with their listeners
- **Horizon integration** — status, metrics, recent jobs, pause/continue/terminate
- **Pulse integration** — servers, slow queries/requests/jobs, exceptions, usage
- **Audit log** — every API request logged with agent, action, payload, response code, duration
- **Admin dashboard** — Livewire + Flux UI for managing agents, viewing audit logs, feature status
- **QR pairing** — generate QR codes for mobile app device pairing
- **Extensible** — register custom features, `CompanionSerializable` for model opt-in, events for integration

## Requirements

- PHP 8.2+
- Laravel 11+
- [mateffy/laravel-introspect](https://github.com/Capevace/laravel-introspect) ^1.1 (required)

**For the dashboard:**
- [Livewire](https://livewire.laravel.com) ^4.0
- [Flux](https://fluxui.dev) ^2.0 (free tier)

## Installation

```bash
composer require clinically/laravel-companion
```

Then run the install command:

```bash
php artisan companion:install
```

This publishes the config, migrations, runs migrations, and optionally creates your first agent token.

## Configuration

Publish the config separately if needed:

```bash
php artisan vendor:publish --tag=companion-config
```

See `config/companion.php` for all options including:

- **Route prefix/domain** — `COMPANION_PATH`, `COMPANION_DOMAIN`
- **Feature toggles** — enable/disable each endpoint group
- **Scopes & presets** — define what agents can access
- **Command whitelist/blacklist** — control which artisan commands are executable
- **Model browser settings** — hidden columns, redaction patterns, pagination, column validation
- **Cache browser** — `cache.allowed_prefixes` to restrict which keys are accessible (empty = all)
- **Config redaction** — patterns, always/never redact rules
- **Rate limiting** — per-agent API and SSE connection limits
- **Audit log** — retention, read/write logging, automatic payload sanitisation

## Artisan Commands

| Command | Purpose |
|---|---|
| `companion:install` | One-time setup: publish config, run migrations, create first agent |
| `companion:agent` | Create a new agent token interactively |
| `companion:pair {agent}` | Display pairing info for an existing agent |
| `companion:agents` | List all agents with status |
| `companion:revoke {agent}` | Revoke an agent token |
| `companion:prune-audit` | Prune old audit log entries |
| `companion:status` | Health check and feature status overview |

## Dashboard

The admin dashboard is available at `/{path}/dashboard` (default: `/companion/dashboard`).

Access is controlled by the `viewCompanion` gate — open in local environment, locked in production. Define it in your `AppServiceProvider`:

```php
Gate::define('viewCompanion', function (User $user) {
    return $user->is_admin;
});
```

### Embeddable Livewire Components

All dashboard components can be embedded in your own admin panel:

```blade
<livewire:companion-agent-list />
<livewire:companion-agent-creator />
<livewire:companion-agent-detail :agent="$agent" />
<livewire:companion-qr-code :agent="$agent" :token="$token" />
<livewire:companion-audit-log />
<livewire:companion-scope-picker wire:model="scopes" />
<livewire:companion-feature-status />
```

## API Authentication

Agents authenticate via bearer token:

```
Authorization: Bearer cmp_01hx...|a3f8b2...
```

Tokens are SHA-256 hashed before storage. The plain token is shown once at creation. The authenticated agent is accessible via `$request->companionAgent()`.

## HasCompanionAccess Trait

Add to your User model for convenience helpers:

```php
use Clinically\Companion\Traits\HasCompanionAccess;

class User extends Authenticatable
{
    use HasCompanionAccess;
}
```

Provides `$user->companionAgents()`, `$user->createCompanionAgent()`, `$user->revokeCompanionAgent()`, `$user->canAccessCompanion()`.

## Custom Features

Register additional feature groups:

```php
Companion::registerFeature('custom-metrics', function (Router $router) {
    $router->get('/custom-metrics', CustomMetricsController::class)
        ->middleware('companion.scope:custom-metrics:read');
});
```

## Model Browser Opt-in

Implement `CompanionSerializable` to control exactly what's exposed:

```php
use Clinically\Companion\Contracts\CompanionSerializable;

class Patient extends Model implements CompanionSerializable
{
    public function toCompanionArray(): array
    {
        return $this->only(['id', 'first_name', 'last_name', 'mrn']);
    }

    public function companionRelationships(): array
    {
        return ['appointments'];
    }

    public function companionScopes(): array
    {
        return ['active'];
    }
}
```

## Events

| Event | When |
|---|---|
| `AgentCreated` | New agent token generated |
| `AgentRevoked` | Agent revoked |
| `AgentAuthenticated` | Successful API auth |
| `AgentAuthFailed` | Failed auth attempt |
| `CommandExecuted` | Artisan command run via API |
| `MutatingAction` | Any write operation |

## Security

### Model Browser

- Only models discovered in configured `models.paths` are accessible — arbitrary class names are rejected
- Sort/filter column names are validated against the database schema, excluding hidden and redacted columns
- LIKE search wildcards are escaped to prevent pattern injection
- Scopes can only be applied to models implementing `CompanionSerializable`
- Relationship data passes through the same redaction pipeline as top-level records
- Per-page limits are enforced (min 1, max configurable)

### Cache Browser

Configure `companion.cache.allowed_prefixes` to restrict key access:

```php
'cache' => [
    'allowed_prefixes' => ['app:', 'companion:'],
],
```

When empty (default), all keys are accessible. In production, restrict to prevent exposure of session/token data.

### HTTPS

HTTPS is enforced in all non-local/testing environments. Requests over plain HTTP receive a `403`.

## Testing

The package provides test helpers:

```php
use Clinically\Companion\Testing\CompanionTestHelpers;

class MyTest extends TestCase
{
    use CompanionTestHelpers;

    public function test_something()
    {
        $agent = $this->createTestAgent(scopes: ['models:read']);

        $response = $this->withCompanionAgent($agent)
            ->getJson('/companion/api/models');

        $response->assertOk();
    }
}
```

### Running Package Tests

```bash
composer test      # Pest tests
composer analyse   # PHPStan level 6
composer format    # Pint formatting
```

## License

MIT
