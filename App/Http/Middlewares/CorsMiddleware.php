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
    /** @var list<string> */
    private array $defaultOrigins = [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:9501',
        'http://127.0.0.1',
        'http://127.0.0.1:3000',
        'https://panel.pardis-book.ir',
        'http://panel.pardis-book.ir',
    ];

    public function handle(Request $request, Response $response, callable $next)
    {
        $headers = $request->header ?? [];
        $origin = $headers['origin'] ?? $headers['Origin'] ?? null;

        if ($origin !== null && $origin !== '' && $this->isOriginAllowed($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Vary', 'Origin');
        } else {
            $response->header('Access-Control-Allow-Origin', '*');
        }

        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
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
        if ($this->isDevMode()) {
            return true;
        }

        foreach ($this->allowedOrigins() as $allowed) {
            if ($origin === $allowed) {
                return true;
            }
        }

        return false;
    }

    private function isDevMode(): bool
    {
        return ($_ENV['APP_ENV'] ?? '') === 'local'
            || ($_ENV['APP_DEBUG'] ?? '0') === '1';
    }

    /** @return list<string> */
    private function allowedOrigins(): array
    {
        $env = trim((string) ($_ENV['CORS_ALLOWED_ORIGINS'] ?? ''));
        if ($env === '') {
            return $this->defaultOrigins;
        }

        return array_values(array_filter(array_map('trim', explode(',', $env))));
    }
}
