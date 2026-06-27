<?php

declare(strict_types=1);

namespace App\Modules\Student\Services;

use App\Infrastructure\Database\DB;
use App\Shared\Enums\MessageStatus;
use App\Shared\Enums\MessageType;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Validators\Validator;

class StudentService
{
    public function __construct(
        private GradeRepository $grades,
        private TermRepository $terms,
        private ClassRepository $classes,
        private MessageRepository $messages
    ) {
    }

    public function dashboard(int $studentId): array
    {
        $gradeRows = $this->grades->forStudentEndedTerms($studentId);
        $grades = array_map(static fn (array $r) => ResourceTransformer::grade($r), $gradeRows);

        $terms = [];
        foreach ($this->terms->endedTermsForStudent($studentId) as $term) {
            $terms[] = ResourceTransformer::term($term);
        }

        return [
            'grades'      => $grades,
            'endedTerms'  => $terms,
            'latestTerm'  => $terms[0] ?? null,
        ];
    }

    public function listMessages(int $studentId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->messages->listForStudent($studentId, $perPage, $offset);
        $items = array_map(static fn (array $r) => ResourceTransformer::message($r), $rows);
        return [
            'items'       => $items,
            'total'       => $this->messages->countForStudent($studentId),
            'page'        => $page,
            'per_page'    => $perPage,
            'unreadCount' => $this->messages->unreadCountForStudent($studentId),
        ];
    }

    public function createMessage(int $studentId, array $input): array
    {
        Validator::make($input)
            ->required('type')
            ->required('title')
            ->required('body')
            ->in('type', MessageType::values())
            ->validate();

        $classId = isset($input['classId']) ? (int) $input['classId'] : null;
        if ($classId !== null && !$this->classes->hasMembership($classId, $studentId, 'student')) {
            throw new \App\Shared\Exceptions\ForbiddenException('You are not a student of this class.');
        }

        $messageId = DB::transaction(function () use ($studentId, $input, $classId) {
            $id = $this->messages->create([
                'student_id'      => $studentId,
                'course_class_id' => $classId,
                'type'            => (string) $input['type'],
                'title'           => trim((string) $input['title']),
                'body'            => trim((string) $input['body']),
                'status'          => MessageStatus::Pending->value,
            ]);

            if ($classId !== null) {
                $teacherIds = $this->classes->memberUserIds($classId, 'teacher');
                $this->messages->attachTeachers($id, $teacherIds);
            }

            return $id;
        });

        return ResourceTransformer::message($this->messages->findById($messageId));
    }

    public function markMessagesSeen(int $studentId, array $input): array
    {
        $ids = $input['ids'] ?? [];
        $this->messages->markSeenByStudent($studentId, is_array($ids) ? $ids : []);
        return ['unreadCount' => $this->messages->unreadCountForStudent($studentId)];
    }
}
