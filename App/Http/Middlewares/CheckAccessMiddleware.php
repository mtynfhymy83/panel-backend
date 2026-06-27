<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Framework\Coroutine\Context;
use App\Http\Concerns\ResponseTrait;
use App\Infrastructure\Cache\CacheInterface;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Services\JwtService;

/**
 * Role-based access check, invoked by the Router when a route declares an
 * $access value. Decodes the Bearer JWT and verifies the `role` claim is in
 * the allowed set.
 *
 * This is intentionally minimal — extend the claim handling to match your
 * token shape (e.g. multiple roles, scopes, levels).
 */
class CheckAccessMiddleware
{
    use ResponseTrait;

    public function __construct(
        private ?JwtService $jwt = null,
        private ?CacheInterface $cache = null
    ) {
        $this->jwt ??= new JwtService();
    }

    /**
     * @param array<int,string> $allowedRoles
     */
    public function checkAccess(array $allowedRoles, $request = null): bool
    {
        $token = $this->extractToken($request);
        if (!$token) {
            throw new AccessDeniedException('Authentication token is missing.', 401);
        }

        if ($this->cache !== null && $this->cache->get('jwt_blacklist:' . hash('sha256', $token)) !== null) {
            throw new AccessDeniedException('Invalid or expired token.', 401);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Throwable) {
            throw new AccessDeniedException('Invalid or expired token.', 401);
        }

        $userId = (int) ($payload->user_id ?? $payload->sub ?? $payload->id ?? 0);
        $roles = (array) ($payload->roles ?? []);
        $activeRole = (string) ($payload->active_role ?? $payload->role ?? '');

        if ($userId <= 0) {
            throw new AccessDeniedException('Invalid or expired token.', 401);
        }

        Context::set('auth_user_id', $userId);
        Context::set('auth_roles', $roles);
        Context::set('auth_active_role', $activeRole);
        Context::set('auth_token', $token);

        if (!in_array($activeRole, $allowedRoles, true)) {
            throw new AccessDeniedException('You do not have permission for this action.', 403);
        }

        return true;
    }

    private function extractToken($request): ?string
    {
        if (!is_object($request)) {
            return null;
        }

        $headers = $request->header ?? [];
        $auth = $headers['authorization'] ?? $headers['Authorization'] ?? null;
        if ($auth && preg_match('/Bearer\s+(.*)$/i', (string) $auth, $m)) {
            return trim($m[1]);
        }
        if (isset($headers['token'])) {
            return trim((string) $headers['token']);
        }

        return isset($request->get['token']) ? trim((string) $request->get['token']) : null;
    }
}
