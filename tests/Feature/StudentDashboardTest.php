<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Student\Services\StudentService;
use App\Modules\Teacher\Services\TeacherGradeService;
use App\Modules\Teacher\Services\TeacherTermService;
use App\Shared\Enums\Role;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\TermRepository;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    public function testStudentDashboardHidesActiveTermGrades(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09126000001');
        $studentId = $this->createUser(Role::Student->value, '09126000002');

        $classes = new ClassRepository();
        $classId = $classes->create('Class D', '1');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $studentId, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository());
        $gradeService = new TeacherGradeService($classes, new TermRepository(), new GradeRepository(), new FeedbackRepository());
        $studentService = new StudentService(new GradeRepository(), new TermRepository(), $classes, new MessageRepository());

        $term = $termService->createTerm($teacherId, $classId, ['name' => 'Active Term', 'startDate' => '2026-05-01']);
        $gradeService->submitGrade($teacherId, [
            'termId'    => $term['id'],
            'studentId' => $studentId,
            'score'     => 17,
        ]);

        $dashboard = $studentService->dashboard($studentId);
        $this->assertSame([], $dashboard['grades']);
        $this->assertNull($dashboard['latestTerm']);
        $this->assertCount(1, $dashboard['classes']);
        $this->assertSame($classId, $dashboard['classes'][0]['id']);
        $this->assertSame('Class D', $dashboard['classes'][0]['name']);
        $this->assertSame('1', $dashboard['classes'][0]['level']);
        $this->assertCount(1, $dashboard['classes'][0]['teachers']);
        $this->assertSame($teacherId, $dashboard['classes'][0]['teachers'][0]['id']);
    }

    public function testStudentDashboardShowsEndedTermGrades(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09126000003');
        $studentId = $this->createUser(Role::Student->value, '09126000004');

        $classes = new ClassRepository();
        $classId = $classes->create('Class E', '1');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $studentId, 'student');

        $termService = new TeacherTermService($classes, new TermRepository(), new GradeRepository());
        $gradeService = new TeacherGradeService($classes, new TermRepository(), new GradeRepository(), new FeedbackRepository());
        $studentService = new StudentService(new GradeRepository(), new TermRepository(), $classes, new MessageRepository());

        $term = $termService->createTerm($teacherId, $classId, ['name' => 'Ended Term', 'startDate' => '2026-01-01']);
        $gradeService->submitGrade($teacherId, [
            'termId'    => $term['id'],
            'studentId' => $studentId,
            'score'     => 19,
        ]);
        $termService->endTerm($teacherId, $term['id'], ['endDate' => '2026-02-01']);

        $dashboard = $studentService->dashboard($studentId);
        $this->assertCount(1, $dashboard['grades']);
        $this->assertSame(19.0, $dashboard['grades'][0]['score']);
        $this->assertNotNull($dashboard['latestTerm']);
    }
}
