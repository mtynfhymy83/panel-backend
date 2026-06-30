<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class ExamRepository
{
    public function findByTermClassExaminer(int $termId, int $classId, int $examinerId): ?array
    {
        $row = DB::fetch(
            'SELECT * FROM exams WHERE term_id = :term_id AND course_class_id = :class_id AND examiner_id = :examiner_id',
            [':term_id' => $termId, ':class_id' => $classId, ':examiner_id' => $examinerId]
        );
        return $row ?: null;
    }

    public function findForClassActiveTerm(int $classId, int $examinerId): ?array
    {
        $row = DB::fetch(
            'SELECT e.* FROM exams e
             INNER JOIN terms t ON t.id = e.term_id
             WHERE e.course_class_id = :class_id AND e.examiner_id = :examiner_id AND t.is_active = TRUE
             LIMIT 1',
            [':class_id' => $classId, ':examiner_id' => $examinerId]
        );
        return $row ?: null;
    }

    /** @return list<array> */
    public function listForExaminer(int $examinerId): array
    {
        return DB::fetchAll(
            'SELECT e.* FROM exams e
             INNER JOIN class_memberships m ON m.course_class_id = e.course_class_id
             WHERE e.examiner_id = :examiner_id AND m.user_id = :examiner_id AND m.role = :role
             ORDER BY e.id DESC',
            [':examiner_id' => $examinerId, ':role' => 'examiner']
        );
    }

    public function create(array $data): int
    {
        $id = DB::execute(
            'INSERT INTO exams (course_class_id, term_id, examiner_id, exam_date, student_scores,
             class_strengths, class_improvements, class_suggestions, created_at, updated_at)
             VALUES (:class_id, :term_id, :examiner_id, :exam_date, :scores, :strengths, :improvements,
             :suggestions, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                ':class_id'      => $data['course_class_id'],
                ':term_id'       => $data['term_id'],
                ':examiner_id'   => $data['examiner_id'],
                ':exam_date'     => $data['exam_date'],
                ':scores'        => json_encode($data['student_scores'] ?? [], JSON_THROW_ON_ERROR),
                ':strengths'     => $data['class_strengths'] ?? null,
                ':improvements'  => $data['class_improvements'] ?? null,
                ':suggestions'   => $data['class_suggestions'] ?? null,
            ],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    /** @return list<array> */
    public function listForClass(int $classId, ?int $termId = null): array
    {
        $where = ['e.course_class_id = :class_id'];
        $params = [':class_id' => $classId];
        if ($termId !== null) {
            $where[] = 'e.term_id = :term_id';
            $params[':term_id'] = $termId;
        }

        return DB::fetchAll(
            'SELECT e.* FROM exams e WHERE ' . implode(' AND ', $where) . ' ORDER BY e.id DESC',
            $params
        );
    }

    public function list(?int $classId = null, ?string $level = null, int $limit = 20, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [':limit' => $limit, ':offset' => $offset];
        if ($classId !== null) {
            $where[] = 'e.course_class_id = :class_id';
            $params[':class_id'] = $classId;
        }
        if ($level !== null && $level !== '') {
            $where[] = 'c.level = :level';
            $params[':level'] = $level;
        }
        $sql = 'SELECT e.*, c.level, c.name AS class_name FROM exams e
                INNER JOIN course_classes c ON c.id = e.course_class_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY e.id DESC LIMIT :limit OFFSET :offset';
        return DB::fetchAll($sql, $params);
    }

    public function count(?int $classId = null, ?string $level = null): int
    {
        $where = ['1=1'];
        $params = [];
        if ($classId !== null) {
            $where[] = 'e.course_class_id = :class_id';
            $params[':class_id'] = $classId;
        }
        if ($level !== null && $level !== '') {
            $where[] = 'c.level = :level';
            $params[':level'] = $level;
        }
        $row = DB::fetch(
            'SELECT COUNT(*) AS c FROM exams e INNER JOIN course_classes c ON c.id = e.course_class_id WHERE '
            . implode(' AND ', $where),
            $params
        );
        return (int) ($row['c'] ?? 0);
    }
}
