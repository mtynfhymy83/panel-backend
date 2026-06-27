<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Framework\Core\MiddlewareInterface;
use App\Framework\Coroutine\Context;
use App\Infrastructure\Cache\CacheInterface;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Services\JwtService;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Pipeline middleware: decode JWT and inject auth context for downstream handlers.
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private JwtService $jwt,
        private CacheInterface $cache
    ) {
    }

    public function handle(Request $request, Response $response, callable $next)
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            throw new AccessDeniedException('Authentication token is missing.', 401);
        }

        if ($this->cache->get('jwt_blacklist:' . hash('sha256', $token)) !== null) {
            throw new AccessDeniedException('Invalid or expired token.', 401);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Throwable) {
            throw new AccessDeniedException('Invalid or expired token.', 401);
        }

        $userId = (int) ($payload->user_id ?? $payload->sub ?? $payload->id ?? 0);
        if ($userId <= 0) {
            throw new AccessDeniedException('Invalid or expired token.', 401);
        }

        Context::set('auth_user_id', $userId);
        Context::set('auth_roles', (array) ($payload->roles ?? []));
        Context::set('auth_active_role', (string) ($payload->active_role ?? $payload->role ?? ''));
        Context::set('auth_token', $token);

        return $next($request, $response);
    }

    private function extractToken(Request $request): ?string
    {
        $headers = $request->header ?? [];
        $auth = $headers['authorization'] ?? $headers['Authorization'] ?? null;
        if ($auth && preg_match('/Bearer\s+(.*)$/i', (string) $auth, $m)) {
            return trim($m[1]);
        }
        return isset($headers['token']) ? trim((string) $headers['token']) : null;
    }
}
