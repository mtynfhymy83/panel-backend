<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Infrastructure\Database\DB;
use App\Shared\Enums\Level;
use App\Shared\Enums\MembershipRole;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\ForbiddenException;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Repositories\UserRepository;
use App\Shared\Validators\Validator;

class AdminUserService
{
    public function __construct(
        private UserRepository $users,
        private ClassRepository $classes
    ) {
    }

    public function list(?string $role, ?string $search, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->users->list($role, $search, $perPage, $offset);
        $items = [];
        foreach ($rows as $row) {
            $roles = $this->users->getRoles((int) $row['id']);
            $items[] = ResourceTransformer::user($row, $roles[0] ?? null, $roles);
        }
        return ['items' => $items, 'total' => $this->users->count($role, $search), 'page' => $page, 'per_page' => $perPage];
    }

    public function updateRoles(int $targetUserId, array $input, int $adminId): array
    {
        Validator::make($input)->required('roles')->validate();
        $roles = $input['roles'];
        if (!is_array($roles) || $roles === []) {
            throw new ValidationException(['roles' => 'At least one role is required.']);
        }
        foreach ($roles as $role) {
            if (!in_array((string) $role, Role::values(), true)) {
                throw new ValidationException(['roles' => 'Invalid role provided.']);
            }
        }

        $user = $this->users->findById($targetUserId);
        if ($user === null) {
            throw new NotFoundException('User not found.');
        }

        if ($targetUserId === $adminId) {
            throw new ForbiddenException('Cannot modify your own roles.');
        }

        DB::transaction(function () use ($targetUserId, $roles) {
            $this->users->syncRoles($targetUserId, $roles);
        });

        $updated = $this->users->findById($targetUserId);
        $userRoles = $this->users->getRoles($targetUserId);
        return ResourceTransformer::user($updated, $userRoles[0] ?? null, $userRoles);
    }

    public function deleteUser(int $targetUserId, int $adminId): void
    {
        if ($targetUserId === $adminId) {
            throw new ForbiddenException('Cannot delete your own account.');
        }

        $user = $this->users->findById($targetUserId);
        if ($user === null) {
            throw new NotFoundException('User not found.');
        }

        if ($this->users->hasRole($targetUserId, Role::Admin->value)) {
            throw new ForbiddenException('Cannot delete admin users.');
        }

        DB::transaction(function () use ($targetUserId) {
            $this->classes->removeAllMemberships($targetUserId);
            $this->users->softDelete($targetUserId);
        });
    }
}
