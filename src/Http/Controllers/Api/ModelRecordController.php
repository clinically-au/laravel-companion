<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Contracts\CompanionSerializable;
use Clinically\Companion\Services\ModelBrowserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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

        $allowedColumns = $this->getAllowedColumns($instance);

        $this->applyFilters($query, $request, $allowedColumns);
        $this->applyScope($query, $request, $class);
        $this->applySearch($query, $request, $instance);
        $this->applySorting($query, $request, $allowedColumns);

        $perPage = max(1, min(
            (int) $request->query('per_page', (string) config('companion.models.default_per_page', 25)),
            (int) config('companion.models.max_per_page', 100),
        ));

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

        $depth = (int) config('companion.models.eager_load_depth', 1);
        if ($depth > 0) {
            $this->eagerLoadRelationships($record, $class);
        }

        $data = $this->modelBrowser->serialiseRecord($record);

        return $this->respond($data);
    }

    /**
     * Get columns that are safe to use in filters/sorting.
     * Excludes hidden and redacted columns.
     *
     * @return list<string>
     */
    private function getAllowedColumns(Model $instance): array
    {
        try {
            $allColumns = Schema::connection($instance->getConnectionName())
                ->getColumnListing($instance->getTable());
        } catch (\Throwable) {
            return $instance->getFillable();
        }

        $hidden = array_merge(
            $instance->getHidden(),
            (array) config('companion.models.hidden_columns', []),
        );

        $redactPatterns = (array) config('companion.models.redact_patterns', []);

        return array_values(array_filter($allColumns, function (string $col) use ($hidden, $redactPatterns) {
            if (in_array($col, $hidden, true)) {
                return false;
            }

            foreach ($redactPatterns as $pattern) {
                if (preg_match($pattern, $col)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $allowedColumns
     */
    private function applyFilters(Builder $query, Request $request, array $allowedColumns): void
    {
        /** @var array<string, string>|null $filters */
        $filters = $request->query('filter');

        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $key => $value) {
            if (str_contains($key, ':')) {
                [$column, $operator] = explode(':', $key, 2);
            } else {
                $column = $key;
                $operator = 'eq';
            }

            if (! in_array($column, $allowedColumns, true)) {
                continue;
            }

            $this->applyOperatorFilter($query, $column, $operator, $value);
        }
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyOperatorFilter(Builder $query, string $column, string $operator, string $value): void
    {
        match ($operator) {
            'eq' => $query->where($column, $value),
            'gt' => $query->where($column, '>', $value),
            'lt' => $query->where($column, '<', $value),
            'gte' => $query->where($column, '>=', $value),
            'lte' => $query->where($column, '<=', $value),
            'like' => $query->where($column, 'LIKE', '%'.self::escapeLike($value).'%'),
            'null' => $query->whereNull($column),
            'not_null' => $query->whereNotNull($column),
            default => null,
        };
    }

    /**
     * Only allow scopes explicitly declared via CompanionSerializable.
     * Non-CompanionSerializable models cannot have scopes applied.
     *
     * @param  Builder<Model>  $query
     * @param  class-string<Model>  $class
     */
    private function applyScope(Builder $query, Request $request, string $class): void
    {
        $scopeName = $request->query('scope');

        if (! is_string($scopeName) || $scopeName === '') {
            return;
        }

        $instance = new $class;

        if (! $instance instanceof CompanionSerializable) {
            return;
        }

        $allowed = $instance->companionScopes();

        if (! in_array($scopeName, $allowed, true)) {
            return;
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

        $escaped = self::escapeLike($search);

        $query->where(function (Builder $q) use ($fillable, $escaped) {
            foreach ($fillable as $column) {
                $q->orWhere($column, 'LIKE', "%{$escaped}%");
            }
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $allowedColumns
     */
    private function applySorting(Builder $query, Request $request, array $allowedColumns): void
    {
        $sort = $request->query('sort');

        if (! is_string($sort) || $sort === '') {
            return;
        }

        if (! in_array($sort, $allowedColumns, true)) {
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

    /**
     * Escape LIKE wildcard characters to prevent pattern injection.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
