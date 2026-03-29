<?php

declare(strict_types=1);

namespace Clinically\Companion\Services;

use Clinically\Companion\Contracts\CompanionSerializable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Mateffy\Introspect\DTO\ModelProperty;
use Mateffy\Introspect\LaravelIntrospect;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class ModelBrowserService
{
    public function __construct(
        private readonly LaravelIntrospect $introspect,
    ) {}
    /**
     * Discover all Eloquent models using laravel-introspect.
     *
     * @return Collection<int, array{class: class-string<Model>, short_name: string, table: string, count: int, soft_deletes: bool}>
     */
    public function discoverModels(): Collection
    {
        /** @var \Illuminate\Support\Collection<int, \Mateffy\Introspect\DTO\Model> $models */
        $models = $this->introspect->models()->get();

        return $models
            ->map(function (\Mateffy\Introspect\DTO\Model $model) {
                /** @var class-string<Model> $class */
                $class = $model->classpath;
                /** @var Model $instance */
                $instance = new $class;

                return [
                    'class' => $class,
                    'short_name' => class_basename($class),
                    'table' => $instance->getTable(),
                    'count' => $this->safeCount($instance),
                    'soft_deletes' => $this->usesSoftDeletes($class),
                ];
            })
            ->values();
    }

    /**
     * Resolve a model class from a short name or fully-qualified name.
     *
     * @return class-string<Model>|null
     */
    public function resolveModelClass(string $identifier): ?string
    {
        $decoded = urldecode($identifier);

        if (class_exists($decoded) && is_subclass_of($decoded, Model::class)) {
            return $decoded;
        }

        $models = $this->discoverModels();
        $matches = $models->where('short_name', $identifier);

        if ($matches->count() === 1) {
            return $matches->first()['class'];
        }

        return null;
    }

    /**
     * Get metadata for a specific model using laravel-introspect for
     * property details and our own reflection for relationships/scopes.
     *
     * @param  class-string<Model>  $class
     * @return array<string, mixed>
     */
    public function getModelMetadata(string $class): array
    {
        $instance = new $class;
        $introspected = $this->introspect->model($class);

        return [
            'class' => $class,
            'short_name' => class_basename($class),
            'table' => $instance->getTable(),
            'connection' => $instance->getConnectionName(),
            'fillable' => $instance->getFillable(),
            'guarded' => $instance->getGuarded(),
            'hidden' => $instance->getHidden(),
            'casts' => $instance->getCasts(),
            'soft_deletes' => $this->usesSoftDeletes($class),
            'properties' => $introspected->properties
                ->map(fn (ModelProperty $prop) => [
                    'name' => $prop->name,
                    'types' => $prop->types->all(),
                    'fillable' => $prop->fillable,
                    'hidden' => $prop->hidden,
                    'appended' => $prop->appended,
                    'cast' => $prop->cast,
                    'readable' => $prop->readable,
                    'writable' => $prop->writable,
                ])
                ->values()
                ->all(),
            'schema' => $introspected->schema(),
            'relationships' => $this->discoverRelationships($class, $instance),
            'scopes' => $this->discoverScopes(new ReflectionClass($class)),
        ];
    }

    /**
     * Get relationship graph for a model.
     *
     * @param  class-string<Model>  $class
     * @return list<array<string, mixed>>
     */
    public function getRelationships(string $class): array
    {
        return $this->discoverRelationships($class, new $class);
    }

    /**
     * Serialise a model record for the API response.
     *
     * @return array<string, mixed>
     */
    public function serialiseRecord(Model $record): array
    {
        if ($record instanceof CompanionSerializable) {
            return $record->toCompanionArray();
        }

        $attributes = $record->toArray();

        $hidden = (array) config('companion.models.hidden_columns', []);
        foreach ($hidden as $column) {
            unset($attributes[$column]);
        }

        $patterns = (array) config('companion.models.redact_patterns', []);
        foreach ($attributes as $key => $value) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $key)) {
                    $attributes[$key] = '********';
                    break;
                }
            }
        }

        return $attributes;
    }

    private function safeCount(Model $instance): int
    {
        try {
            return $instance->newQuery()->count();
        } catch (\Throwable) {
            return -1;
        }
    }

    private function usesSoftDeletes(string $class): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($class), true);
    }

    /**
     * Discover relationships with type/key detail via reflection.
     * Introspect marks properties as relation=true but doesn't provide
     * the relationship type, related model, or foreign keys.
     *
     * @return list<array<string, mixed>>
     */
    private function discoverRelationships(string $class, Model $instance): array
    {
        $reflection = new ReflectionClass($class);
        $relationships = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $class || $method->getNumberOfParameters() > 0) {
                continue;
            }

            $name = $method->getName();
            if (str_starts_with($name, 'get') || str_starts_with($name, 'set') || str_starts_with($name, 'scope')) {
                continue;
            }

            $returnType = $method->getReturnType();
            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            $typeName = $returnType->getName();
            if (is_subclass_of($typeName, Relation::class) || $typeName === Relation::class) {
                try {
                    /** @var Relation<Model, Model, mixed> $relation */
                    $relation = $instance->{$name}();

                    $relationships[] = [
                        'name' => $name,
                        'type' => class_basename($typeName),
                        'related' => get_class($relation->getRelated()),
                        'foreign_key' => method_exists($relation, 'getForeignKeyName')
                            ? $relation->getForeignKeyName()
                            : null,
                        'local_key' => method_exists($relation, 'getLocalKeyName')
                            ? $relation->getLocalKeyName()
                            : null,
                    ];
                } catch (\Throwable) {
                    // Skip relationships that can't be instantiated
                }
            }
        }

        return $relationships;
    }

    /**
     * @param  ReflectionClass<Model>  $reflection
     * @return list<string>
     */
    private function discoverScopes(ReflectionClass $reflection): array
    {
        return collect($reflection->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $m) => str_starts_with($m->getName(), 'scope') && $m->getName() !== 'scopeQuery')
            ->map(fn (ReflectionMethod $m) => lcfirst(substr($m->getName(), 5)))
            ->values()
            ->all();
    }
}
