<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Clinically\Companion\FeatureRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CapabilitiesController extends Controller
{
    public function __construct(
        private readonly FeatureRegistry $features,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $agent = $this->agent($request);

        return $this->respond([
            'features' => $this->features->capabilities($agent->scopes),
        ]);
    }
}
