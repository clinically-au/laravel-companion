<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\Services\ModelBrowserService;
use Illuminate\Http\JsonResponse;

final class ModelController extends Controller
{
    public function __construct(
        private readonly ModelBrowserService $modelBrowser,
    ) {}

    public function index(): JsonResponse
    {
        $models = $this->modelBrowser->discoverModels();

        return $this->respond($models->all());
    }

    public function show(string $model): JsonResponse
    {
        $class = $this->modelBrowser->resolveModelClass($model);

        if ($class === null) {
            // Check for ambiguous short names
            $models = $this->modelBrowser->discoverModels();
            $matches = $models->where('short_name', $model);

            if ($matches->count() > 1) {
                return $this->error(
                    'Ambiguous model name. Use a fully-qualified class name.',
                    'ambiguous_model',
                    409,
                );
            }

            return $this->error('Model not found.', 'model_not_found', 404);
        }

        $metadata = $this->modelBrowser->getModelMetadata($class);

        return $this->respond($metadata);
    }

    public function relationships(string $model): JsonResponse
    {
        $class = $this->modelBrowser->resolveModelClass($model);

        if ($class === null) {
            return $this->error('Model not found.', 'model_not_found', 404);
        }

        $relationships = $this->modelBrowser->getRelationships($class);

        return $this->respond($relationships);
    }
}
