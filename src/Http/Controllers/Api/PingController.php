<?php

declare(strict_types=1);

namespace Clinically\Companion\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

final class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return $this->respond(['status' => 'ok']);
    }
}
