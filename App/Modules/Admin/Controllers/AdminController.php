<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminClassService;
use App\Modules\Admin\Services\AdminDashboardService;
use App\Modules\Admin\Services\AdminFeedbackService;
use App\Modules\Admin\Services\AdminMessageService;
use App\Modules\Admin\Services\AdminUserService;
use App\Shared\Services\AuthContext;

class AdminController extends Controller
{
    public function __construct(
        private AdminDashboardService $dashboard,
        private AdminUserService $users,
        private AdminClassService $classes,
        private AdminMessageService $messages,
        private AdminFeedbackService $feedbacks
    ) {
    }

    public function dashboard(): array
    {
        return $this->ok($this->dashboard->dashboard());
    }

    public function listUsers(?string $role = null, ?string $search = null, int $page = 1, int $per_page = 20): array
    {
        $r = $this->users->list($role, $search, $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function updateUserRoles(int $user, array $request): array
    {
        return $this->updated($this->users->updateRoles($user, $request, AuthContext::requireUserId()));
    }

    public function deleteUser(int $user): array
    {
        $this->users->deleteUser($user, AuthContext::requireUserId());
        return $this->deleted();
    }

    public function listClasses(?string $search = null, int $page = 1, int $per_page = 20): array
    {
        $r = $this->classes->list($search, $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function createClass(array $request): array
    {
        return $this->created($this->classes->create($request));
    }

    public function updateClass(int $class, array $request): array
    {
        return $this->updated($this->classes->update($class, $request));
    }

    public function deleteClass(int $class): array
    {
        $this->classes->delete($class);
        return $this->deleted();
    }

    public function addMember(int $class, array $request): array
    {
        return $this->updated($this->classes->addMember($class, $request));
    }

    public function removeMember(int $class, int $user, ?string $role = null): array
    {
        return $this->updated($this->classes->removeMember($class, $user, (string) $role));
    }

    public function listTerms(?int $class_id = null, int $page = 1, int $per_page = 20): array
    {
        $r = $this->dashboard->listTerms($class_id, $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function listExams(?int $class_id = null, ?string $level = null, int $page = 1, int $per_page = 20): array
    {
        $r = $this->dashboard->listExams($class_id, $level, $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function progress(?int $class_id = null): array
    {
        return $this->ok($this->dashboard->progress($class_id));
    }

    public function listMessages(?string $status = null, ?string $type = null, ?string $search = null, int $page = 1, int $per_page = 20): array
    {
        $r = $this->messages->list($status, $type, $search, $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function reviewMessage(int $message): array
    {
        return $this->updated($this->messages->review($message));
    }

    public function replyMessage(int $message, array $request): array
    {
        return $this->updated($this->messages->reply($message, $request, AuthContext::requireUserId()));
    }

    public function listFeedbacks(?int $teacher_id = null, ?int $class_id = null, int $page = 1, int $per_page = 20): array
    {
        $r = $this->feedbacks->list($teacher_id, $class_id, $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function createFeedback(array $request): array
    {
        return $this->created($this->feedbacks->create($request, AuthContext::requireUserId()));
    }
}
