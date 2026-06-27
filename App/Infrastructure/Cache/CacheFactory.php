<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

class CacheFactory
{
    public static function create(): CacheInterface
    {
        $driver = strtolower((string) ($_ENV['CACHE_DRIVER'] ?? 'file'));
        if ($driver === 'redis') {
            return new RedisDriver();
        }
        return new FileDriver();
    }
}
