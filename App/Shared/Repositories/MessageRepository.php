<?php

declare(strict_types=1);

namespace App\Shared\Repositories;

use App\Infrastructure\Database\DB;

class MessageRepository
{
    public function findById(int $id): ?array
    {
        $row = DB::fetch('SELECT * FROM student_messages WHERE id = :id', [':id' => $id]);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $id = DB::execute(
            'INSERT INTO student_messages (student_id, course_class_id, type, title, body, status, created_at, updated_at)
             VALUES (:student_id, :class_id, :type, :title, :body, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
            [
                ':student_id' => $data['student_id'],
                ':class_id'   => $data['course_class_id'] ?? null,
                ':type'       => $data['type'],
                ':title'      => $data['title'],
                ':body'       => $data['body'],
                ':status'     => $data['status'] ?? 'pending',
            ],
            returnLastInsertId: true
        );
        return (int) $id;
    }

    public function attachTeachers(int $messageId, array $teacherIds): void
    {
        foreach ($teacherIds as $teacherId) {
            $exists = DB::fetch(
                'SELECT 1 FROM student_message_teachers WHERE student_message_id = :msg_id AND teacher_id = :teacher_id LIMIT 1',
                [':msg_id' => $messageId, ':teacher_id' => (int) $teacherId]
            );
            if ($exists) {
                continue;
            }
            DB::execute(
                'INSERT INTO student_message_teachers (student_message_id, teacher_id) VALUES (:msg_id, :teacher_id)',
                [':msg_id' => $messageId, ':teacher_id' => (int) $teacherId]
            );
        }
    }

    public function listForStudent(int $studentId, int $limit = 20, int $offset = 0): array
    {
        return DB::fetchAll(
            'SELECT * FROM student_messages WHERE student_id = :id ORDER BY id DESC LIMIT :limit OFFSET :offset',
            [':id' => $studentId, ':limit' => $limit, ':offset' => $offset]
        );
    }

    public function countForStudent(int $studentId): int
    {
        $row = DB::fetch('SELECT COUNT(*) AS c FROM student_messages WHERE student_id = :id', [':id' => $studentId]);
        return (int) ($row['c'] ?? 0);
    }

    public function listAdmin(?string $status = null, ?string $type = null, ?string $search = null, int $limit = 20, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [':limit' => $limit, ':offset' => $offset];
        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($type !== null && $type !== '') {
            $where[] = 'type = :type';
            $params[':type'] = $type;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(title LIKE :search OR body LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        return DB::fetchAll(
            'SELECT * FROM student_messages WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT :limit OFFSET :offset',
            $params
        );
    }

    public function countAdmin(?string $status = null, ?string $type = null, ?string $search = null): int
    {
        $where = ['1=1'];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'status = :status';
            $params[':status'] = $status;
        }
        if ($type !== null && $type !== '') {
            $where[] = 'type = :type';
            $params[':type'] = $type;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(title LIKE :search OR body LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }
        $row = DB::fetch('SELECT COUNT(*) AS c FROM student_messages WHERE ' . implode(' AND ', $where), $params);
        return (int) ($row['c'] ?? 0);
    }

    public function markReviewed(int $id): void
    {
        DB::execute(
            'UPDATE student_messages SET status = \'reviewed\', reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = \'pending\'',
            [':id' => $id]
        );
    }

    public function reply(int $id, string $reply, int $adminId): void
    {
        DB::execute(
            'UPDATE student_messages SET admin_reply = :reply, admin_reply_by = :admin_id, status = \'replied\',
             replied_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id',
            [':reply' => $reply, ':admin_id' => $adminId, ':id' => $id]
        );
    }

    public function markSeenByStudent(int $studentId, array $ids = []): void
    {
        if ($ids === []) {
            DB::execute(
                'UPDATE student_messages SET student_seen_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE student_id = :id AND student_seen_at IS NULL',
                [':id' => $studentId]
            );
            return;
        }
        foreach ($ids as $id) {
            DB::execute(
                'UPDATE student_messages SET student_seen_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :msg_id AND student_id = :student_id',
                [':msg_id' => (int) $id, ':student_id' => $studentId]
            );
        }
    }

    public function unreadCountForStudent(int $studentId): int
    {
        $row = DB::fetch(
            'SELECT COUNT(*) AS c FROM student_messages WHERE student_id = :id AND student_seen_at IS NULL',
            [':id' => $studentId]
        );
        return (int) ($row['c'] ?? 0);
    }
}
