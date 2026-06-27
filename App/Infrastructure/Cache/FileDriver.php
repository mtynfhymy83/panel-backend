<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

class FileDriver implements CacheInterface
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $base = dirname(__DIR__, 3);
        $this->directory = $directory ?? $base . '/storage/cache';
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return $default;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return $default;
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !isset($payload['expires_at'])) {
            return $default;
        }
        if ($payload['expires_at'] !== 0 && $payload['expires_at'] < time()) {
            @unlink($path);
            return $default;
        }
        return $payload['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        $payload = [
            'expires_at' => $ttlSeconds > 0 ? time() + $ttlSeconds : 0,
            'value'      => $value,
        ];
        return file_put_contents($this->path($key), json_encode($payload, JSON_THROW_ON_ERROR)) !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return true;
        }
        return @unlink($path);
    }

    public function increment(string $key, int $ttlSeconds = 0): int
    {
        $current = (int) $this->get($key, 0);
        $next = $current + 1;
        $this->set($key, $next, $ttlSeconds);
        return $next;
    }

    private function path(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.cache';
    }
}
