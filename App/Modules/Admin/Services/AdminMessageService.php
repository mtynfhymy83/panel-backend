<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Shared\Enums\MessageType;
use App\Shared\Exceptions\BadRequestException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\UserRepository;
use App\Shared\Validators\Validator;

class AdminMessageService
{
    public function __construct(
        private MessageRepository $messages,
        private UserRepository $users,
        private ClassRepository $classes
    ) {
    }

    public function list(?string $status, ?string $type, ?string $search, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->messages->listAdmin($status, $type, $search, $perPage, $offset);
        $items = array_map(fn (array $r) => $this->transformMessage($r), $rows);
        return ['items' => $items, 'total' => $this->messages->countAdmin($status, $type, $search), 'page' => $page, 'per_page' => $perPage];
    }

    public function review(int $messageId): array
    {
        $msg = $this->messages->findById($messageId);
        if ($msg === null) {
            throw new NotFoundException('Message not found.');
        }
        if ($msg['status'] !== 'pending') {
            throw new BadRequestException('Message is already reviewed.');
        }
        $this->messages->markReviewed($messageId);
        return $this->transformMessage($this->messages->findById($messageId));
    }

    public function reply(int $messageId, array $input, int $adminId): array
    {
        Validator::make($input)->required('adminReply')->validate();
        $msg = $this->messages->findById($messageId);
        if ($msg === null) {
            throw new NotFoundException('Message not found.');
        }
        if ($msg['status'] === 'pending') {
            throw new BadRequestException('Message must be reviewed before replying.');
        }
        $this->messages->reply($messageId, trim((string) $input['adminReply']), $adminId);
        return $this->transformMessage($this->messages->findById($messageId));
    }

    private function transformMessage(?array $row): array
    {
        if ($row === null) {
            return [];
        }

        $student = $this->users->findById((int) $row['student_id']);
        $classId = isset($row['course_class_id']) ? (int) $row['course_class_id'] : null;
        $class = $classId !== null ? $this->classes->findById($classId) : null;

        return ResourceTransformer::messageWithContext($row, $student, $class);
    }
}
