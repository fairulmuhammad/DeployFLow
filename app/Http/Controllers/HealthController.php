<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Health\HealthChecker;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    public function __invoke(HealthChecker $checker): JsonResponse
    {
        $report = $checker->run();

        return response()
            ->json($report, $report['status'] === 'ok' ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
