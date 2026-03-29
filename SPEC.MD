# `clinically/laravel-companion` — Implementation Spec

> A Laravel package that exposes a structured JSON API for mobile and external tooling to inspect, monitor, and manage Laravel applications. Ships with an admin dashboard (Blade + Livewire) for managing authenticated agents. Designed as the server-side companion to a native iOS app, but protocol-agnostic — any HTTP client can consume the API.

---

## 1. Package Identity

**Name:** `clinically/laravel-companion`
**Namespace:** `Clinically\Companion`
**License:** MIT
**Minimum requirements:** PHP 8.2+, Laravel 11+
**Key dependencies:**

- `capevace/laravel-introspect` — model/route/class/view introspection engine
- `laravel/sanctum` — token-based API authentication (peer dependency, not forced — many apps already have it)
- `simplesoftwareio/simple-qrcode` — QR code generation for device pairing
- `livewire/livewire` — admin dashboard components (peer dependency)

---

## 2. Installation & Setup

### 2.1 Install

```bash
composer require clinically/laravel-companion
```

### 2.2 Publish & Migrate

```bash
php artisan companion:install
```

This single command:

- Publishes `config/companion.php`
- Publishes migrations for the `companion_agents` and `companion_audit_logs` tables
- Runs the migrations
- Generates a first agent token interactively (name, scopes) and displays the QR code in-terminal as ASCII art
- Registers the Companion service provider if not already auto-discovered

### 2.3 Artisan Commands

| Command                    | Purpose                                                                   |
| -------------------------- | ------------------------------------------------------------------------- |
| `companion:install`        | One-time setup: publish config, run migrations, create first agent        |
| `companion:agent`          | Create a new agent token interactively (name, scopes, expiry)             |
| `companion:pair {agent}`   | Display a QR code in terminal for an existing agent (for mobile scanning) |
| `companion:agents`         | List all agents with status, last seen, scopes, expiry                    |
| `companion:revoke {agent}` | Revoke an agent token immediately                                         |
| `companion:prune-audit`    | Prune audit log entries older than configured retention period            |

---

## 3. Configuration (`config/companion.php`)

```php
return [

    /*
    |--------------------------------------------------------------------------
    | Route Prefix & Domain
    |--------------------------------------------------------------------------
    */
    'path' => env('COMPANION_PATH', 'companion'),

    'domain' => env('COMPANION_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    | The middleware stack applied to all Companion API routes.
    | The dashboard routes additionally receive 'web' and the
    | auth gate below.
    */
    'middleware' => ['throttle:companion'],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Auth (Horizon-style)
    |--------------------------------------------------------------------------
    | In local environment the dashboard is open. In production
    | the gate below must return true.
    */
    'auth' => [
        'gate' => 'viewCompanion',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model & Trait
    |--------------------------------------------------------------------------
    */
    'user_model' => env('COMPANION_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Agent Token Settings
    |--------------------------------------------------------------------------
    */
    'agents' => [
        'token_prefix' => 'cmp_',
        'default_expiry_days' => 90,    // null for never
        'max_agents' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Scopes
    |--------------------------------------------------------------------------
    | Every scope the package understands. Agents are granted a
    | subset at creation. Scopes are only enforceable for features
    | that are enabled below — disabling a feature implicitly
    | revokes the scope regardless of what the agent holds.
    */
    'scopes' => [
        'models:read',
        'models:browse',
        'routes:read',
        'commands:list',
        'commands:execute',
        'queues:read',
        'queues:write',
        'cache:read',
        'cache:write',
        'config:read',
        'logs:read',
        'schedule:read',
        'migrations:read',
        'events:read',
        'environment:read',
        'horizon:read',
        'horizon:write',
        'pulse:read',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Scope Presets
    |--------------------------------------------------------------------------
    */
    'scope_presets' => [
        'read-only' => [
            'models:read', 'routes:read', 'commands:list',
            'queues:read', 'cache:read', 'config:read',
            'logs:read', 'schedule:read', 'migrations:read',
            'events:read', 'environment:read',
        ],
        'operator'  => ['*:read', 'queues:write', 'cache:write', 'commands:execute'],
        'admin'     => ['*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Features (Enable/Disable Endpoint Groups)
    |--------------------------------------------------------------------------
    | Master switches for each feature group. When a feature is
    | disabled, its routes are never registered, its scopes are
    | inert, and the /capabilities endpoint reports it as
    | unavailable. The mobile app uses /capabilities to
    | dynamically show/hide UI sections.
    |
    | Each feature can be:
    |   true     — enabled
    |   false    — disabled, routes not registered
    |   'read'   — only read endpoints registered (where applicable)
    |
    | Features with sub-keys allow granular control over
    | individual operations within the group.
    |--------------------------------------------------------------------------
    */
    'features' => [

        'environment' => true,

        'models' => [
            'enabled'  => true,
            'browse'   => true,         // record listing/detail — false keeps schema-only
        ],

        'routes' => true,

        'commands' => [
            'enabled'  => true,
            'execute'  => true,         // false = list-only, no execution
        ],

        'queues' => [
            'enabled'  => true,
            'write'    => true,         // retry/delete/flush — false = read-only
        ],

        'cache' => [
            'enabled'  => true,
            'read'     => true,         // get individual keys
            'write'    => true,         // forget/flush
        ],

        'config' => true,

        'logs' => [
            'enabled'  => true,
            'stream'   => true,         // SSE live tail
        ],

        'schedule' => true,

        'migrations' => true,

        'events' => true,

        // Auto-detected integrations.
        // Set to false to disable even if the package is installed.
        // Set to true to enable (still requires the package to be installed).
        'horizon' => [
            'enabled'  => true,         // auto-detect: class_exists check
            'write'    => true,         // pause/continue/terminate
        ],

        'pulse' => [
            'enabled'  => true,         // auto-detect: class_exists check
        ],

        'telescope' => [
            'enabled'  => false,        // off by default — usually dev-only
        ],

        // The admin dashboard itself can be disabled
        // (e.g. if you only want the API, or embed
        // components into your own admin panel).
        'dashboard' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelisted Artisan Commands
    |--------------------------------------------------------------------------
    | Only commands in this list can be executed via the API when
    | features.commands.execute is true and the agent holds the
    | commands:execute scope.
    */
    'whitelisted_commands' => [
        'cache:clear',
        'config:clear',
        'view:clear',
        'route:clear',
        'event:clear',
        'queue:restart',
        'horizon:pause',
        'horizon:continue',
        'horizon:terminate',
        'optimize:clear',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blacklisted Commands (can never be whitelisted)
    |--------------------------------------------------------------------------
    */
    'blacklisted_commands' => [
        'migrate',
        'migrate:fresh',
        'migrate:reset',
        'migrate:rollback',
        'db:wipe',
        'db:seed',
        'key:generate',
        'tinker',
        'env',
        'down',
        'up',
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Browser
    |--------------------------------------------------------------------------
    */
    'models' => [
        'paths' => ['app/Models'],
        'max_per_page' => 100,
        'default_per_page' => 25,
        'eager_load_depth' => 1,
        'hidden_columns' => [
            'password', 'remember_token',
            'two_factor_secret', 'two_factor_recovery_codes',
        ],
        'redact_patterns' => ['/password/i', '/secret/i', '/token/i', '/key/i'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Config Redaction
    |--------------------------------------------------------------------------
    */
    'config_redaction' => [
        'patterns' => ['/password/i', '/secret/i', '/key$/i', '/token/i', '/private/i', '/credential/i'],
        'always_redact' => [
            'app.key',
            'database.connections.*.password',
            'mail.mailers.*.password',
            'services.*.secret',
        ],
        'never_redact' => [
            'app.name', 'app.env', 'app.debug',
            'app.url', 'app.timezone',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Viewer
    |--------------------------------------------------------------------------
    */
    'logs' => [
        'path' => storage_path('logs'),
        'max_file_size_mb' => 50,
        'tail_lines' => 500,
        'sse_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Log
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'retention_days' => 90,
        'log_reads' => false,
        'log_writes' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'api' => 120,          // requests per minute per agent
        'sse' => 5,            // concurrent SSE connections per agent
    ],
];
```

---

## 4. Feature Resolution

### 4.1 How Features Are Resolved at Boot

The `CompanionServiceProvider` reads `config('companion.features')` during the `boot` phase and only registers routes for enabled features. This means disabled features have zero runtime cost — no routes, no middleware, no controller instantiation.

```
FeatureRegistry::resolve('models')        → true/false
FeatureRegistry::resolve('models.browse') → true/false
FeatureRegistry::resolve('horizon')       → true/false (also checks class_exists)
```

### 4.2 Resolution Rules

| Config value                      | Behaviour                                                 |
| --------------------------------- | --------------------------------------------------------- |
| `true`                            | Feature fully enabled, all sub-features default to `true` |
| `false`                           | Feature fully disabled, no routes registered              |
| `array` with `'enabled' => true`  | Feature enabled, sub-features controlled individually     |
| `array` with `'enabled' => false` | Feature fully disabled regardless of sub-feature values   |
| Sub-feature `true`                | That specific operation is available                      |
| Sub-feature `false`               | Routes for that operation are not registered              |

### 4.3 Auto-Detection for Integrations

For `horizon`, `pulse`, and `telescope`, even if `enabled` is `true` in config, the feature is only actually enabled if the corresponding package is installed:

```php
// Pseudo-code in FeatureRegistry
if ($feature === 'horizon') {
    return config('companion.features.horizon.enabled', true)
        && class_exists(\Laravel\Horizon\Horizon::class);
}
```

### 4.4 `/capabilities` Endpoint

The mobile app calls `GET /companion/api/capabilities` immediately after pairing. This returns the resolved state of every feature for the authenticated agent — combining the global feature config with the agent's granted scopes:

```json
{
    "data": {
        "features": {
            "environment":  { "available": true,  "read": true },
            "models":       { "available": true,  "read": true, "browse": true },
            "routes":       { "available": true,  "read": true },
            "commands":     { "available": true,  "list": true, "execute": false },
            "queues":       { "available": true,  "read": true, "write": false },
            "cache":        { "available": true,  "read": true, "write": false },
            "config":       { "available": true,  "read": true },
            "logs":         { "available": true,  "read": true, "stream": true },
            "schedule":     { "available": true,  "read": true },
            "migrations":   { "available": true,  "read": true },
            "events":       { "available": true,  "read": true },
            "horizon":      { "available": true,  "read": true, "write": false },
            "pulse":        { "available": false },
            "telescope":    { "available": false }
        }
    }
}
```

`available: false` means the feature is disabled globally (config or package not installed).
Individual operation booleans (`read`, `write`, `browse`, `execute`, `stream`) reflect both the global config AND the agent's scopes. A feature can be globally enabled but `write: false` for a specific agent because they lack the write scope.

The mobile app caches this response and uses it to hide/show entire UI sections and individual action buttons.

---

## 5. Database Schema

### 5.1 `companion_agents`

| Column            | Type                   | Notes                                                              |
| ----------------- | ---------------------- | ------------------------------------------------------------------ |
| `id`              | `ulid` (primary)       |                                                                    |
| `name`            | `string`               | Human-readable label ("Wojt's iPhone", "CI Runner")                |
| `token_hash`      | `string` (unique)      | SHA-256 hash of the plain token                                    |
| `token_prefix`    | `string`               | First 8 chars of the plain token, for identification in audit logs |
| `scopes`          | `json`                 | Array of granted scope strings                                     |
| `last_seen_at`    | `timestamp` (nullable) | Updated on each request (throttled to once per minute)             |
| `last_ip`         | `string` (nullable)    |                                                                    |
| `last_user_agent` | `string` (nullable)    | Captures app version                                               |
| `expires_at`      | `timestamp` (nullable) | Null = never expires                                               |
| `revoked_at`      | `timestamp` (nullable) | Soft-revoke                                                        |
| `created_by`      | `foreignId` (nullable) | User who created via dashboard (null if via artisan)               |
| `metadata`        | `json` (nullable)      | Extensible: device name, OS version, app version from client       |
| `created_at`      | `timestamp`            |                                                                    |
| `updated_at`      | `timestamp`            |                                                                    |

Indexes: `token_hash` (unique), `revoked_at` (for active agent filtering).

### 5.2 `companion_audit_logs`

| Column          | Type                | Notes                                                             |
| --------------- | ------------------- | ----------------------------------------------------------------- |
| `id`            | `ulid` (primary)    |                                                                   |
| `agent_id`      | `foreignId`         | References `companion_agents`                                     |
| `action`        | `string`            | Endpoint/action: `models.list`, `commands.execute`, `cache.flush` |
| `method`        | `string`            | HTTP method                                                       |
| `path`          | `string`            | Request path                                                      |
| `payload`       | `json` (nullable)   | Sanitised request payload (redacted sensitive values)             |
| `response_code` | `integer`           | HTTP status returned                                              |
| `ip`            | `string`            |                                                                   |
| `user_agent`    | `string` (nullable) |                                                                   |
| `duration_ms`   | `integer`           | Request processing time                                           |
| `created_at`    | `timestamp`         |                                                                   |

Indexes: `agent_id + created_at`, `action + created_at`. Prunable via `companion:prune-audit` and Laravel's `Prunable` trait.

---

## 6. Authentication & Authorisation

### 6.1 Token Format

Pattern: `cmp_{ulid}|{random_64_chars}`

The ULID prefix allows quick visual identification. The full token is hashed (SHA-256) before storage. The plain token is displayed once at creation — in terminal (artisan) or dashboard (copy button + QR). Never retrievable after that.

### 6.2 Auth Flow

1. Mobile app sends `Authorization: Bearer cmp_01HX...` header.
2. `CompanionAuthMiddleware` extracts token, hashes it, looks up `companion_agents` by `token_hash`.
3. Validates: not revoked, not expired, IP allowlist (if configured).
4. Resolves the agent's scopes, cross-references with the resolved feature config.
5. Attaches the `CompanionAgent` model to the request via `$request->companionAgent()`.
6. Updates `last_seen_at`, `last_ip`, `last_user_agent` (throttled to once per minute per agent).

### 6.3 Scope Enforcement

Each route declares its required scope(s) via a middleware parameter:

```
Route::get('/models', ...)->middleware('companion.scope:models:read');
Route::post('/commands/{command}/run', ...)->middleware('companion.scope:commands:execute');
```

The middleware checks:
1. Is the feature globally enabled? (If not → `404`)
2. Does the agent hold the required scope? (If not → `403`)

This means a disabled feature returns `404` (it doesn't exist) while an enabled feature the agent can't access returns `403` (it exists but you lack permission). The distinction is deliberate.

### 6.4 QR Pairing Payload

```json
{
    "v": 1,
    "url": "https://app.example.com",
    "path": "/companion/api",
    "token": "cmp_01HX...|a3f8b2...",
    "name": "Production — Clinically",
    "env": "production",
    "expires_at": "2026-06-30T00:00:00Z"
}
```

The `v` field allows future format changes. The mobile app scans, stores the connection profile, calls `/ping` then `/capabilities`, and renders the UI accordingly.

---

## 7. Dashboard (Admin UI)

### 7.1 Access Control — Horizon Pattern

The package registers a `viewCompanion` gate. Identical to Horizon/Telescope:

```php
Gate::define('viewCompanion', function ($user = null) {
    if (app()->environment('local')) {
        return true;
    }
    return false; // developer must override
});
```

Dashboard sits at `/{path}/dashboard` (default: `/companion/dashboard`) behind `web` middleware + the gate. The entire dashboard is controlled by `features.dashboard` — set to `false` to disable it entirely if you only want the API or embed components into your own admin panel.

### 7.2 Dashboard Routes

| Route                                        | Purpose                                                                               |
| -------------------------------------------- | ------------------------------------------------------------------------------------- |
| `GET /companion/dashboard`                   | Overview: agent count, recent audit entries, environment summary, feature status grid |
| `GET /companion/dashboard/agents`            | Agent list: name, status, scopes, last seen, actions                                  |
| `GET /companion/dashboard/agents/create`     | Create agent: name, scope preset or custom, expiry                                    |
| `GET /companion/dashboard/agents/{agent}`    | Agent detail: activity log, metadata, scopes, revoke, regenerate QR                   |
| `POST /companion/dashboard/agents`           | Create action: generates token, shows token + QR once                                 |
| `DELETE /companion/dashboard/agents/{agent}` | Revoke agent                                                                          |
| `GET /companion/dashboard/audit`             | Audit log: filterable, searchable, paginated                                          |
| `GET /companion/dashboard/audit/{entry}`     | Full request/response detail                                                          |
| `GET /companion/dashboard/features`          | Feature status overview: enabled/disabled, toggle (updates config cache, not file)    |

### 7.3 Livewire Components (Reusable)

These components are independently embeddable — developers can mount them in Filament, Nova, custom Blade layouts, or anywhere Livewire runs. This is the primary extensibility mechanism for teams who want Companion management inside their existing admin panel rather than a separate dashboard.

| Component       | Tag                                                       | Purpose                                                                          |
| --------------- | --------------------------------------------------------- | -------------------------------------------------------------------------------- |
| `AgentList`     | `<livewire:companion-agent-list />`                       | Table of all agents with inline revoke, status badges                            |
| `AgentCreator`  | `<livewire:companion-agent-creator />`                    | Full create form with scope picker, preset buttons, generates QR on success      |
| `AgentDetail`   | `<livewire:companion-agent-detail :agent="$agent" />`     | Single agent info, metadata, scope list, activity feed                           |
| `QrCode`        | `<livewire:companion-qr-code :agent="$agent" />`          | Renders QR for pairing, with copy-token button and expiry countdown              |
| `AuditLog`      | `<livewire:companion-audit-log :agent="$agent" />`        | Filterable audit log, optionally scoped to a single agent                        |
| `ScopePicker`   | `<livewire:companion-scope-picker wire:model="scopes" />` | Multi-select scope UI with preset buttons, emits selected scopes                 |
| `FeatureStatus` | `<livewire:companion-feature-status />`                   | Grid showing all features with enabled/disabled badges and integration detection |

All components ship with semantic HTML and Tailwind-styled default views, publishable:

```bash
php artisan vendor:publish --tag=companion-views
```

### 7.4 Dashboard Styling

Self-contained Blade layout with a vendored Tailwind CSS build — single file, no dependency on host app styles. Dark mode via `prefers-color-scheme`. Same approach as Horizon.

---

## 8. HasCompanionAccess Trait

The package provides a `HasCompanionAccess` trait that developers add to their User model (or whatever model is configured in `companion.user_model`). This is optional — the dashboard and gate work without it — but it provides convenient helpers.

```php
use Clinically\Companion\Traits\HasCompanionAccess;

class User extends Authenticatable
{
    use HasCompanionAccess;
}
```

### What the Trait Provides

**Relationship:**
- `$user->companionAgents()` — `hasMany` relationship to `CompanionAgent` models this user created via the dashboard.

**Convenience methods:**
- `$user->canAccessCompanion(): bool` — checks the `viewCompanion` gate for this user. Useful in Filament navigation or custom menu visibility.
- `$user->createCompanionAgent(string $name, array $scopes, ?Carbon $expiresAt): CompanionAgentToken` — creates an agent programmatically and returns a value object containing the plain token (shown once) and the persisted `CompanionAgent` model.
- `$user->revokeCompanionAgent(string|CompanionAgent $agent): void` — revokes an agent by ID or model.
- `$user->companionAuditLog(): Builder` — query builder scoped to audit log entries for agents this user created.

**Extending the gate:**

Developers can use the trait's `canAccessCompanion()` method in their gate definition:

```php
Gate::define('viewCompanion', function (User $user) {
    return $user->is_admin; // or any custom logic
});
```

Or use the provided `CompanionAccessPolicy` which the trait respects if registered.

---

## 9. API Endpoints

All endpoints prefixed with `/{path}/api` (default: `/companion/api`).

### 9.1 Response Envelope

All responses follow a consistent structure:

```json
{
    "data": { },
    "meta": {
        "environment": "production",
        "laravel_version": "12.45.0",
        "php_version": "8.3.12",
        "companion_version": "1.2.0",
        "timestamp": "2026-03-30T12:00:00Z"
    }
}
```

Paginated responses add standard Laravel pagination keys inside `meta`. Error responses use:

```json
{
    "error": {
        "code": "scope_denied",
        "message": "Agent lacks required scope: commands:execute"
    },
    "meta": { }
}
```

### 9.2 Handshake & Discovery

**Always enabled — not controlled by `features` config.**

| Method | Path            | Scope       | Description                                               |
| ------ | --------------- | ----------- | --------------------------------------------------------- |
| `GET`  | `/ping`         | (auth only) | Returns `{ "status": "ok" }`. Connection health check.    |
| `GET`  | `/capabilities` | (auth only) | Resolved feature matrix for this agent (see Section 4.4). |

### 9.3 Environment

**Feature key:** `features.environment`

| Method | Path           | Scope              | Description                                                                                                                                             |
| ------ | -------------- | ------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`  | `/environment` | `environment:read` | App name, environment, debug mode, URL, timezone, PHP/Laravel versions, database/cache/queue/mail drivers. Structured safe values only — no raw `.env`. |

### 9.4 Models

**Feature key:** `features.models` (with `features.models.browse` sub-feature)

Powered by `laravel-introspect` for schema/metadata. Record browsing uses direct Eloquent queries.

| Method | Path                            | Scope           | Feature gate     | Description                                                                                                                                                                                                                                                                          |
| ------ | ------------------------------- | --------------- | ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `GET`  | `/models`                       | `models:read`   | `models.enabled` | List all discovered Eloquent models with table name, record count, soft-delete flag.                                                                                                                                                                                                 |
| `GET`  | `/models/{model}`               | `models:read`   | `models.enabled` | Full metadata: columns (name, type, nullable, default, cast, index), fillable, guarded, hidden, appends, relationships (name, type, related model, foreign key), local scopes, observers.                                                                                            |
| `GET`  | `/models/{model}/relationships` | `models:read`   | `models.enabled` | Relationship graph data for the selected model: relationship name, type, related model class, foreign/local keys. Enough for the mobile app to render a visual map.                                                                                                                  |
| `GET`  | `/models/{model}/records`       | `models:browse` | `models.browse`  | Paginated records. Query params: `page`, `per_page`, `sort`, `direction`, `filter[column]`, `filter[column:operator]` (gt, lt, gte, lte, like, null, not\_null), `scope` (named scope), `search` (across fillable string columns). Hidden columns stripped, redacted columns masked. |
| `GET`  | `/models/{model}/records/{id}`  | `models:browse` | `models.browse`  | Single record with eager-loaded relationships (configurable depth). Includes related record counts for `hasMany`/`belongsToMany`.                                                                                                                                                    |

`{model}` accepts the short class name (`User`) or fully-qualified (`App%5CModels%5CUser`). Short names are resolved from the discovered model list — ambiguities return a `409` with all matching fully-qualified names.

### 9.5 Routes

**Feature key:** `features.routes`

| Method | Path      | Scope         | Description                                                                                                                                                                                                                    |
| ------ | --------- | ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `GET`  | `/routes` | `routes:read` | Full route list: method(s), URI, name, action (controller@method or closure), middleware stack, domain, parameters. Filterable by query params: `method`, `name` (prefix match), `uri` (contains), `middleware`, `controller`. |

### 9.6 Artisan Commands

**Feature key:** `features.commands` (with `features.commands.execute` sub-feature)

| Method | Path                      | Scope              | Feature gate       | Description                                                                                                                                                                                                                                                                                                                         |
| ------ | ------------------------- | ------------------ | ------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`  | `/commands`               | `commands:list`    | `commands.enabled` | All registered commands: signature, description, arguments (name, description, required, default), options (name, description, shortcut, default, value\_required).                                                                                                                                                                 |
| `GET`  | `/commands/whitelisted`   | `commands:list`    | `commands.enabled` | Subset of above — only commands in the whitelist. The mobile app uses this to populate the quick-action grid.                                                                                                                                                                                                                       |
| `POST` | `/commands/{command}/run` | `commands:execute` | `commands.execute` | Execute a whitelisted command. Request body: `{ "arguments": {}, "options": {} }`. Validates against whitelist, validates against blacklist (belt and suspenders), runs synchronously, returns `{ "exit_code": 0, "output": "..." }`. Non-whitelisted commands return `403`. Blacklisted commands return `403` with explicit error. |

### 9.7 Queues & Jobs

**Feature key:** `features.queues` (with `features.queues.write` sub-feature)

| Method   | Path                        | Scope          | Feature gate     | Description                                                                                                                |
| -------- | --------------------------- | -------------- | ---------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `GET`    | `/queues`                   | `queues:read`  | `queues.enabled` | Queue connection info, sizes per queue name (via `Queue::size()`).                                                         |
| `GET`    | `/queues/failed`            | `queues:read`  | `queues.enabled` | Paginated failed jobs: id, uuid, connection, queue, exception (truncated), failed\_at. Sortable, filterable by queue name. |
| `GET`    | `/queues/failed/{id}`       | `queues:read`  | `queues.enabled` | Single failed job with full exception, payload (decoded), stack trace.                                                     |
| `POST`   | `/queues/failed/{id}/retry` | `queues:write` | `queues.write`   | Retry a specific failed job. Returns new job ID.                                                                           |
| `POST`   | `/queues/failed/retry-all`  | `queues:write` | `queues.write`   | Retry all failed jobs. Returns count retried.                                                                              |
| `DELETE` | `/queues/failed/{id}`       | `queues:write` | `queues.write`   | Delete a specific failed job.                                                                                              |
| `DELETE` | `/queues/failed`            | `queues:write` | `queues.write`   | Flush all failed jobs. Requires `confirm: true` in request body.                                                           |

### 9.8 Horizon

**Feature key:** `features.horizon` (auto-detected + config)

| Method | Path                    | Scope           | Feature gate      | Description                                                              |
| ------ | ----------------------- | --------------- | ----------------- | ------------------------------------------------------------------------ |
| `GET`  | `/horizon/status`       | `horizon:read`  | `horizon.enabled` | Master supervisor status, paused state, current processes.               |
| `GET`  | `/horizon/metrics/jobs` | `horizon:read`  | `horizon.enabled` | Job throughput, average runtime, failure count over configurable period. |
| `GET`  | `/horizon/recent-jobs`  | `horizon:read`  | `horizon.enabled` | Recent jobs: name, status, queue, runtime, completed\_at. Paginated.     |
| `POST` | `/horizon/pause`        | `horizon:write` | `horizon.write`   | Pause all supervisors.                                                   |
| `POST` | `/horizon/continue`     | `horizon:write` | `horizon.write`   | Continue all supervisors.                                                |
| `POST` | `/horizon/terminate`    | `horizon:write` | `horizon.write`   | Terminate the master supervisor (triggers restart if supervised).        |

### 9.9 Cache

**Feature key:** `features.cache` (with `features.cache.read` and `features.cache.write` sub-features)

| Method   | Path           | Scope         | Feature gate    | Description                                                                                                                              |
| -------- | -------------- | ------------- | --------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`    | `/cache/info`  | `cache:read`  | `cache.enabled` | Cache driver, store name. If Redis: connection info, dbsize.                                                                             |
| `GET`    | `/cache/{key}` | `cache:read`  | `cache.read`    | Retrieve a cache value with remaining TTL. Returns the value as JSON (if serialisable) or a type descriptor. `404` if key doesn't exist. |
| `DELETE` | `/cache/{key}` | `cache:write` | `cache.write`   | Forget a specific cache key.                                                                                                             |
| `POST`   | `/cache/flush` | `cache:write` | `cache.write`   | Flush entire cache. Requires `{ "confirm": true }` in body. Audit-logged with elevated priority.                                         |

### 9.10 Config

**Feature key:** `features.config`

| Method | Path            | Scope         | Description                                                                                                                                                                                  |
| ------ | --------------- | ------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`  | `/config`       | `config:read` | Full config tree as nested JSON. Sensitive values replaced with `"••••••••"` per redaction rules. Includes a `_redacted: true` flag on redacted keys so the mobile app can show a lock icon. |
| `GET`  | `/config/{key}` | `config:read` | Specific config value by dot notation (e.g., `database.default`). Redaction still applies.                                                                                                   |

### 9.11 Logs

**Feature key:** `features.logs` (with `features.logs.stream` sub-feature)

| Method | Path                  | Scope       | Feature gate   | Description                                                                                                                                                                                             |
| ------ | --------------------- | ----------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET`  | `/logs`               | `logs:read` | `logs.enabled` | List log files: filename, size, last\_modified.                                                                                                                                                         |
| `GET`  | `/logs/{file}`        | `logs:read` | `logs.enabled` | Parsed log entries from a file: level, datetime, message, context, stack trace (collapsed). Paginated, reverse-chronological. Query params: `level` (filter), `search` (full-text), `page`, `per_page`. |
| `GET`  | `/logs/{file}/stream` | `logs:read` | `logs.stream`  | SSE (Server-Sent Events) endpoint for live log tailing. Sends parsed log entries as they appear. Client can filter by level via query param. Connection subject to SSE rate limit.                      |

### 9.12 Scheduler

**Feature key:** `features.schedule`

| Method | Path        | Scope           | Description                                                                                                                                                                                            |
| ------ | ----------- | --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `GET`  | `/schedule` | `schedule:read` | All scheduled commands/jobs: command or job class, cron expression, human-readable description, timezone, next due datetime (in both server and UTC), mutex info (without overlapping, on one server). |

### 9.13 Migrations

**Feature key:** `features.migrations`

| Method | Path          | Scope             | Description                                                                |
| ------ | ------------- | ----------------- | -------------------------------------------------------------------------- |
| `GET`  | `/migrations` | `migrations:read` | Migration status: filename, batch number, ran (boolean). Grouped by batch. |

### 9.14 Events

**Feature key:** `features.events`

| Method | Path      | Scope         | Description                                                                                        |
| ------ | --------- | ------------- | -------------------------------------------------------------------------------------------------- |
| `GET`  | `/events` | `events:read` | Registered events with their listeners. Each entry: event class, listener classes, whether queued. |

### 9.15 Pulse

**Feature key:** `features.pulse` (auto-detected + config)

| Method | Path                   | Scope        | Description                                                                 |
| ------ | ---------------------- | ------------ | --------------------------------------------------------------------------- |
| `GET`  | `/pulse/servers`       | `pulse:read` | Server metrics from Pulse's recorded data: CPU, memory, storage per server. |
| `GET`  | `/pulse/slow-queries`  | `pulse:read` | Slowest queries over configurable period.                                   |
| `GET`  | `/pulse/slow-requests` | `pulse:read` | Slowest endpoints over configurable period.                                 |
| `GET`  | `/pulse/slow-jobs`     | `pulse:read` | Slowest jobs over configurable period.                                      |
| `GET`  | `/pulse/exceptions`    | `pulse:read` | Exception summary: class, message, count, last occurrence.                  |
| `GET`  | `/pulse/usage`         | `pulse:read` | Application usage: top users by requests, top users by jobs.                |

These endpoints query Pulse's aggregation tables directly. They do not render Pulse cards — they return structured JSON suitable for mobile rendering.

---

## 10. Route Registration Architecture

### 10.1 Conditional Registration

The service provider iterates the `features` config and only calls `Route::group()` for enabled features. This is not middleware-level gating — routes for disabled features literally do not exist in Laravel's route collection.

```php
// Pseudo-code in CompanionServiceProvider::boot()
$features = app(FeatureRegistry::class);

Route::prefix(config('companion.path') . '/api')
    ->middleware(['api', 'companion.auth', ...config('companion.middleware')])
    ->group(function () use ($features) {
        // Always registered
        Route::get('/ping', PingController::class);
        Route::get('/capabilities', CapabilitiesController::class);

        if ($features->enabled('environment')) {
            Route::get('/environment', EnvironmentController::class)
                ->middleware('companion.scope:environment:read');
        }

        if ($features->enabled('models')) {
            Route::get('/models', [ModelController::class, 'index'])
                ->middleware('companion.scope:models:read');
            Route::get('/models/{model}', [ModelController::class, 'show'])
                ->middleware('companion.scope:models:read');

            if ($features->enabled('models.browse')) {
                Route::get('/models/{model}/records', [ModelRecordController::class, 'index'])
                    ->middleware('companion.scope:models:browse');
                Route::get('/models/{model}/records/{id}', [ModelRecordController::class, 'show'])
                    ->middleware('companion.scope:models:browse');
            }
        }

        // ... same pattern for all features
    });
```

### 10.2 Route Naming Convention

All routes are named `companion.api.{feature}.{action}`:

```
companion.api.ping
companion.api.capabilities
companion.api.models.index
companion.api.models.show
companion.api.models.records.index
companion.api.models.records.show
companion.api.commands.index
companion.api.commands.run
companion.api.queues.failed.retry
...
```

Dashboard routes follow `companion.dashboard.{section}.{action}`.

---

## 11. Middleware Stack

### 11.1 Custom Middleware

| Middleware                   | Alias                         | Purpose                                                                                                                                                                                                               |
| ---------------------------- | ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `CompanionAuthMiddleware`    | `companion.auth`              | Token extraction, agent lookup, validation (not expired, not revoked, IP allowlist). Attaches `CompanionAgent` to request.                                                                                            |
| `CompanionScopeMiddleware`   | `companion.scope:{scope}`     | Checks agent holds the required scope. Returns `403` if not.                                                                                                                                                          |
| `CompanionAuditMiddleware`   | `companion.audit`             | Logs the request to `companion_audit_logs` based on config (reads, writes, or both). Applied globally to all API routes — individual routes do not need to opt in.                                                    |
| `CompanionFeatureMiddleware` | `companion.feature:{feature}` | Belt-and-suspenders check that the feature is enabled at runtime. Normally redundant (route wouldn't exist if disabled) but protects against cached route lists surviving a config change. Returns `404` if disabled. |

### 11.2 Applied Stack (API routes)

```
api → companion.auth → companion.audit → throttle:companion → companion.scope:{scope}
```

### 11.3 Applied Stack (Dashboard routes)

```
web → auth → can:viewCompanion
```

---

## 12. Extensibility

### 12.1 Custom Features

Developers can register additional feature groups that follow the same enable/disable/scope pattern:

```php
// In a service provider
Companion::registerFeature('custom-metrics', function (Router $router) {
    $router->get('/custom-metrics', CustomMetricsController::class)
        ->middleware('companion.scope:custom-metrics:read');
});
```

This adds the feature to the config schema, the `/capabilities` response, and the dashboard's feature status grid.

### 12.2 Event Hooks

The package dispatches events for key actions, allowing developers to integrate with their own notification/logging:

| Event                | Payload                                                     | When                                               |
| -------------------- | ----------------------------------------------------------- | -------------------------------------------------- |
| `AgentCreated`       | `CompanionAgent $agent`, `?User $creator`                   | New agent token generated                          |
| `AgentRevoked`       | `CompanionAgent $agent`, `?User $revoker`                   | Agent revoked                                      |
| `AgentAuthenticated` | `CompanionAgent $agent`, `Request $request`                 | Successful API auth                                |
| `AgentAuthFailed`    | `string $tokenPrefix`, `string $reason`, `Request $request` | Failed auth attempt                                |
| `CommandExecuted`    | `CompanionAgent $agent`, `string $command`, `int $exitCode` | Artisan command run via API                        |
| `MutatingAction`     | `CompanionAgent $agent`, `string $action`, `array $payload` | Any write operation (cache flush, job retry, etc.) |

### 12.3 Macros on CompanionAgent

The `CompanionAgent` model uses `Macroable`, allowing developers to add custom methods:

```php
CompanionAgent::macro('isFromMobileApp', function () {
    return str_contains($this->last_user_agent ?? '', 'CompanionApp/');
});
```

### 12.4 Custom Model Serialisation

For the model browser, developers can control what's exposed per-model by implementing a `CompanionSerializable` interface or defining a `toCompanionArray()` method on their Eloquent model:

```php
class Patient extends Model
{
    public function toCompanionArray(): array
    {
        return $this->only(['id', 'first_name', 'last_name', 'date_of_birth', 'mrn']);
    }

    public function companionRelationships(): array
    {
        return ['appointments', 'referrals']; // only these are browsable
    }

    public function companionScopes(): array
    {
        return ['active', 'withUpcoming']; // only these scopes exposed
    }
}
```

If the interface is not implemented, the package falls back to the standard hidden/redaction rules from config. This is critical for healthcare data — Clinically models can opt into exactly what's browsable from a phone.

---

## 13. Security Considerations

### 13.1 Defence in Depth

Access to any endpoint requires ALL of the following to be true:
1. Feature is enabled in config
2. Route is registered (consequence of #1)
3. Agent token is valid (not expired, not revoked)
4. Agent holds the required scope
5. Request passes rate limiting
6. IP is in allowlist (if configured)

### 13.2 Token Security

- Tokens are SHA-256 hashed before storage — database breach does not expose usable tokens.
- Token prefix (`cmp_01HX...`) is stored separately for audit log correlation without requiring the full token.
- Tokens are generated with `Str::random(64)` — 384 bits of entropy.
- Expired/revoked tokens are rejected at the middleware layer before any controller logic runs.

### 13.3 Model Browser Safety

- Hidden columns (`password`, `remember_token`, etc.) are stripped server-side before serialisation.
- Columns matching redaction patterns are replaced with `"••••••••"`.
- `toCompanionArray()` provides model-level opt-in control.
- Pagination is enforced — no unbounded `SELECT *` possible.
- `eager_load_depth` prevents recursive relationship loading.

### 13.4 Command Execution Safety

- Double-gate: whitelist (allow-list) AND blacklist (deny-list).
- Blacklist wins: even if someone manually adds `migrate` to the whitelist, the blacklist rejects it.
- Commands run synchronously with captured output — no shell injection possible (uses `Artisan::call()` internally).
- Execution is audit-logged with the full command signature and arguments.

### 13.5 Audit Trail

- Every write operation is logged regardless of `audit.log_reads` setting.
- Audit log includes agent identity, IP, user agent, payload, response code, and duration.
- Audit entries are immutable — no update/delete endpoints exposed.
- Retention is configurable and prunable via scheduled command.

### 13.6 Production Hardening Checklist

The `companion:install` command outputs a checklist after setup:

```
✅ Config published
✅ Migrations run
✅ First agent created

⚠️  Production hardening checklist:
   □ Define the viewCompanion gate in AppServiceProvider
   □ Set COMPANION_DOMAIN if using a separate domain
   □ Review features config — disable what you don't need
   □ Review whitelisted_commands — remove any you're uncomfortable with
   □ Consider IP allowlisting for agent tokens
   □ Add companion:prune-audit to your scheduler
   □ Ensure HTTPS is enforced (tokens are bearer credentials)
```

---

## 14. Testing

### 14.1 Package Test Suite

The package ships with a comprehensive test suite using Orchestra Testbench:

- **Unit tests:** FeatureRegistry resolution, scope matching, token hashing, config redaction, log parsing.
- **Feature tests:** Full HTTP tests for every endpoint with enabled/disabled feature permutations, scope enforcement, auth rejection, rate limiting.
- **Integration tests:** `laravel-introspect` integration for model/route discovery against a test application with known models.

### 14.2 Test Helpers for Consuming Apps

The package provides test helpers for developers writing tests in their own applications:

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

---

## 15. Package Structure

```
clinically/laravel-companion/
├── config/
│   └── companion.php
├── database/
│   └── migrations/
│       ├── create_companion_agents_table.php
│       └── create_companion_audit_logs_table.php
├── resources/
│   └── views/
│       ├── dashboard/
│       │   ├── layout.blade.php
│       │   ├── overview.blade.php
│       │   ├── agents/
│       │   │   ├── index.blade.php
│       │   │   ├── create.blade.php
│       │   │   └── show.blade.php
│       │   ├── audit/
│       │   │   ├── index.blade.php
│       │   │   └── show.blade.php
│       │   └── features.blade.php
│       └── livewire/
│           ├── agent-list.blade.php
│           ├── agent-creator.blade.php
│           ├── agent-detail.blade.php
│           ├── qr-code.blade.php
│           ├── audit-log.blade.php
│           ├── scope-picker.blade.php
│           └── feature-status.blade.php
├── src/
│   ├── CompanionServiceProvider.php
│   ├── CompanionApplicationServiceProvider.php  (publishable, for gate overrides)
│   ├── Companion.php                            (facade)
│   ├── FeatureRegistry.php
│   │
│   ├── Models/
│   │   ├── CompanionAgent.php
│   │   └── CompanionAuditLog.php
│   │
│   ├── Traits/
│   │   └── HasCompanionAccess.php
│   │
│   ├── Contracts/
│   │   └── CompanionSerializable.php            (toCompanionArray interface)
│   │
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── CompanionAuthMiddleware.php
│   │   │   ├── CompanionScopeMiddleware.php
│   │   │   ├── CompanionAuditMiddleware.php
│   │   │   └── CompanionFeatureMiddleware.php
│   │   └── Controllers/
│   │       ├── Api/
│   │       │   ├── PingController.php
│   │       │   ├── CapabilitiesController.php
│   │       │   ├── EnvironmentController.php
│   │       │   ├── ModelController.php
│   │       │   ├── ModelRecordController.php
│   │       │   ├── RouteController.php
│   │       │   ├── CommandController.php
│   │       │   ├── QueueController.php
│   │       │   ├── HorizonController.php
│   │       │   ├── CacheController.php
│   │       │   ├── ConfigController.php
│   │       │   ├── LogController.php
│   │       │   ├── ScheduleController.php
│   │       │   ├── MigrationController.php
│   │       │   ├── EventController.php
│   │       │   └── PulseController.php
│   │       └── Dashboard/
│   │           ├── OverviewController.php
│   │           ├── AgentController.php
│   │           ├── AuditController.php
│   │           └── FeatureController.php
│   │
│   ├── Livewire/
│   │   ├── AgentList.php
│   │   ├── AgentCreator.php
│   │   ├── AgentDetail.php
│   │   ├── QrCode.php
│   │   ├── AuditLog.php
│   │   ├── ScopePicker.php
│   │   └── FeatureStatus.php
│   │
│   ├── Console/
│   │   ├── InstallCommand.php
│   │   ├── AgentCommand.php
│   │   ├── PairCommand.php
│   │   ├── AgentsCommand.php
│   │   ├── RevokeCommand.php
│   │   └── PruneAuditCommand.php
│   │
│   ├── Events/
│   │   ├── AgentCreated.php
│   │   ├── AgentRevoked.php
│   │   ├── AgentAuthenticated.php
│   │   ├── AgentAuthFailed.php
│   │   ├── CommandExecuted.php
│   │   └── MutatingAction.php
│   │
│   ├── Services/
│   │   ├── TokenService.php           (generation, hashing, validation)
│   │   ├── ModelBrowserService.php     (wraps introspect + record queries)
│   │   ├── LogParserService.php        (Laravel log format → structured JSON)
│   │   ├── ConfigRedactionService.php  (pattern matching, redaction)
│   │   ├── QrPayloadService.php        (builds pairing JSON + QR)
│   │   └── AuditService.php            (write + prune)
│   │
│   └── Testing/
│       └── CompanionTestHelpers.php
│
├── tests/
│   ├── Unit/
│   ├── Feature/
│   └── TestCase.php
│
├── composer.json
├── LICENSE.md
└── README.md
```

---

## 16. Versioning & API Stability

### 16.1 API Versioning

The v1 API does not include a version prefix in the URL. If a breaking change is needed in future, a `/v2` prefix will be introduced and `/v1` will be maintained for a deprecation period. The `/capabilities` response includes the `companion_version` to allow the mobile app to detect compatibility.

### 16.2 Mobile App Compatibility

The mobile app should check `companion_version` from the `/capabilities` response and display a warning if the server package version is older than the minimum it supports. The `"v": 1` field in the QR pairing payload is the protocol version — distinct from the package semver.

### 16.3 Semver

The package follows strict semver:
- **Patch:** bug fixes, documentation, internal refactors.
- **Minor:** new features (endpoints, config keys), new scopes. Always backward compatible.
- **Major:** breaking changes to existing endpoint contracts, removed features, config structure changes.

Config keys are never removed in minor versions — they are deprecated and ignored, then removed in the next major.
