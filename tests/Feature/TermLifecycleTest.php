<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Teacher\Services\TeacherGradeService;
use App\Modules\Teacher\Services\TeacherTermService;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\TermRepository;
use Tests\TestCase;

class TermLifecycleTest extends TestCase
{
    public function testTeacherCannotEndTermBeforeAllStudentsGraded(): void
    {
        $teacherId = $this->createUser('teacher', Role::Teacher->value);
        $student1 = $this->createUser('student1', Role::Student->value);
        $student2 = $this->createUser('student2', Role::Student->value);

        $classes = new ClassRepository();
        $classId = $classes->create('Class A', '4');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $student1, 'student');
        $classes->addMember($classId, $student2, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository());
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
        $teacherId = $this->createUser('teacher2', Role::Teacher->value);
        $student1 = $this->createUser('student3', Role::Student->value);

        $classes = new ClassRepository();
        $classId = $classes->create('Class B', '3');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $student1, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository());
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
        $teacherId = $this->createUser('teacher3', Role::Teacher->value);
        $classes = new ClassRepository();
        $classId = $classes->create('Class C', '2');
        $classes->addMember($classId, $teacherId, 'teacher');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository());
        $termService->createTerm($teacherId, $classId, ['name' => 'Active', 'startDate' => '2026-01-01']);

        $this->expectException(BadRequestException::class);
        $termService->createTerm($teacherId, $classId, ['name' => 'Second', 'startDate' => '2026-02-01']);
    }
}
