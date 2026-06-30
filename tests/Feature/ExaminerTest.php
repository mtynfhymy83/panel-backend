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
    public function testExaminerDashboardReturnsAssignedClassesAndExams(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09127000010');
        $studentId = $this->createUser(Role::Student->value, '09127000011');
        $examinerId = $this->createUser(Role::Examiner->value, '09127000012');

        $classes = new ClassRepository();
        $classId = $classes->create('Class H', '2');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $studentId, 'student');
        $classes->addMember($classId, $examinerId, 'examiner');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository());
        $termService->createTerm($teacherId, $classId, ['name' => 'Dashboard Term', 'startDate' => '2026-06-01']);

        $service = new ExaminerService($classes, new TermRepository(), new ExamRepository());
        $service->submitExam($examinerId, $classId, ['examDate' => '2026-06-15']);

        $dashboard = $service->dashboard($examinerId);

        $this->assertSame(1, $dashboard['classesCount']);
        $this->assertCount(1, $dashboard['classes']);
        $this->assertSame($classId, $dashboard['classes'][0]['id']);
        $this->assertSame('Class H', $dashboard['classes'][0]['name']);
        $this->assertSame('2', $dashboard['classes'][0]['level']);
        $this->assertSame([$teacherId], $dashboard['classes'][0]['teacherIds']);
        $this->assertSame([$studentId], $dashboard['classes'][0]['studentIds']);
        $this->assertSame([$examinerId], $dashboard['classes'][0]['examinerIds']);
        $this->assertNotNull($dashboard['classes'][0]['activeTerm']);
        $this->assertSame('Dashboard Term', $dashboard['classes'][0]['activeTerm']['name']);
        $this->assertCount(1, $dashboard['exams']);
        $this->assertSame($classId, $dashboard['exams'][0]['classId']);
        $this->assertSame($examinerId, $dashboard['exams'][0]['examinerId']);
    }

    public function testExaminerCannotSubmitWithoutActiveTerm(): void
    {
        $examinerId = $this->createUser(Role::Examiner->value, '09127000001');
        $classes = new ClassRepository();
        $classId = $classes->create('Class F', '2');
        $classes->addMember($classId, $examinerId, 'examiner');

        $service = new ExaminerService($classes, new TermRepository(), new ExamRepository());
        $this->expectException(BadRequestException::class);
        $service->submitExam($examinerId, $classId, ['examDate' => '2026-06-01']);
    }

    public function testExaminerDuplicateSubmitReturnsConflict(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09127000002');
        $examinerId = $this->createUser(Role::Examiner->value, '09127000003');

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
