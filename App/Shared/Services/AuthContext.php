<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Framework\Coroutine\Context;

final class AuthContext
{
    public static function userId(): ?int
    {
        $id = Context::get('auth_user_id');
        return $id !== null ? (int) $id : null;
    }

    public static function requireUserId(): int
    {
        $id = self::userId();
        if ($id === null || $id <= 0) {
            throw new \App\Shared\Exceptions\AuthenticationException();
        }
        return $id;
    }

    /** @return list<string> */
    public static function roles(): array
    {
        return (array) Context::get('auth_roles', []);
    }

    public static function activeRole(): ?string
    {
        $role = Context::get('auth_active_role');
        return $role !== null ? (string) $role : null;
    }

    public static function token(): ?string
    {
        $token = Context::get('auth_token');
        return $token !== null ? (string) $token : null;
    }
}
