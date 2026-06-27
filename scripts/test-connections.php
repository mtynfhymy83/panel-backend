<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Framework\Bootstrap\EnvironmentManager;
use App\Infrastructure\Cache\CacheFactory;
use App\Infrastructure\Database\DB;

EnvironmentManager::initialize();

$pdo = new PDO(
    (string) EnvironmentManager::get('DB_DSN'),
    (string) EnvironmentManager::get('DB_USERNAME'),
    (string) EnvironmentManager::get('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
DB::initSingle($pdo);
$row = DB::fetch('SELECT COUNT(*) AS c FROM users');
echo 'PostgreSQL OK — users: ' . ($row['c'] ?? 0) . PHP_EOL;

$cache = CacheFactory::create();
$cache->set('pardis:connection-test', ['ok' => true], 30);
$payload = $cache->get('pardis:connection-test');
echo ($payload['ok'] ?? false) ? "Redis OK\n" : "Redis FAIL\n";
