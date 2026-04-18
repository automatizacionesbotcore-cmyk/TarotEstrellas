<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $database = 'ok';

        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            $database = 'error';
        }

        $status = $database === 'ok' ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => [
                'database' => $database,
            ],
            'timestamp' => now()->toIso8601String(),
        ], $status === 'ok' ? 200 : 503);
    }
}
