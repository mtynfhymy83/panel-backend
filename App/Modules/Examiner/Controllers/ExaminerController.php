<?php

declare(strict_types=1);

namespace App\Modules\Examiner\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Examiner\Services\ExaminerService;
use App\Shared\Services\AuthContext;

class ExaminerController extends Controller
{
    public function __construct(private ExaminerService $examiner)
    {
    }

    public function dashboard(): array
    {
        return $this->ok($this->examiner->dashboard(AuthContext::requireUserId()));
    }

    public function getExam(int $class): array
    {
        return $this->ok($this->examiner->getExam(AuthContext::requireUserId(), $class));
    }

    public function submitExam(int $class, array $request): array
    {
        return $this->created($this->examiner->submitExam(AuthContext::requireUserId(), $class, $request));
    }
}
