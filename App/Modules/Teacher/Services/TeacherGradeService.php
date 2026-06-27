<?php

declare(strict_types=1);

namespace App\Modules\Teacher\Services;

use App\Infrastructure\Database\DB;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ForbiddenException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Validators\Validator;

class TeacherGradeService
{
    public function __construct(
        private ClassRepository $classes,
        private TermRepository $terms,
        private GradeRepository $grades,
        private FeedbackRepository $feedbacks
    ) {
    }

    public function submitGrade(int $teacherId, array $input): array
    {
        Validator::make($input)->required('termId')->required('studentId')->validate();

        $termId = (int) $input['termId'];
        $studentId = (int) $input['studentId'];
        $term = $this->terms->findById($termId);
        if ($term === null) {
            throw new NotFoundException('Term not found.');
        }

        $classId = (int) $term['course_class_id'];
        if (!$this->classes->hasMembership($classId, $teacherId, 'teacher')) {
            throw new ForbiddenException('You are not assigned to this class as a teacher.');
        }
        if (!$this->classes->hasMembership($classId, $studentId, 'student')) {
            throw new BadRequestException('Student is not a member of this class.');
        }
        if (!(bool) $term['is_active']) {
            throw new BadRequestException('Cannot grade an ended term.');
        }

        $gradeId = DB::transaction(function () use ($input, $teacherId, $classId, $termId, $studentId) {
            return $this->grades->upsert([
                'course_class_id'        => $classId,
                'term_id'                => $termId,
                'student_id'             => $studentId,
                'teacher_id'             => $teacherId,
                'created_by_teacher_id'  => $teacherId,
                'updated_by_teacher_id'  => $teacherId,
                'criteria_scores'        => $input['criteriaScores'] ?? $input['criteria_scores'] ?? [],
                'score'                  => $input['score'] ?? null,
                'total_score'            => $input['totalScore'] ?? $input['total_score'] ?? null,
                'max_total_score'        => $input['maxTotalScore'] ?? $input['max_total_score'] ?? null,
                'feedback'               => $input['feedback'] ?? null,
            ]);
        });

        $row = $this->grades->findByTermAndStudent($termId, $studentId);
        return ResourceTransformer::grade($row);
    }

    public function listFeedbacks(int $teacherId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->feedbacks->listForTeacher($teacherId, $perPage, $offset);
        $items = array_map(static fn (array $r) => ResourceTransformer::teacherFeedback($r), $rows);
        return [
            'items'        => $items,
            'total'        => $this->feedbacks->countForTeacher($teacherId),
            'page'         => $page,
            'per_page'     => $perPage,
            'unreadCount'  => $this->feedbacks->unreadCountForTeacher($teacherId),
        ];
    }

    public function markFeedbacksSeen(int $teacherId, array $input): array
    {
        $ids = $input['ids'] ?? [];
        $this->feedbacks->markSeen($teacherId, is_array($ids) ? $ids : []);
        return ['unreadCount' => $this->feedbacks->unreadCountForTeacher($teacherId)];
    }
}
