<?php

declare(strict_types=1);

/**
 * One-time fix: re-hash plain-text passwords to bcrypt.
 * Run after manual DB inserts or old seeds that stored raw passwords.
 *
 *   php scripts/fix-plain-passwords.php
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

$rows = DB::fetchAll('SELECT id, phone, password FROM users');
$fixed = 0;

foreach ($rows as $row) {
    $stored = (string) $row['password'];
    if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$')) {
        continue;
    }

    $hash = password_hash($stored, PASSWORD_BCRYPT);
    DB::execute('UPDATE users SET password = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :id', [
        ':p'  => $hash,
        ':id' => (int) $row['id'],
    ]);
    echo "Re-hashed password for user id={$row['id']} phone={$row['phone']}\n";
    $fixed++;
}

echo $fixed > 0 ? "Done. Fixed {$fixed} user(s).\n" : "No plain-text passwords found.\n";
