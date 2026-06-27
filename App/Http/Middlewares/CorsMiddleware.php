<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Framework\Core\MiddlewareInterface;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * CORS headers + preflight (OPTIONS) handling.
 * Lock down isOriginAllowed() for production.
 */
class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins = [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:9501',
        'http://127.0.0.1',
        'http://127.0.0.1:3000',
    ];

    public function handle(Request $request, Response $response, callable $next)
    {
        $headers = $request->header ?? [];
        $origin = $headers['origin'] ?? $headers['Origin'] ?? null;

        $allowedOrigin = ($origin && $this->isOriginAllowed($origin)) ? $origin : '*';

        $response->header('Access-Control-Allow-Origin', $allowedOrigin);
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', '86400');

        if (($request->server['request_method'] ?? 'GET') === 'OPTIONS') {
            $response->status(204);
            $response->end('');
            return;
        }

        return $next($request, $response);
    }

    private function isOriginAllowed(string $origin): bool
    {
        // Development: allow everything. For production, switch to the allowlist:
        // foreach ($this->allowedOrigins as $a) { if (str_starts_with($origin, $a)) return true; }
        // return false;
        return true;
    }
}
