<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Admin\Services\AdminDashboardService;
use App\Modules\Examiner\Services\ExaminerService;
use App\Modules\Teacher\Services\TeacherTermService;
use App\Shared\Enums\ExamCriteria;
use App\Shared\Enums\Role;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Repositories\UserRepository;
use App\Shared\Repositories\UserRepository;
use Tests\TestCase;

class AdminProgressTest extends TestCase
{
    public function testAdminProgressReturnsStudentsExamsAndCriteria(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09126000001');
        $examinerId = $this->createUser(Role::Examiner->value, '09126000002');
        $studentId = $this->createUser(Role::Student->value, '09126000003', 'Reza', 'Moradi');

        $classes = new ClassRepository();
        $classId = $classes->create('Class Progress', '2');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $examinerId, 'examiner');
        $classes->addMember($classId, $studentId, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository(), new ExamRepository(), new UserRepository());
        $termService->createTerm($teacherId, $classId, ['name' => 'Progress Term', 'startDate' => '2026-06-01']);

        $examinerService = new ExaminerService($classes, new TermRepository(), new ExamRepository());
        $examinerService->submitExam($examinerId, $classId, [
            'examDate'      => '2026-06-15',
            'studentScores' => [
                (string) $studentId => [
                    'note'   => 'خوب بود',
                    'scores' => [
                        'phonics'    => 5,
                        'receptive'  => 4,
                        'vocabulary' => 2,
                    ],
                ],
            ],
        ]);

        $service = new AdminDashboardService(
            new UserRepository(),
            $classes,
            new TermRepository(),
            new MessageRepository(),
            new FeedbackRepository(),
            new ExamRepository(),
            new GradeRepository()
        );

        $progress = $service->progress($classId);

        $this->assertSame($classId, $progress['classId']);
        $this->assertNotNull($progress['activeTerm']);
        $this->assertCount(1, $progress['students']);
        $this->assertSame($studentId, $progress['students'][0]['id']);
        $this->assertSame('Reza Moradi', $progress['students'][0]['fullName']);
        $this->assertCount(1, $progress['exams']);
        $this->assertSame($classId, $progress['exams'][0]['classId']);
        $this->assertSame($examinerId, $progress['exams'][0]['examinerId']);
        $this->assertSame('خوب بود', $progress['exams'][0]['studentScores'][(string) $studentId]['note']);
        $this->assertSame(5, $progress['exams'][0]['studentScores'][(string) $studentId]['scores']['phonics']);
        $this->assertSame(ExamCriteria::labels(), $progress['criteria']);
        $this->assertSame([], $progress['grades']);
    }
}
