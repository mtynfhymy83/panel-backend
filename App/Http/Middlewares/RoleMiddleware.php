<?php

declare(strict_types=1);

namespace App\Http\Middlewares;

use App\Framework\Core\MiddlewareInterface;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Services\AuthContext;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Pipeline middleware: enforce active role against an allowed role list.
 */
class RoleMiddleware implements MiddlewareInterface
{
    /** @param list<string> $allowedRoles */
    public function __construct(private array $allowedRoles)
    {
    }

    public function handle(Request $request, Response $response, callable $next)
    {
        $activeRole = AuthContext::activeRole();
        if ($activeRole === null || !in_array($activeRole, $this->allowedRoles, true)) {
            throw new AccessDeniedException('You do not have permission for this action.', 403);
        }
        return $next($request, $response);
    }
}
