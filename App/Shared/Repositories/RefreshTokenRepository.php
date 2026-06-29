<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class RefreshTokenRepository
{
    public function upsertForUser(int $userId, string $tokenHash, string $activeRole, string $expiresAt): void
    {
        $existing = DB::fetch(
            'SELECT id FROM refresh_tokens WHERE user_id = :user_id ORDER BY id DESC LIMIT 1',
            [':user_id' => $userId]
        );

        if ($existing !== null && $existing !== false) {
            $id = (int) $existing['id'];
            DB::execute(
                'UPDATE refresh_tokens
                 SET token_hash = :token_hash, active_role = :active_role, expires_at = :expires_at, revoked_at = NULL
                 WHERE id = :id',
                [
                    ':id'           => $id,
                    ':token_hash'   => $tokenHash,
                    ':active_role'  => $activeRole,
                    ':expires_at'   => $expiresAt,
                ]
            );
            DB::execute(
                'DELETE FROM refresh_tokens WHERE user_id = :user_id AND id != :id',
                [':user_id' => $userId, ':id' => $id]
            );
            return;
        }

        DB::execute(
            'INSERT INTO refresh_tokens (user_id, token_hash, active_role, expires_at, created_at)
             VALUES (:user_id, :token_hash, :active_role, :expires_at, CURRENT_TIMESTAMP)',
            [
                ':user_id'      => $userId,
                ':token_hash'   => $tokenHash,
                ':active_role'  => $activeRole,
                ':expires_at'   => $expiresAt,
            ]
        );
    }

    public function countForUser(int $userId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS c FROM refresh_tokens WHERE user_id = :user_id',
            [':user_id' => $userId]
        );
        return (int) ($row['c'] ?? 0);
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
