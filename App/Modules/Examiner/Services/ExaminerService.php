<?php

declare(strict_types=1);

namespace App\Modules\Examiner\Services;

use App\Infrastructure\Database\DB;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\ConflictException;
use App\Shared\Exceptions\ForbiddenException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Validators\Validator;

class ExaminerService
{
    public function __construct(
        private ClassRepository $classes,
        private TermRepository $terms,
        private ExamRepository $exams
    ) {
    }

    public function dashboard(int $examinerId): array
    {
        $classRows = $this->classes->classesForUser($examinerId, 'examiner');
        $classes = [];
        foreach ($classRows as $row) {
            $classId = (int) $row['id'];
            $activeTerm = $this->terms->activeForClass($classId);
            $class = ResourceTransformer::courseClass($row, $this->classes->memberships($classId));
            $class['activeTerm'] = $activeTerm ? ResourceTransformer::term($activeTerm) : null;
            $classes[] = $class;
        }

        $examRows = $this->exams->listForExaminer($examinerId);
        $exams = array_map(static fn (array $r) => ResourceTransformer::exam($r), $examRows);

        return [
            'classesCount' => count($classRows),
            'classes'      => $classes,
            'exams'        => $exams,
        ];
    }

    public function getExam(int $examinerId, int $classId): array
    {
        $this->assertExaminerOfClass($examinerId, $classId);
        $students = $this->classStudents($classId);
        $activeTerm = $this->terms->activeForClass($classId);
        if ($activeTerm === null) {
            return ['activeTerm' => null, 'exam' => null, 'students' => $students];
        }

        $exam = $this->exams->findByTermClassExaminer((int) $activeTerm['id'], $classId, $examinerId);
        return [
            'activeTerm' => ResourceTransformer::term($activeTerm),
            'exam'       => $exam ? ResourceTransformer::exam($exam) : null,
            'students'   => $students,
        ];
    }

    public function submitExam(int $examinerId, int $classId, array $input): array
    {
        $this->assertExaminerOfClass($examinerId, $classId);
        Validator::make($input)->required('examDate')->validate();

        $activeTerm = $this->terms->activeForClass($classId);
        if ($activeTerm === null) {
            throw new BadRequestException('Class has no active term.');
        }

        $termId = (int) $activeTerm['id'];
        if ($this->exams->findByTermClassExaminer($termId, $classId, $examinerId) !== null) {
            throw new ConflictException('Exam already submitted for this term.');
        }

        $examId = DB::transaction(function () use ($classId, $termId, $examinerId, $input) {
            return $this->exams->create([
                'course_class_id'    => $classId,
                'term_id'            => $termId,
                'examiner_id'        => $examinerId,
                'exam_date'          => (string) $input['examDate'],
                'student_scores'     => $input['studentScores'] ?? $input['student_scores'] ?? [],
                'class_strengths'    => $input['classStrengths'] ?? $input['class_strengths'] ?? null,
                'class_improvements' => $input['classImprovements'] ?? $input['class_improvements'] ?? null,
                'class_suggestions'  => $input['classSuggestions'] ?? $input['class_suggestions'] ?? null,
            ]);
        });

        $exam = $this->exams->findByTermClassExaminer($termId, $classId, $examinerId);
        if ($exam === null) {
            return ResourceTransformer::exam(['id' => $examId]);
        }
        return ResourceTransformer::exam($exam);
    }

    private function assertExaminerOfClass(int $examinerId, int $classId): void
    {
        if ($this->classes->findById($classId) === null) {
            throw new NotFoundException('Class not found.');
        }
        if (!$this->classes->hasMembership($classId, $examinerId, 'examiner')) {
            throw new ForbiddenException('You are not assigned to this class as an examiner.');
        }
    }

    /** @return list<array> */
    private function classStudents(int $classId): array
    {
        $rows = $this->classes->membersByRole($classId, 'student');
        return array_map(static fn (array $r) => ResourceTransformer::user($r, 'student'), $rows);
    }
}
