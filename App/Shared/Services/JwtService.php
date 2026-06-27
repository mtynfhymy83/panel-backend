<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private string $secret;
    private string $algo;
    private int $ttlMinutes;

    public function __construct()
    {
        $this->secret = (string) ($_ENV['JWT_SECRET'] ?? '');
        $this->algo = (string) ($_ENV['JWT_ALGO'] ?? 'HS256');
        $this->ttlMinutes = max(1, (int) ($_ENV['JWT_TTL'] ?? 60));
    }

    /** @param list<string> $roles */
    public function issue(int $userId, array $roles, string $activeRole): string
    {
        if ($this->secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }

        $now = time();
        $payload = [
            'iss'          => 'pardis-api',
            'iat'          => $now,
            'exp'          => $now + ($this->ttlMinutes * 60),
            'user_id'      => $userId,
            'roles'        => $roles,
            'active_role'  => $activeRole,
            'role'         => $activeRole,
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    public function decode(string $token): object
    {
        if ($this->secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }
        return JWT::decode($token, new Key($this->secret, $this->algo));
    }

    public function ttlSeconds(): int
    {
        return $this->ttlMinutes * 60;
    }
}
