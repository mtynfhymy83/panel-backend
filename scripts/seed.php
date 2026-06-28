<?php

declare(strict_types=1);

/**
 * Seed default admin user from environment.
 *
 *   php scripts/seed.php
 */

use App\Framework\Bootstrap\EnvironmentManager;
use App\Infrastructure\Database\DB;
use App\Shared\Enums\Role;
use App\Shared\Repositories\UserRepository;

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

$password = (string) EnvironmentManager::get('ADMIN_PASSWORD', '');
$firstName = (string) EnvironmentManager::get('ADMIN_FIRST_NAME', 'System');
$lastName = (string) EnvironmentManager::get('ADMIN_LAST_NAME', 'Admin');
$phone = (string) EnvironmentManager::get('ADMIN_PHONE', '');
$email = strtolower(trim((string) EnvironmentManager::get('ADMIN_EMAIL', '')));

if ($password === '') {
    fwrite(STDERR, "ADMIN_PASSWORD is required in .env\n");
    exit(1);
}
if ($phone === '') {
    fwrite(STDERR, "ADMIN_PHONE is required in .env\n");
    exit(1);
}

$users = new UserRepository();
$existing = $users->findByPhone($phone);
if ($existing !== null) {
    if ($email !== '' && empty($existing['email'])) {
        DB::execute(
            'UPDATE users SET email = :email, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':email' => $email, ':id' => (int) $existing['id']]
        );
        echo "Admin email updated: {$email}\n";
    } else {
        echo "Admin user already exists for phone: {$phone}\n";
    }
    exit(0);
}

$userId = $users->create(
    $firstName,
    $lastName,
    $phone,
    password_hash($password, PASSWORD_BCRYPT),
    $email !== '' ? $email : null
);
$users->addRole($userId, Role::Admin->value);

echo "Admin user created: {$phone}" . ($email !== '' ? " ({$email})" : '') . " (id={$userId})\n";
