<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class ClassRepository
{
    public function findById(int $id): ?array
    {
        $row = DB::fetch('SELECT * FROM course_classes WHERE id = :id', [':id' => $id]);
        return $row ?: null;
    }

    public function create(string $name, string $level): int
    {
        $id = DB::execute(
            'INSERT INTO course_classes (name, level, created_at, updated_at) VALUES (:name, :level, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [':name' => $name, ':level' => $level],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    public function update(int $id, string $name, string $level): void
    {
        DB::execute(
            'UPDATE course_classes SET name = :name, level = :level, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':name' => $name, ':level' => $level, ':id' => $id]
        );
    }

    public function delete(int $id): void
    {
        DB::execute('DELETE FROM course_classes WHERE id = :id', [':id' => $id]);
    }

    public function list(?string $search = null, int $limit = 20, int $offset = 0): array
    {
        $params = [':limit' => $limit, ':offset' => $offset];
        $where = '1=1';
        if ($search !== null && $search !== '') {
            $where = 'name LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }
        return DB::fetchAll(
            "SELECT * FROM course_classes WHERE {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset",
            $params
        );
    }

    public function count(?string $search = null): int
    {
        $params = [];
        $where = '1=1';
        if ($search !== null && $search !== '') {
            $where = 'name LIKE :search';
            $params[':search'] = '%' . $search . '%';
        }
        $row = DB::fetch("SELECT COUNT(*) AS c FROM course_classes WHERE {$where}", $params);
        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array> */
    public function memberships(int $classId): array
    {
        return DB::fetchAll(
            'SELECT * FROM class_memberships WHERE course_class_id = :id',
            [':id' => $classId]
        );
    }

    public function addMember(int $classId, int $userId, string $role): void
    {
        DB::execute(
            'INSERT INTO class_memberships (course_class_id, user_id, role, created_at, updated_at)
             VALUES (:class_id, :user_id, :role, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [':class_id' => $classId, ':user_id' => $userId, ':role' => $role]
        );
    }

    public function removeMember(int $classId, int $userId, string $role): bool
    {
        return DB::execute(
            'DELETE FROM class_memberships WHERE course_class_id = :class_id AND user_id = :user_id AND role = :role',
            [':class_id' => $classId, ':user_id' => $userId, ':role' => $role]
        ) > 0;
    }

    public function removeAllMemberships(int $userId): void
    {
        DB::execute('DELETE FROM class_memberships WHERE user_id = :user_id', [':user_id' => $userId]);
    }

    public function hasMembership(int $classId, int $userId, string $role): bool
    {
        $row = DB::fetch(
            'SELECT 1 FROM class_memberships WHERE course_class_id = :class_id AND user_id = :user_id AND role = :role LIMIT 1',
            [':class_id' => $classId, ':user_id' => $userId, ':role' => $role]
        );
        return $row !== false && $row !== null;
    }

    /** @return list<array> */
    public function classesForUser(int $userId, ?string $role = null): array
    {
        $params = [':user_id' => $userId];
        $sql = 'SELECT c.* FROM course_classes c
                INNER JOIN class_memberships m ON m.course_class_id = c.id
                WHERE m.user_id = :user_id';
        if ($role !== null) {
            $sql .= ' AND m.role = :role';
            $params[':role'] = $role;
        }
        $sql .= ' ORDER BY c.id DESC';
        return DB::fetchAll($sql, $params);
    }

    /** @return list<int> */
    public function memberUserIds(int $classId, string $role): array
    {
        $rows = DB::fetchAll(
            'SELECT user_id FROM class_memberships WHERE course_class_id = :id AND role = :role',
            [':id' => $classId, ':role' => $role]
        );
        return array_map(static fn (array $r) => (int) $r['user_id'], $rows);
    }

    /** @return list<array> */
    public function membersByRole(int $classId, string $role): array
    {
        return DB::fetchAll(
            'SELECT u.* FROM users u
             INNER JOIN class_memberships m ON m.user_id = u.id
             WHERE m.course_class_id = :class_id AND m.role = :role AND u.deleted_at IS NULL
             ORDER BY u.id',
            [':class_id' => $classId, ':role' => $role]
        );
    }
}
