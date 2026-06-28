<?php

declare(strict_types=1);

/**
 * Add email column to users (for OTP delivery).
 *
 *   php scripts/migrate_add_email.php
 */

use App\Framework\Bootstrap\EnvironmentManager;
use App\Infrastructure\Database\DB;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

EnvironmentManager::initialize();

$dsn = (string) EnvironmentManager::get('DB_DSN', '');
if ($dsn === '') {
    fwrite(STDERR, "DB_DSN is not set.\n");
    exit(1);
}

$pdo = new PDO(
    $dsn,
    (string) EnvironmentManager::get('DB_USERNAME', ''),
    (string) EnvironmentManager::get('DB_PASSWORD', ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
DB::initSingle($pdo);

$driver = strtok($dsn, ':') ?: 'sqlite';

$columns = DB::fetchAll(match ($driver) {
    'pgsql'  => "SELECT column_name FROM information_schema.columns WHERE table_name = 'users'",
    'mysql'  => 'SHOW COLUMNS FROM users',
    default  => "PRAGMA table_info(users)",
});

$columnNames = match ($driver) {
    'pgsql'  => array_map(static fn (array $r) => (string) $r['column_name'], $columns),
    'mysql'  => array_map(static fn (array $r) => (string) $r['Field'], $columns),
    default  => array_map(static fn (array $r) => (string) $r['name'], $columns),
};

if (in_array('email', $columnNames, true)) {
    echo "Column users.email already exists.\n";
    exit(0);
}

echo "Adding users.email (driver: {$driver})...\n";

match ($driver) {
    'pgsql' => DB::execute('ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL UNIQUE'),
    'mysql' => DB::execute('ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL UNIQUE AFTER phone'),
    default => DB::execute('ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL UNIQUE'),
};

echo "Done.\n";
