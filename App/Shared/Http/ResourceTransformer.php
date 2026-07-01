<?php

declare(strict_types=1);

namespace App\Shared\Http;

class ResourceTransformer
{
    public static function user(array $row, ?string $activeRole = null, ?array $roles = null): array
    {
        $firstName = (string) ($row['first_name'] ?? '');
        $lastName = (string) ($row['last_name'] ?? '');

        return [
            'id'        => (int) $row['id'],
            'firstName' => $firstName,
            'lastName'  => $lastName,
            'fullName'  => trim($firstName . ' ' . $lastName),
            'phone'     => (string) ($row['phone'] ?? ''),
            'email'     => isset($row['email']) && $row['email'] !== null && $row['email'] !== ''
                ? (string) $row['email']
                : null,
            'role'      => $activeRole ?? ($roles[0] ?? null),
            'roles'     => $roles ?? [],
            'createdAt' => self::iso($row['created_at'] ?? null),
            'deleted'   => !empty($row['deleted_at']),
        ];
    }

    public static function courseClass(array $row, array $memberships = []): array
    {
        $teacherIds = [];
        $studentIds = [];
        $examinerIds = [];
        foreach ($memberships as $m) {
            match ($m['role']) {
                'teacher'  => $teacherIds[] = (int) $m['user_id'],
                'student'  => $studentIds[] = (int) $m['user_id'],
                'examiner' => $examinerIds[] = (int) $m['user_id'],
                default    => null,
            };
        }

        return [
            'id'          => (int) $row['id'],
            'name'        => (string) ($row['name'] ?? ''),
            'level'       => (string) ($row['level'] ?? ''),
            'teacherIds'  => $teacherIds,
            'studentIds'  => $studentIds,
            'examinerIds' => $examinerIds,
            'createdAt'   => self::iso($row['created_at'] ?? null),
        ];
    }

    public static function userBrief(array $row): array
    {
        $firstName = (string) ($row['first_name'] ?? '');
        $lastName = (string) ($row['last_name'] ?? '');

        return [
            'id'       => (int) $row['id'],
            'fullName' => trim($firstName . ' ' . $lastName),
            'phone'    => (string) ($row['phone'] ?? ''),
        ];
    }

    public static function classBrief(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id'    => (int) $row['id'],
            'name'  => (string) ($row['name'] ?? ''),
            'level' => (string) ($row['level'] ?? ''),
        ];
    }

    public static function messageWithContext(array $row, ?array $student = null, ?array $class = null): array
    {
        $message = self::message($row);
        $message['student'] = $student !== null ? self::userBrief($student) : null;
        $message['class'] = self::classBrief($class);

        return $message;
    }

    public static function term(array $row): array
    {
        return [
            'id'                 => (int) $row['id'],
            'classId'            => (int) $row['course_class_id'],
            'name'               => (string) ($row['name'] ?? ''),
            'startDate'          => self::date($row['start_date'] ?? null),
            'endDate'            => self::date($row['end_date'] ?? null),
            'isActive'           => (bool) ($row['is_active'] ?? false),
            'createdByTeacherId' => isset($row['created_by_teacher_id']) ? (int) $row['created_by_teacher_id'] : null,
            'endedByTeacherId'   => isset($row['ended_by_teacher_id']) ? (int) $row['ended_by_teacher_id'] : null,
            'endedAt'            => self::iso($row['ended_at'] ?? null),
        ];
    }

    public static function grade(array $row): array
    {
        return [
            'id'                 => (int) $row['id'],
            'classId'            => (int) $row['course_class_id'],
            'termId'             => (int) $row['term_id'],
            'studentId'          => (int) $row['student_id'],
            'teacherId'          => (int) $row['teacher_id'],
            'criteriaScores'     => self::json($row['criteria_scores'] ?? null),
            'score'              => isset($row['score']) ? (float) $row['score'] : null,
            'totalScore'         => isset($row['total_score']) ? (float) $row['total_score'] : null,
            'maxTotalScore'      => isset($row['max_total_score']) ? (float) $row['max_total_score'] : null,
            'feedback'           => self::json($row['feedback'] ?? null),
            'createdAt'          => self::iso($row['created_at'] ?? null),
        ];
    }

    public static function exam(array $row): array
    {
        return [
            'id'                => (int) $row['id'],
            'classId'           => (int) $row['course_class_id'],
            'termId'            => (int) $row['term_id'],
            'examinerId'        => (int) $row['examiner_id'],
            'examDate'          => self::date($row['exam_date'] ?? null),
            'studentScores'     => self::json($row['student_scores'] ?? null),
            'classStrengths'    => $row['class_strengths'] ?? null,
            'classImprovements' => $row['class_improvements'] ?? null,
            'classSuggestions'  => $row['class_suggestions'] ?? null,
            'createdAt'         => self::iso($row['created_at'] ?? null),
        ];
    }

    public static function message(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'studentId'    => (int) $row['student_id'],
            'classId'      => isset($row['course_class_id']) ? (int) $row['course_class_id'] : null,
            'type'         => (string) ($row['type'] ?? ''),
            'title'        => (string) ($row['title'] ?? ''),
            'body'         => (string) ($row['body'] ?? ''),
            'status'       => (string) ($row['status'] ?? ''),
            'adminReply'   => $row['admin_reply'] ?? null,
            'adminReplyBy' => isset($row['admin_reply_by']) ? (int) $row['admin_reply_by'] : null,
            'reviewedAt'   => self::iso($row['reviewed_at'] ?? null),
            'repliedAt'    => self::iso($row['replied_at'] ?? null),
            'studentSeenAt'=> self::iso($row['student_seen_at'] ?? null),
            'createdAt'    => self::iso($row['created_at'] ?? null),
        ];
    }

    public static function teacherFeedback(array $row): array
    {
        return [
            'id'            => (int) $row['id'],
            'teacherId'     => (int) $row['teacher_id'],
            'classId'       => (int) $row['course_class_id'],
            'adminId'       => (int) $row['admin_id'],
            'strengths'     => $row['strengths'] ?? null,
            'improvements'  => $row['improvements'] ?? null,
            'gems'          => $row['gems'] ?? null,
            'teacherSeenAt' => self::iso($row['teacher_seen_at'] ?? null),
            'createdAt'     => self::iso($row['created_at'] ?? null),
        ];
    }

    private static function iso(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts !== false ? gmdate('Y-m-d\TH:i:s\Z', $ts) : $value;
    }

    private static function date(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return substr($value, 0, 10);
    }

    private static function json(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
