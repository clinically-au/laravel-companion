## clinically/laravel-companion

This package provides a structured JSON API for mobile and external tooling to inspect, monitor, and manage Laravel applications. It includes token-based agent authentication, feature toggles, and an admin dashboard.

### Architecture

- **Namespace**: `Clinically\Companion\`
- **Package owns**: API controllers, middleware, models (CompanionAgent, CompanionAuditLog), services, events, Livewire components, artisan commands
- **Consuming app owns**: User model, gate definition (`viewCompanion`), published migrations, published config

### Core Classes

| Class | Purpose |
|---|---|
| `CompanionServiceProvider` | Auto-discovered. Registers routes, middleware, gate, commands, Livewire components |
| `FeatureRegistry` | Resolves which features are enabled, builds `/capabilities` response |
| `TokenService` | Creates agents, hashes tokens (SHA-256), validates tokens |
| `CompanionAuthMiddleware` | Bearer token auth, IP allowlist, HTTPS enforcement |
| `CompanionScopeMiddleware` | Per-route scope enforcement |
| `ModelBrowserService` | Uses `mateffy/laravel-introspect` for model discovery, handles record browsing |

### Features

- Token auth with scopes: Example:

@verbatim
<code-snippet name="Creating an Agent" lang="php">
$tokenService = app(TokenService::class);
$result = $tokenService->createAgent('My Device', ['models:read', 'routes:read']);
// $result->plainToken — show once
// $result->agent — CompanionAgent model
</code-snippet>
@endverbatim

- Model browser opt-in via `CompanionSerializable`. Example:

@verbatim
<code-snippet name="CompanionSerializable" lang="php">
use Clinically\Companion\Contracts\CompanionSerializable;

class Patient extends Model implements CompanionSerializable
{
    public function toCompanionArray(): array
    {
        return $this->only(['id', 'first_name', 'last_name']);
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
</code-snippet>
@endverbatim

- Custom feature registration. Example:

@verbatim
<code-snippet name="Custom Feature" lang="php">
Companion::registerFeature('custom-metrics', function (Router $router) {
    $router->get('/custom-metrics', CustomMetricsController::class)
        ->middleware('companion.scope:custom-metrics:read');
});
</code-snippet>
@endverbatim

### Conventions

- `declare(strict_types=1)` in all PHP files
- Models/tables resolved via `CompanionConfig::model()` / `CompanionConfig::table()`
- Disabled features have zero runtime cost — routes are never registered
- All write operations dispatch `MutatingAction` events and are audit-logged
- HTTPS enforced in production; bearer tokens are credentials
