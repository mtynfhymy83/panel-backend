<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Repositories\UserRepository;

class AdminDashboardService
{
    public function __construct(
        private UserRepository $users,
        private ClassRepository $classes,
        private TermRepository $terms,
        private MessageRepository $messages,
        private FeedbackRepository $feedbacks,
        private ExamRepository $exams,
        private GradeRepository $grades
    ) {
    }

    public function dashboard(): array
    {
        return [
            'usersCount'    => $this->users->count(),
            'classesCount'  => $this->classes->count(),
            'termsCount'    => $this->terms->count(),
            'pendingMessages' => $this->messages->countAdmin('pending'),
            'feedbacksCount'  => $this->feedbacks->countAdmin(),
        ];
    }

    public function listTerms(?int $classId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->terms->list($classId, $perPage, $offset);
        $items = array_map(static fn (array $r) => ResourceTransformer::term($r), $rows);
        return ['items' => $items, 'total' => $this->terms->count($classId), 'page' => $page, 'per_page' => $perPage];
    }

    public function listExams(?int $classId, ?string $level, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->exams->list($classId, $level, $perPage, $offset);
        $items = array_map(static fn (array $r) => ResourceTransformer::exam($r), $rows);
        return ['items' => $items, 'total' => $this->exams->count($classId, $level), 'page' => $page, 'per_page' => $perPage];
    }

    public function progress(?int $classId): array
    {
        if ($classId === null) {
            return ['classes' => []];
        }
        $class = $this->classes->findById($classId);
        if ($class === null) {
            return ['classes' => []];
        }
        $term = $this->terms->activeForClass($classId);
        $studentCount = count($this->classes->memberUserIds($classId, 'student'));
        $gradeCount = $term ? $this->grades->countForTerm((int) $term['id']) : 0;
        return [
            'classId'       => $classId,
            'activeTerm'    => $term ? ResourceTransformer::term($term) : null,
            'studentCount'  => $studentCount,
            'gradedCount'   => $gradeCount,
            'allGraded'     => $term !== null && $studentCount > 0 && $gradeCount >= $studentCount,
        ];
    }
}
