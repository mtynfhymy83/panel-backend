<?php

declare(strict_types=1);

namespace App\Modules\Teacher\Services;

use App\Infrastructure\Database\DB;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ForbiddenException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Repositories\UserRepository;
use App\Shared\Validators\Validator;

class TeacherTermService
{
    public function __construct(
        private ClassRepository $classes,
        private TermRepository $terms,
        private GradeRepository $grades,
        private ExamRepository $exams,
        private UserRepository $users
    ) {
    }

    public function dashboard(int $teacherId): array
    {
        $classRows = $this->classes->classesForUser($teacherId, 'teacher');
        return [
            'classesCount' => count($classRows),
            'activeTerms'  => count(array_filter($classRows, fn ($c) => $this->terms->activeForClass((int) $c['id']) !== null)),
        ];
    }

    public function listClasses(int $teacherId): array
    {
        $rows = $this->classes->classesForUser($teacherId, 'teacher');
        return array_map(function (array $r) {
            $classId = (int) $r['id'];
            $class = ResourceTransformer::courseClass($r, $this->classes->memberships($classId));
            $activeTerm = $this->terms->activeForClass($classId);
            $class['activeTerm'] = $activeTerm ? ResourceTransformer::term($activeTerm) : null;
            $exams = $this->classExams($classId);
            $class['exams'] = $exams;
            $class['examCount'] = count($exams);
            $class['students'] = $this->classStudents($classId);

            return $class;
        }, $rows);
    }

    public function getClass(int $teacherId, int $classId): array
    {
        $this->assertTeacherOfClass($teacherId, $classId);
        $row = $this->classes->findById($classId);
        $activeTerm = $this->terms->activeForClass($classId);
        $class = ResourceTransformer::courseClass($row, $this->classes->memberships($classId));
        $class['students'] = $this->classStudents($classId);
        return [
            'class'      => $class,
            'activeTerm' => $activeTerm ? ResourceTransformer::term($activeTerm) : null,
        ];
    }

    public function listStudents(int $teacherId, int $classId): array
    {
        $this->assertTeacherOfClass($teacherId, $classId);
        $students = $this->classStudents($classId);
        return [
            'studentIds' => array_map(static fn (array $s) => $s['id'], $students),
            'students'   => $students,
        ];
    }

    public function listExams(int $teacherId, int $classId): array
    {
        $this->assertTeacherOfClass($teacherId, $classId);
        return $this->classExams($classId);
    }

    public function createTerm(int $teacherId, int $classId, array $input): array
    {
        $this->assertTeacherOfClass($teacherId, $classId);
        Validator::make($input)->required('name')->required('startDate')->validate();

        $startDate = (string) $input['startDate'];
        if ($this->terms->activeForClass($classId) !== null) {
            throw new BadRequestException('Class already has an active term.');
        }

        $lastEnded = $this->terms->lastEndedForClass($classId);
        if ($lastEnded !== null && !empty($lastEnded['end_date']) && $startDate < $lastEnded['end_date']) {
            throw new BadRequestException('Start date cannot be before the last term end date.');
        }

        $termId = DB::transaction(function () use ($classId, $input, $teacherId, $startDate) {
            return $this->terms->create($classId, trim((string) $input['name']), $startDate, $teacherId);
        });

        return ResourceTransformer::term($this->terms->findById($termId));
    }

    public function endTerm(int $teacherId, int $termId, array $input): array
    {
        Validator::make($input)->required('endDate')->validate();

        $term = $this->terms->findById($termId);
        if ($term === null) {
            throw new NotFoundException('Term not found.');
        }

        $classId = (int) $term['course_class_id'];
        $this->assertTeacherOfClass($teacherId, $classId);

        if (!(bool) $term['is_active']) {
            throw new BadRequestException('Term is already ended.');
        }

        $studentIds = $this->classes->memberUserIds($classId, 'student');
        $gradeCount = $this->grades->countForTerm($termId);
        if ($studentIds !== [] && $gradeCount < count($studentIds)) {
            throw new BadRequestException('All students must have grades before ending the term.');
        }

        DB::transaction(function () use ($termId, $input, $teacherId, $studentIds) {
            $this->terms->endTerm($termId, (string) $input['endDate'], $teacherId, $studentIds);
        });

        return ResourceTransformer::term($this->terms->findById($termId));
    }

    private function assertTeacherOfClass(int $teacherId, int $classId): void
    {
        if (!$this->classes->hasMembership($classId, $teacherId, 'teacher')) {
            throw new ForbiddenException('You are not assigned to this class as a teacher.');
        }
    }

    /** @return list<array> */
    private function classStudents(int $classId): array
    {
        $rows = $this->classes->membersByRole($classId, 'student');
        return array_map(static function (array $row): array {
            $firstName = (string) ($row['first_name'] ?? '');
            $lastName = (string) ($row['last_name'] ?? '');

            return [
                'id'       => (int) $row['id'],
                'fullName' => trim($firstName . ' ' . $lastName),
            ];
        }, $rows);
    }

    /** @return list<array> */
    private function classExams(int $classId): array
    {
        $rows = $this->exams->listForClass($classId);
        return array_map(fn (array $row) => $this->transformExam($row), $rows);
    }

    private function transformExam(array $row): array
    {
        $exam = ResourceTransformer::exam($row);
        $examiner = $this->users->findById((int) $row['examiner_id']);
        if ($examiner === null) {
            $exam['examiner'] = null;
            return $exam;
        }

        $firstName = (string) ($examiner['first_name'] ?? '');
        $lastName = (string) ($examiner['last_name'] ?? '');
        $exam['examiner'] = [
            'id'       => (int) $examiner['id'],
            'fullName' => trim($firstName . ' ' . $lastName),
        ];

        return $exam;
    }
}
