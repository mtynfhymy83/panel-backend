<?php

declare(strict_types=1);

namespace App\Framework\Coroutine;

/**
 * Request-scoped state. Backed by Swoole's per-coroutine context so values
 * never bleed across concurrent requests. Falls back to no-ops when Swoole
 * is not loaded (e.g. unit tests).
 */
final class Context
{
    public static function set(string $key, mixed $value): void
    {
        $ctx = self::ctx();
        if ($ctx !== null) {
            $ctx[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $ctx = self::ctx();
        return $ctx !== null ? ($ctx[$key] ?? $default) : $default;
    }

    public static function has(string $key): bool
    {
        $ctx = self::ctx();
        return $ctx !== null && isset($ctx[$key]);
    }

    public static function remove(string $key): void
    {
        $ctx = self::ctx();
        if ($ctx !== null && isset($ctx[$key])) {
            unset($ctx[$key]);
        }
    }

    public static function clear(): void
    {
        $ctx = self::ctx();
        if ($ctx === null) {
            return;
        }
        foreach ($ctx as $key => $_) {
            unset($ctx[$key]);
        }
    }

    private static function ctx(): ?\ArrayAccess
    {
        if (!extension_loaded('swoole') || !class_exists(\Swoole\Coroutine::class)) {
            return null;
        }
        return \Swoole\Coroutine::getContext();
    }
}
