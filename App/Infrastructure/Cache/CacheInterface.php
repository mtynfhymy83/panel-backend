<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool;

    public function delete(string $key): bool;

    public function increment(string $key, int $ttlSeconds = 0): int;
}
