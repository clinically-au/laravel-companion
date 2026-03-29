<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Contracts\CompanionSerializable;
use Clinically\Companion\Services\ModelBrowserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ModelRecordController extends Controller
{
    public function __construct(
        private readonly ModelBrowserService $modelBrowser,
    ) {}

    public function index(Request $request, string $model): JsonResponse
    {
        $class = $this->modelBrowser->resolveModelClass($model);

        if ($class === null) {
            return $this->error('Model not found.', 'model_not_found', 404);
        }

        /** @var Model $instance */
        $instance = new $class;
        $query = $instance->newQuery();

        $this->applyFilters($query, $request, $instance);
        $this->applyScope($query, $request, $class);
        $this->applySearch($query, $request, $instance);
        $this->applySorting($query, $request);

        $perPage = min(
            (int) $request->query('per_page', (string) config('companion.models.default_per_page', 25)),
            (int) config('companion.models.max_per_page', 100),
        );

        $paginator = $query->paginate($perPage);

        $records = collect($paginator->items())
            ->map(fn (Model $record) => $this->modelBrowser->serialiseRecord($record))
            ->all();

        return $this->paginated($records, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    public function show(string $model, string $id): JsonResponse
    {
        $class = $this->modelBrowser->resolveModelClass($model);

        if ($class === null) {
            return $this->error('Model not found.', 'model_not_found', 404);
        }

        /** @var Model $instance */
        $instance = new $class;
        $record = $instance->newQuery()->find($id);

        if ($record === null) {
            return $this->error('Record not found.', 'record_not_found', 404);
        }

        // Eager load relationships up to configured depth
        $depth = (int) config('companion.models.eager_load_depth', 1);
        if ($depth > 0) {
            $this->eagerLoadRelationships($record, $class);
        }

        $data = $this->modelBrowser->serialiseRecord($record);

        return $this->respond($data);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyFilters(Builder $query, Request $request, Model $instance): void
    {
        /** @var array<string, string>|null $filters */
        $filters = $request->query('filter');

        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $key => $value) {
            // Parse operator: filter[column:operator]=value
            if (str_contains($key, ':')) {
                [$column, $operator] = explode(':', $key, 2);
                $this->applyOperatorFilter($query, $column, $operator, $value);
            } else {
                $query->where($key, $value);
            }
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyOperatorFilter(Builder $query, string $column, string $operator, string $value): void
    {
        match ($operator) {
            'gt' => $query->where($column, '>', $value),
            'lt' => $query->where($column, '<', $value),
            'gte' => $query->where($column, '>=', $value),
            'lte' => $query->where($column, '<=', $value),
            'like' => $query->where($column, 'LIKE', "%{$value}%"),
            'null' => $query->whereNull($column),
            'not_null' => $query->whereNotNull($column),
            default => null,
        };
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<Model>  $class
     */
    private function applyScope(Builder $query, Request $request, string $class): void
    {
        $scopeName = $request->query('scope');

        if (! is_string($scopeName) || $scopeName === '') {
            return;
        }

        // Check allowed scopes
        $instance = new $class;
        if ($instance instanceof CompanionSerializable) {
            $allowed = $instance->companionScopes();
            if (! in_array($scopeName, $allowed, true)) {
                return;
            }
        }

        $scopeMethod = 'scope'.ucfirst($scopeName);
        if (method_exists($class, $scopeMethod)) {
            $query->{$scopeName}();
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySearch(Builder $query, Request $request, Model $instance): void
    {
        $search = $request->query('search');

        if (! is_string($search) || $search === '') {
            return;
        }

        $fillable = $instance->getFillable();

        if ($fillable === []) {
            return;
        }

        $query->where(function (Builder $q) use ($fillable, $search) {
            foreach ($fillable as $column) {
                $q->orWhere($column, 'LIKE', "%{$search}%");
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->query('sort');

        if (! is_string($sort) || $sort === '') {
            return;
        }

        $direction = $request->query('direction', 'asc');
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

        $query->orderBy($sort, $direction);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function eagerLoadRelationships(Model $record, string $class): void
    {
        if ($record instanceof CompanionSerializable) {
            $relationships = $record->companionRelationships();
        } else {
            $metadata = $this->modelBrowser->getModelMetadata($class);
            /** @var array<int, array{name: string}> $rels */
            $rels = $metadata['relationships'];
            $relationships = collect($rels)->pluck('name')->all();
        }

        if ($relationships !== []) {
            $record->load($relationships);
        }
    }
}
