<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Framework\Bootstrap\EnvironmentManager;

EnvironmentManager::initialize();

$phone = $argv[1] ?? '09100559253';
$testPassword = $argv[2] ?? '12345678';

$pdo = new PDO(
    (string) EnvironmentManager::get('DB_DSN'),
    (string) EnvironmentManager::get('DB_USERNAME'),
    (string) EnvironmentManager::get('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$cols = $pdo->query(
    "SELECT column_name FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position"
)->fetchAll(PDO::FETCH_COLUMN);
echo 'users columns: ' . implode(', ', $cols) . "\n";

$stmt = $pdo->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
$stmt->execute([$phone]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user === false) {
    echo "USER_NOT_FOUND: {$phone}\n";
    exit(1);
}

$roles = $pdo->prepare('SELECT role FROM user_roles WHERE user_id = ?');
$roles->execute([$user['id']]);
$roleList = $roles->fetchAll(PDO::FETCH_COLUMN);

$stored = (string) ($user['password'] ?? '');
$isBcrypt = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$');
$verifyOk = $isBcrypt && password_verify($testPassword, $stored);

echo 'USER id=' . $user['id'] . ' phone=' . $user['phone'] . ' username=' . ($user['username'] ?? '-') . "\n";
echo 'roles: ' . (empty($roleList) ? '(none)' : implode(', ', $roleList)) . "\n";
echo 'password_is_bcrypt: ' . ($isBcrypt ? 'yes' : 'no') . "\n";
echo 'password_verify(' . $testPassword . '): ' . ($verifyOk ? 'yes' : 'no') . "\n";
if (!$isBcrypt) {
    echo 'plain_text_match: ' . ($stored === $testPassword ? 'yes' : 'no') . "\n";
}
