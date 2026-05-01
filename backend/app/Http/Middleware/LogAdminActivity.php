<?php

namespace App\Http\Middleware;

use App\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAdminActivity
{
    /** Solo registramos métodos que mutan estado para no inflar la tabla. */
    private const TRACKED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (in_array($request->method(), self::TRACKED_METHODS, true)) {
            AuditLogger::log('admin.request', [
                'status_code' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
