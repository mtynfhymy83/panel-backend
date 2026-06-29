<?php

declare(strict_types=1);

namespace App\Shared\Services;

use App\Infrastructure\Database\DB;
use App\Shared\Exceptions\AuthenticationException;
use App\Shared\Repositories\RefreshTokenRepository;

class RefreshTokenService
{
    private int $ttlDays;

    public function __construct(private RefreshTokenRepository $tokens)
    {
        $this->ttlDays = max(1, (int) ($_ENV['REFRESH_TTL_DAYS'] ?? 30));
    }

    public function issue(int $userId, string $activeRole): string
    {
        $raw = bin2hex(random_bytes(32));
        $this->tokens->upsertForUser(
            $userId,
            $this->hash($raw),
            $activeRole,
            $this->expiresAt()
        );
        return $raw;
    }

    /**
     * @return array{refreshToken: string, userId: int, activeRole: string}
     */
    public function rotate(string $rawToken): array
    {
        $hash = $this->hash($rawToken);

        return DB::transaction(function () use ($hash, $rawToken) {
            $row = $this->tokens->findValidByHash($hash);
            if ($row === null) {
                throw new AuthenticationException('Invalid refresh token.');
            }

            $userId = (int) $row['user_id'];
            $activeRole = (string) $row['active_role'];

            $newRaw = bin2hex(random_bytes(32));
            $this->tokens->upsertForUser(
                $userId,
                $this->hash($newRaw),
                $activeRole,
                $this->expiresAt()
            );

            return [
                'refreshToken' => $newRaw,
                'userId'       => $userId,
                'activeRole'   => $activeRole,
            ];
        });
    }

    public function revoke(string $rawToken): void
    {
        $this->tokens->revokeByHash($this->hash($rawToken));
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->tokens->revokeAllForUser($userId);
    }

    private function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private function expiresAt(): string
    {
        return date('Y-m-d H:i:s', time() + ($this->ttlDays * 86400));
    }
}
