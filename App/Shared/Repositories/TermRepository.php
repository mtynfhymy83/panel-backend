<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class TermRepository
{
    public function findById(int $id): ?array
    {
        $row = DB::fetch('SELECT * FROM terms WHERE id = :id', [':id' => $id]);
        return $row ?: null;
    }

    public function activeForClass(int $classId): ?array
    {
        $row = DB::fetch(
            'SELECT * FROM terms WHERE course_class_id = :id AND is_active = TRUE LIMIT 1',
            [':id' => $classId]
        );
        return $row ?: null;
    }

    public function lastEndedForClass(int $classId): ?array
    {
        $row = DB::fetch(
            'SELECT * FROM terms WHERE course_class_id = :id AND is_active = FALSE AND end_date IS NOT NULL
             ORDER BY end_date DESC LIMIT 1',
            [':id' => $classId]
        );
        return $row ?: null;
    }

    public function create(int $classId, string $name, string $startDate, int $teacherId): int
    {
        $id = DB::execute(
            'INSERT INTO terms (course_class_id, name, start_date, is_active, created_by_teacher_id, created_at, updated_at)
             VALUES (:class_id, :name, :start_date, TRUE, :teacher_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                ':class_id'   => $classId,
                ':name'       => $name,
                ':start_date' => $startDate,
                ':teacher_id' => $teacherId,
            ],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    public function endTerm(int $termId, string $endDate, int $teacherId, array $closedStudentIds): void
    {
        DB::execute(
            'UPDATE terms SET end_date = :end_date, is_active = FALSE, ended_by_teacher_id = :teacher_id,
             ended_at = CURRENT_TIMESTAMP, closed_student_ids = :closed_ids, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id',
            [
                ':end_date'    => $endDate,
                ':teacher_id'  => $teacherId,
                ':closed_ids'  => json_encode($closedStudentIds, JSON_THROW_ON_ERROR),
                ':id'          => $termId,
            ]
        );
    }

    public function list(?int $classId = null, int $limit = 20, int $offset = 0): array
    {
        $params = [':limit' => $limit, ':offset' => $offset];
        $where = '1=1';
        if ($classId !== null) {
            $where = 'course_class_id = :class_id';
            $params[':class_id'] = $classId;
        }
        return DB::fetchAll(
            "SELECT * FROM terms WHERE {$where} ORDER BY id DESC LIMIT :limit OFFSET :offset",
            $params
        );
    }

    public function count(?int $classId = null): int
    {
        $params = [];
        $where = '1=1';
        if ($classId !== null) {
            $where = 'course_class_id = :class_id';
            $params[':class_id'] = $classId;
        }
        $row = DB::fetch("SELECT COUNT(*) AS c FROM terms WHERE {$where}", $params);
        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array> */
    public function endedTermsForStudent(int $studentId): array
    {
        return DB::fetchAll(
            'SELECT t.* FROM terms t
             INNER JOIN class_memberships m ON m.course_class_id = t.course_class_id
             WHERE m.user_id = :student_id AND m.role = \'student\'
             AND t.is_active = FALSE AND t.end_date IS NOT NULL
             ORDER BY t.end_date DESC',
            [':student_id' => $studentId]
        );
    }
}
