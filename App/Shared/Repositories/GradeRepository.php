<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class GradeRepository
{
    public function findByTermAndStudent(int $termId, int $studentId): ?array
    {
        $row = DB::fetch(
            'SELECT * FROM teacher_grades WHERE term_id = :term_id AND student_id = :student_id',
            [':term_id' => $termId, ':student_id' => $studentId]
        );
        return $row ?: null;
    }

    /** @return list<array> */
    public function forTerm(int $termId): array
    {
        return DB::fetchAll('SELECT * FROM teacher_grades WHERE term_id = :term_id', [':term_id' => $termId]);
    }

    /** @return list<array> */
    public function forStudentEndedTerms(int $studentId): array
    {
        return DB::fetchAll(
            'SELECT g.* FROM teacher_grades g
             INNER JOIN terms t ON t.id = g.term_id
             WHERE g.student_id = :student_id AND t.is_active = FALSE AND t.end_date IS NOT NULL
             ORDER BY t.end_date DESC',
            [':student_id' => $studentId]
        );
    }

    public function upsert(array $data): int
    {
        $existing = $this->findByTermAndStudent((int) $data['term_id'], (int) $data['student_id']);
        $criteriaJson = json_encode($data['criteria_scores'] ?? [], JSON_THROW_ON_ERROR);
        $feedbackJson = json_encode($data['feedback'] ?? null, JSON_THROW_ON_ERROR);

        if ($existing) {
            DB::execute(
                'UPDATE teacher_grades SET criteria_scores = :criteria, score = :score, total_score = :total,
                 max_total_score = :max_total, feedback = :feedback, updated_by_teacher_id = :updated_by,
                 updated_at = CURRENT_TIMESTAMP WHERE id = :id',
                [
                    ':criteria'   => $criteriaJson,
                    ':score'      => $data['score'] ?? null,
                    ':total'      => $data['total_score'] ?? null,
                    ':max_total'  => $data['max_total_score'] ?? null,
                    ':feedback'   => $feedbackJson,
                    ':updated_by' => $data['updated_by_teacher_id'],
                    ':id'         => $existing['id'],
                ]
            );
            return (int) $existing['id'];
        }

        $id = DB::execute(
            'INSERT INTO teacher_grades (course_class_id, term_id, student_id, teacher_id, created_by_teacher_id,
             updated_by_teacher_id, criteria_scores, score, total_score, max_total_score, feedback, created_at, updated_at)
             VALUES (:class_id, :term_id, :student_id, :teacher_id, :created_by, :updated_by, :criteria, :score,
             :total, :max_total, :feedback, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                ':class_id'   => $data['course_class_id'],
                ':term_id'    => $data['term_id'],
                ':student_id' => $data['student_id'],
                ':teacher_id' => $data['teacher_id'],
                ':created_by' => $data['created_by_teacher_id'],
                ':updated_by' => $data['updated_by_teacher_id'],
                ':criteria'   => $criteriaJson,
                ':score'      => $data['score'] ?? null,
                ':total'      => $data['total_score'] ?? null,
                ':max_total'  => $data['max_total_score'] ?? null,
                ':feedback'   => $feedbackJson,
            ],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    public function countForTerm(int $termId): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS c FROM teacher_grades WHERE term_id = :id', [':id' => $termId]);
        return (int) ($row['c'] ?? 0);
    }
}
