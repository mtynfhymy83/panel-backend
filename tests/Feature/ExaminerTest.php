<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Examiner\Services\ExaminerService;
use App\Modules\Teacher\Services\TeacherTermService;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ConflictException;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\TermRepository;
use Tests\TestCase;

class ExaminerTest extends TestCase
{
    public function testExaminerCannotSubmitWithoutActiveTerm(): void
    {
        $examinerId = $this->createUser('exam1', Role::Examiner->value);
        $classes = new ClassRepository();
        $classId = $classes->create('Class F', '2');
        $classes->addMember($classId, $examinerId, 'examiner');

        $service = new ExaminerService($classes, new TermRepository(), new ExamRepository());
        $this->expectException(BadRequestException::class);
        $service->submitExam($examinerId, $classId, ['examDate' => '2026-06-01']);
    }

    public function testExaminerDuplicateSubmitReturnsConflict(): void
    {
        $teacherId = $this->createUser('t3', Role::Teacher->value);
        $examinerId = $this->createUser('exam2', Role::Examiner->value);

        $classes = new ClassRepository();
        $classId = $classes->create('Class G', '3');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $examinerId, 'examiner');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository());
        $termService->createTerm($teacherId, $classId, ['name' => 'Exam Term', 'startDate' => '2026-06-01']);

        $service = new ExaminerService($classes, new TermRepository(), new ExamRepository());
        $service->submitExam($examinerId, $classId, ['examDate' => '2026-06-15']);

        $this->expectException(ConflictException::class);
        $service->submitExam($examinerId, $classId, ['examDate' => '2026-06-16']);
    }
}
