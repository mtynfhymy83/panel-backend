<?php

declare(strict_types=1);

namespace App\Modules\Teacher\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Teacher\Services\TeacherGradeService;
use App\Modules\Teacher\Services\TeacherTermService;
use App\Shared\Services\AuthContext;

class TeacherController extends Controller
{
    public function __construct(
        private TeacherTermService $terms,
        private TeacherGradeService $grades
    ) {
    }

    public function dashboard(): array
    {
        return $this->ok($this->terms->dashboard(AuthContext::requireUserId()));
    }

    public function listClasses(): array
    {
        return $this->collection($this->terms->listClasses(AuthContext::requireUserId()));
    }

    public function createTerm(int $class, array $request): array
    {
        return $this->created($this->terms->createTerm(AuthContext::requireUserId(), $class, $request));
    }

    public function endTerm(int $term, array $request): array
    {
        return $this->updated($this->terms->endTerm(AuthContext::requireUserId(), $term, $request));
    }

    public function showClass(int $class): array
    {
        return $this->ok($this->terms->getClass(AuthContext::requireUserId(), $class));
    }

    public function listStudents(int $class): array
    {
        return $this->ok($this->terms->listStudents(AuthContext::requireUserId(), $class));
    }

    public function submitGrade(array $request): array
    {
        return $this->created($this->grades->submitGrade(AuthContext::requireUserId(), $request));
    }

    public function listFeedbacks(int $page = 1, int $per_page = 20): array
    {
        $r = $this->grades->listFeedbacks(AuthContext::requireUserId(), $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function markFeedbacksSeen(array $request): array
    {
        return $this->updated($this->grades->markFeedbacksSeen(AuthContext::requireUserId(), $request));
    }
}
