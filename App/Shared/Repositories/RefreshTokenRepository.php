<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class RefreshTokenRepository
{
    public function create(int $userId, string $tokenHash, string $activeRole, string $expiresAt): int
    {
        $id = DB::execute(
            'INSERT INTO refresh_tokens (user_id, token_hash, active_role, expires_at, created_at)
             VALUES (:user_id, :token_hash, :active_role, :expires_at, CURRENT_TIMESTAMP)',
            [
                ':user_id'      => $userId,
                ':token_hash'   => $tokenHash,
                ':active_role'  => $activeRole,
                ':expires_at'   => $expiresAt,
            ],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    public function findValidByHash(string $tokenHash): ?array
    {
        $row = DB::fetch(
            'SELECT * FROM refresh_tokens
             WHERE token_hash = :hash
               AND revoked_at IS NULL
               AND expires_at > CURRENT_TIMESTAMP
             LIMIT 1',
            [':hash' => $tokenHash]
        );
        return $row ?: null;
    }

    public function revokeByHash(string $tokenHash): void
    {
        DB::execute(
            'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP
             WHERE token_hash = :hash AND revoked_at IS NULL',
            [':hash' => $tokenHash]
        );
    }

    public function revokeAllForUser(int $userId): void
    {
        DB::execute(
            'UPDATE refresh_tokens SET revoked_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id AND revoked_at IS NULL',
            [':user_id' => $userId]
        );
    }
}
