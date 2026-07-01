<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Examiner\Services\ExaminerService;
use App\Modules\Teacher\Services\TeacherGradeService;
use App\Modules\Teacher\Services\TeacherTermService;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Repositories\UserRepository;
use Tests\TestCase;

class TermLifecycleTest extends TestCase
{
    public function testTeacherCannotEndTermBeforeAllStudentsGraded(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09125000001');
        $student1 = $this->createUser(Role::Student->value, '09125000002');
        $student2 = $this->createUser(Role::Student->value, '09125000003');

        $classes = new ClassRepository();
        $classId = $classes->create('Class A', '4');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $student1, 'student');
        $classes->addMember($classId, $student2, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository(), new ExamRepository(), new UserRepository());
        $term = $termService->createTerm($teacherId, $classId, [
            'name'      => 'Term 1',
            'startDate' => '2026-01-01',
        ]);

        $gradeService = new TeacherGradeService($classes, new TermRepository(), new GradeRepository(), new FeedbackRepository());
        $gradeService->submitGrade($teacherId, [
            'termId'    => $term['id'],
            'studentId' => $student1,
            'score'     => 18,
        ]);

        $this->expectException(BadRequestException::class);
        $termService->endTerm($teacherId, $term['id'], ['endDate' => '2026-02-01']);
    }

    public function testTeacherCanEndTermWhenAllStudentsGraded(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09125000004');
        $student1 = $this->createUser(Role::Student->value, '09125000005');

        $classes = new ClassRepository();
        $classId = $classes->create('Class B', '3');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $student1, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository(), new ExamRepository(), new UserRepository());
        $term = $termService->createTerm($teacherId, $classId, [
            'name'      => 'Term 2',
            'startDate' => '2026-03-01',
        ]);

        $gradeService = new TeacherGradeService($classes, new TermRepository(), new GradeRepository(), new FeedbackRepository());
        $gradeService->submitGrade($teacherId, [
            'termId'    => $term['id'],
            'studentId' => $student1,
            'score'     => 20,
        ]);

        $ended = $termService->endTerm($teacherId, $term['id'], ['endDate' => '2026-04-01']);
        $this->assertFalse($ended['isActive']);
        $this->assertSame('2026-04-01', $ended['endDate']);
    }

    public function testCannotCreateTermWhenActiveExists(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09125000006');
        $classes = new ClassRepository();
        $classId = $classes->create('Class C', '2');
        $classes->addMember($classId, $teacherId, 'teacher');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository(), new ExamRepository(), new UserRepository());
        $termService->createTerm($teacherId, $classId, ['name' => 'Active', 'startDate' => '2026-01-01']);

        $this->expectException(BadRequestException::class);
        $termService->createTerm($teacherId, $classId, ['name' => 'Second', 'startDate' => '2026-02-01']);
    }

    public function testListClassesIncludesActiveTerm(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09125000007');
        $classes = new ClassRepository();
        $classId = $classes->create('Class With Term', '1');
        $classes->addMember($classId, $teacherId, 'teacher');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository(), new ExamRepository(), new UserRepository());
        $termService->createTerm($teacherId, $classId, ['name' => 'ترم 1', 'startDate' => '2026-06-29']);

        $items = $termService->listClasses($teacherId);

        $this->assertCount(1, $items);
        $this->assertSame($classId, $items[0]['id']);
        $this->assertNotNull($items[0]['activeTerm']);
        $this->assertSame('ترم 1', $items[0]['activeTerm']['name']);
        $this->assertSame($classId, $items[0]['activeTerm']['classId']);
        $this->assertSame('2026-06-29', $items[0]['activeTerm']['startDate']);
        $this->assertTrue($items[0]['activeTerm']['isActive']);
    }

    public function testListClassesIncludesExams(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09125000008');
        $examinerId = $this->createUser(Role::Examiner->value, '09125000009', 'Sara', 'Examiner');
        $studentId = $this->createUser(Role::Student->value, '09125000010');

        $classes = new ClassRepository();
        $classId = $classes->create('Class With Exam', '2');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $examinerId, 'examiner');
        $classes->addMember($classId, $studentId, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository(), new ExamRepository(), new UserRepository());
        $termService->createTerm($teacherId, $classId, ['name' => 'Exam Term', 'startDate' => '2026-06-01']);

        $examinerService = new ExaminerService($classes, new TermRepository(), new ExamRepository());
        $examinerService->submitExam($examinerId, $classId, [
            'examDate'      => '2026-06-15',
            'studentScores' => [
                (string) $studentId => [
                    'note'   => 'خوب',
                    'scores' => ['phonics' => 5, 'receptive' => 4, 'vocabulary' => 3],
                ],
            ],
        ]);

        $items = $termService->listClasses($teacherId);
        $exams = $termService->listExams($teacherId, $classId);

        $this->assertCount(1, $items[0]['exams']);
        $this->assertSame(1, $items[0]['examCount']);
        $this->assertSame($classId, $items[0]['exams'][0]['classId']);
        $this->assertSame('2026-06-15', $items[0]['exams'][0]['examDate']);
        $this->assertSame('Sara Examiner', $items[0]['exams'][0]['examiner']['fullName']);
        $this->assertCount(1, $exams);
        $this->assertSame($examinerId, $exams[0]['examinerId']);
    }
}
