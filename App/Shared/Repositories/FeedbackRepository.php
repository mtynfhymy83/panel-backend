<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class FeedbackRepository
{
    public function findById(int $id): ?array
    {
        $row = DB::fetch('SELECT * FROM teacher_feedbacks WHERE id = :id', [':id' => $id]);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $id = DB::execute(
            'INSERT INTO teacher_feedbacks (teacher_id, course_class_id, admin_id, strengths, improvements, gems, created_at, updated_at)
             VALUES (:teacher_id, :class_id, :admin_id, :strengths, :improvements, :gems, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                ':teacher_id'   => $data['teacher_id'],
                ':class_id'     => $data['course_class_id'],
                ':admin_id'     => $data['admin_id'],
                ':strengths'    => $data['strengths'] ?? null,
                ':improvements' => $data['improvements'] ?? null,
                ':gems'         => $data['gems'] ?? null,
            ],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    public function listForTeacher(int $teacherId, int $limit = 20, int $offset = 0): array
    {
        return DB::fetchAll(
            'SELECT * FROM teacher_feedbacks WHERE teacher_id = :id ORDER BY id DESC LIMIT :limit OFFSET :offset',
            [':id' => $teacherId, ':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countForTeacher(int $teacherId): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS c FROM teacher_feedbacks WHERE teacher_id = :id', [':id' => $teacherId]);
        return (int) ($row['c'] ?? 0);
    }

    public function listAdmin(?int $teacherId = null, ?int $classId = null, int $limit = 20, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [':limit' => $limit, ':offset' => $offset];
        if ($teacherId !== null) {
            $where[] = 'teacher_id = :teacher_id';
            $params[':teacher_id'] = $teacherId;
        }
        if ($classId !== null) {
            $where[] = 'course_class_id = :class_id';
            $params[':class_id'] = $classId;
        }
        return DB::fetchAll(
            'SELECT * FROM teacher_feedbacks WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT :limit OFFSET :offset',
            $params
        );
    }

    public function countAdmin(?int $teacherId = null, ?int $classId = null): int
    {
        $where = ['1=1'];
        $params = [];
        if ($teacherId !== null) {
            $where[] = 'teacher_id = :teacher_id';
            $params[':teacher_id'] = $teacherId;
        }
        if ($classId !== null) {
            $where[] = 'course_class_id = :class_id';
            $params[':class_id'] = $classId;
        }
        $row = DB::fetch('SELECT COUNT(*) AS c FROM teacher_feedbacks WHERE ' . implode(' AND ', $where), $params);
        return (int) ($row['c'] ?? 0);
    }

    public function markSeen(int $teacherId, array $ids = []): void
    {
        if ($ids === []) {
            DB::execute(
                'UPDATE teacher_feedbacks SET teacher_seen_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE teacher_id = :id AND teacher_seen_at IS NULL',
                [':id' => $teacherId]
            );
            return;
        }
        foreach ($ids as $id) {
            DB::execute(
                'UPDATE teacher_feedbacks SET teacher_seen_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :fid AND teacher_id = :teacher_id',
                [':fid' => (int) $id, ':teacher_id' => $teacherId]
            );
        }
    }

    public function unreadCountForTeacher(int $teacherId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS c FROM teacher_feedbacks WHERE teacher_id = :id AND teacher_seen_at IS NULL',
            [':id' => $teacherId]
        );
        return (int) ($row['c'] ?? 0);
    }
}
