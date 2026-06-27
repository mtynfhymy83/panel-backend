<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class UserRepository
{
    public function findById(int $id, bool $withDeleted = false): ?array
    {
        $sql = 'SELECT * FROM users WHERE id = :id';
        if (!$withDeleted) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $row = DB::fetch($sql, [':id' => $id]);
        return $row ?: null;
    }

    public function findByUsername(string $username): ?array
    {
        $row = DB::fetch('SELECT * FROM users WHERE username = :u LIMIT 1', [':u' => $username]);
        return $row ?: null;
    }

    public function findByPhone(string $phone): ?array
    {
        $row = DB::fetch('SELECT * FROM users WHERE phone = :p LIMIT 1', [':p' => $phone]);
        return $row ?: null;
    }

    public function findActiveByUsername(string $username): ?array
    {
        $row = DB::fetch(
            'SELECT * FROM users WHERE username = :u AND deleted_at IS NULL LIMIT 1',
            [':u' => $username]
        );
        return $row ?: null;
    }

    public function findActiveByPhone(string $phone): ?array
    {
        $row = DB::fetch(
            'SELECT * FROM users WHERE phone = :p AND deleted_at IS NULL LIMIT 1',
            [':p' => $phone]
        );
        return $row ?: null;
    }

    public function usernameExists(string $username): bool
    {
        $row = DB::fetch('SELECT id FROM users WHERE username = :u LIMIT 1', [':u' => $username]);
        return $row !== false && $row !== null;
    }

    public function phoneExists(string $phone): bool
    {
        $row = DB::fetch('SELECT id FROM users WHERE phone = :p LIMIT 1', [':p' => $phone]);
        return $row !== false && $row !== null;
    }

    /** @return list<string> */
    public function getRoles(int $userId): array
    {
        $rows = DB::fetchAll('SELECT role FROM user_roles WHERE user_id = :id', [':id' => $userId]);
        return array_map(static fn (array $r) => (string) $r['role'], $rows);
    }

    public function create(string $fullName, string $username, ?string $phone, string $passwordHash): int
    {
        $id = DB::execute(
            'INSERT INTO users (full_name, username, phone, password, created_at, updated_at)
             VALUES (:full_name, :username, :phone, :password, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                ':full_name' => $fullName,
                ':username'  => $username,
                ':phone'     => $phone,
                ':password'  => $passwordHash,
            ],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    public function addRole(int $userId, string $role): void
    {
        if ($this->hasRole($userId, $role)) {
            return;
        }
        DB::execute(
            'INSERT INTO user_roles (user_id, role) VALUES (:user_id, :role)',
            [':user_id' => $userId, ':role' => $role]
        );
    }

    public function syncRoles(int $userId, array $roles): void
    {
        DB::execute('DELETE FROM user_roles WHERE user_id = :id', [':id' => $userId]);
        foreach ($roles as $role) {
            $this->addRole($userId, (string) $role);
        }
    }

    public function updateProfile(int $userId, array $data): void
    {
        $fields = [];
        $params = [':id' => $userId];
        foreach (['full_name', 'username'] as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "{$col} = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }
        if ($fields === []) {
            return;
        }
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        DB::execute('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id', $params);
    }

    public function updatePhone(int $userId, string $phone): void
    {
        DB::execute(
            'UPDATE users SET phone = :phone, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':phone' => $phone, ':id' => $userId]
        );
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        DB::execute(
            'UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':password' => $passwordHash, ':id' => $userId]
        );
    }

    public function softDelete(int $userId): void
    {
        DB::execute(
            'UPDATE users SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':id' => $userId]
        );
    }

    public function list(?string $role = null, ?string $search = null, int $limit = 20, int $offset = 0): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($role !== null && $role !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role = :role)';
            $params[':role'] = $role;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(u.full_name LIKE :search OR u.username LIKE :search OR u.phone LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $sql = 'SELECT u.* FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id DESC LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        return DB::fetchAll($sql, $params);
    }

    public function count(?string $role = null, ?string $search = null): int
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if ($role !== null && $role !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = u.id AND ur.role = :role)';
            $params[':role'] = $role;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(u.full_name LIKE :search OR u.username LIKE :search OR u.phone LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $row = DB::fetch('SELECT COUNT(*) AS c FROM users u WHERE ' . implode(' AND ', $where), $params);
        return (int) ($row['c'] ?? 0);
    }

    public function hasRole(int $userId, string $role): bool
    {
        $row = DB::fetch(
            'SELECT 1 FROM user_roles WHERE user_id = :id AND role = :role LIMIT 1',
            [':id' => $userId, ':role' => $role]
        );
        return $row !== false && $row !== null;
    }
}
