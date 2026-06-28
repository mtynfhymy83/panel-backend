<?php

declare(strict_types=1);

/**
 * Migrate users table from username-based auth to phone-based auth.
 * Run once on existing databases:
 *
 *   php scripts/migrate_auth_phone.php
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

if (!in_array('username', $columnNames, true) && in_array('first_name', $columnNames, true)) {
    echo "Migration already applied.\n";
    exit(0);
}

echo "Applying phone-auth migration (driver: {$driver})...\n";

if ($driver === 'pgsql') {
    DB::execute('ALTER TABLE users ADD COLUMN IF NOT EXISTS first_name VARCHAR(100)');
    DB::execute('ALTER TABLE users ADD COLUMN IF NOT EXISTS last_name VARCHAR(100)');
    if (in_array('full_name', $columnNames, true)) {
        DB::execute('UPDATE users SET first_name = COALESCE(SPLIT_PART(full_name, \' \', 1), full_name), last_name = COALESCE(NULLIF(TRIM(SUBSTRING(full_name FROM POSITION(\' \' IN full_name))), \'\'), \'\') WHERE first_name IS NULL OR first_name = \'\'');
    }
    DB::execute('UPDATE users SET first_name = \'User\' WHERE first_name IS NULL OR first_name = \'\'');
    DB::execute('UPDATE users SET last_name = \'\' WHERE last_name IS NULL');
    DB::execute('UPDATE users SET phone = \'09000000000\' WHERE phone IS NULL OR phone = \'\'');
    DB::execute('ALTER TABLE users ALTER COLUMN first_name SET NOT NULL');
    DB::execute('ALTER TABLE users ALTER COLUMN last_name SET NOT NULL');
    DB::execute('ALTER TABLE users ALTER COLUMN phone SET NOT NULL');
    if (in_array('username', $columnNames, true)) {
        DB::execute('ALTER TABLE users DROP COLUMN username');
    }
    if (in_array('full_name', $columnNames, true)) {
        DB::execute('ALTER TABLE users DROP COLUMN full_name');
    }
} elseif ($driver === 'mysql') {
    if (!in_array('first_name', $columnNames, true)) {
        DB::execute('ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NULL AFTER id');
    }
    if (!in_array('last_name', $columnNames, true)) {
        DB::execute('ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name');
    }
    if (in_array('full_name', $columnNames, true)) {
        DB::execute('UPDATE users SET first_name = SUBSTRING_INDEX(full_name, \' \', 1), last_name = IF(LOCATE(\' \', full_name) > 0, SUBSTRING(full_name, LOCATE(\' \', full_name) + 1), \'\') WHERE first_name IS NULL OR first_name = \'\'');
    }
    DB::execute('UPDATE users SET first_name = \'User\' WHERE first_name IS NULL OR first_name = \'\'');
    DB::execute('UPDATE users SET last_name = \'\' WHERE last_name IS NULL');
    DB::execute('UPDATE users SET phone = \'09000000000\' WHERE phone IS NULL OR phone = \'\'');
    DB::execute('ALTER TABLE users MODIFY first_name VARCHAR(100) NOT NULL');
    DB::execute('ALTER TABLE users MODIFY last_name VARCHAR(100) NOT NULL');
    DB::execute('ALTER TABLE users MODIFY phone VARCHAR(20) NOT NULL');
    if (in_array('username', $columnNames, true)) {
        DB::execute('ALTER TABLE users DROP COLUMN username');
    }
    if (in_array('full_name', $columnNames, true)) {
        DB::execute('ALTER TABLE users DROP COLUMN full_name');
    }
} else {
  fwrite(STDERR, "For SQLite, recreate the database with: php scripts/migrate.php\n");
  exit(1);
}

echo "Phone-auth migration complete.\n";
