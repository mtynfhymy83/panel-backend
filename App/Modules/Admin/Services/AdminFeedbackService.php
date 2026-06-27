<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Shared\Exceptions\NotFoundException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\UserRepository;
use App\Shared\Validators\Validator;

class AdminFeedbackService
{
    public function __construct(
        private FeedbackRepository $feedbacks,
        private UserRepository $users,
        private ClassRepository $classes
    ) {
    }

    public function list(?int $teacherId, ?int $classId, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->feedbacks->listAdmin($teacherId, $classId, $perPage, $offset);
        $items = array_map(static fn (array $r) => ResourceTransformer::teacherFeedback($r), $rows);
        return ['items' => $items, 'total' => $this->feedbacks->countAdmin($teacherId, $classId), 'page' => $page, 'per_page' => $perPage];
    }

    public function create(array $input, int $adminId): array
    {
        Validator::make($input)->required('teacherId')->required('classId')->validate();
        $teacherId = (int) $input['teacherId'];
        $classId = (int) $input['classId'];

        if ($this->users->findById($teacherId) === null) {
            throw new NotFoundException('Teacher not found.');
        }
        if ($this->classes->findById($classId) === null) {
            throw new NotFoundException('Class not found.');
        }

        $id = $this->feedbacks->create([
            'teacher_id'       => $teacherId,
            'course_class_id'  => $classId,
            'admin_id'         => $adminId,
            'strengths'        => $input['strengths'] ?? null,
            'improvements'     => $input['improvements'] ?? null,
            'gems'             => $input['gems'] ?? null,
        ]);

        $row = $this->feedbacks->findById($id);
        return ResourceTransformer::teacherFeedback($row ?? ['id' => $id]);
    }
}
