<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

class RedisDriver implements CacheInterface
{
    private \Redis|\Predis\Client|null $client = null;

    public function __construct()
    {
        $host = (string) ($_ENV['REDIS_HOST'] ?? '127.0.0.1');
        $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);
        $username = (string) ($_ENV['REDIS_USERNAME'] ?? '');
        $password = (string) ($_ENV['REDIS_PASSWORD'] ?? '');
        $db = (int) ($_ENV['REDIS_DB'] ?? 0);

        if (extension_loaded('redis')) {
            $redis = new \Redis();
            $redis->connect($host, $port);
            if ($username !== '' && $password !== '') {
                $redis->auth([$username, $password]);
            } elseif ($password !== '') {
                $redis->auth($password);
            }
            if ($db > 0) {
                $redis->select($db);
            }
            $this->client = $redis;
            return;
        }

        if (class_exists(\Predis\Client::class)) {
            $params = ['host' => $host, 'port' => $port, 'database' => $db];
            if ($username !== '') {
                $params['username'] = $username;
            }
            if ($password !== '') {
                $params['password'] = $password;
            }
            $this->client = new \Predis\Client($params);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->client === null) {
            return $default;
        }
        $value = $this->client instanceof \Redis
            ? $this->client->get($key)
            : $this->client->get($key);
        if ($value === false || $value === null) {
            return $default;
        }
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): bool
    {
        if ($this->client === null) {
            return false;
        }
        $encoded = is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
        if ($this->client instanceof \Redis) {
            return $ttlSeconds > 0
                ? $this->client->setex($key, $ttlSeconds, $encoded)
                : (bool) $this->client->set($key, $encoded);
        }
        if ($ttlSeconds > 0) {
            $this->client->setex($key, $ttlSeconds, $encoded);
            return true;
        }
        $this->client->set($key, $encoded);
        return true;
    }

    public function delete(string $key): bool
    {
        if ($this->client === null) {
            return false;
        }
        if ($this->client instanceof \Redis) {
            return $this->client->del($key) > 0;
        }
        return (bool) $this->client->del([$key]);
    }

    public function increment(string $key, int $ttlSeconds = 0): int
    {
        if ($this->client === null) {
            return 0;
        }
        if ($this->client instanceof \Redis) {
            $value = (int) $this->client->incr($key);
            if ($ttlSeconds > 0) {
                $this->client->expire($key, $ttlSeconds);
            }
            return $value;
        }
        $value = (int) $this->client->incr($key);
        if ($ttlSeconds > 0) {
            $this->client->expire($key, $ttlSeconds);
        }
        return $value;
    }
}
