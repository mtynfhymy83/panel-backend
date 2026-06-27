<?php

declare(strict_types=1);

namespace App\Modules\Admin\Services;

use App\Infrastructure\Database\DB;
use App\Shared\Enums\Level;
use App\Shared\Enums\MembershipRole;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Exceptions\ValidationException;
use App\Shared\Http\ResourceTransformer;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\UserRepository;
use App\Shared\Validators\Validator;

class AdminClassService
{
    public function __construct(
        private ClassRepository $classes,
        private UserRepository $users
    ) {
    }

    public function list(?string $search, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $offset = ($page - 1) * $perPage;
        $rows = $this->classes->list($search, $perPage, $offset);
        $items = array_map(fn (array $r) => ResourceTransformer::courseClass($r, $this->classes->memberships((int) $r['id'])), $rows);
        return ['items' => $items, 'total' => $this->classes->count($search), 'page' => $page, 'per_page' => $perPage];
    }

    public function create(array $input): array
    {
        Validator::make($input)->required('name')->required('level')->in('level', Level::values())->validate();
        $id = $this->classes->create(trim((string) $input['name']), (string) $input['level']);
        return $this->getClass($id);
    }

    public function update(int $classId, array $input): array
    {
        $this->assertClassExists($classId);
        Validator::make($input)->required('name')->required('level')->in('level', Level::values())->validate();
        $this->classes->update($classId, trim((string) $input['name']), (string) $input['level']);
        return $this->getClass($classId);
    }

    public function delete(int $classId): void
    {
        $this->assertClassExists($classId);
        $this->classes->delete($classId);
    }

    public function addMember(int $classId, array $input): array
    {
        $this->assertClassExists($classId);
        Validator::make($input)->required('userId')->required('role')->in('role', MembershipRole::values())->validate();

        $userId = (int) $input['userId'];
        $role = (string) $input['role'];
        if ($this->users->findById($userId) === null) {
            throw new NotFoundException('User not found.');
        }

        DB::transaction(function () use ($classId, $userId, $role) {
            if (!$this->classes->hasMembership($classId, $userId, $role)) {
                $this->classes->addMember($classId, $userId, $role);
            }
        });

        return $this->getClass($classId);
    }

    public function removeMember(int $classId, int $userId, string $role): array
    {
        $this->assertClassExists($classId);
        if (!in_array($role, MembershipRole::values(), true)) {
            throw new ValidationException(['role' => 'Invalid role.']);
        }
        $this->classes->removeMember($classId, $userId, $role);
        return $this->getClass($classId);
    }

    private function getClass(int $classId): array
    {
        $row = $this->classes->findById($classId);
        return ResourceTransformer::courseClass($row, $this->classes->memberships($classId));
    }

    private function assertClassExists(int $classId): void
    {
        if ($this->classes->findById($classId) === null) {
            throw new NotFoundException('Class not found.');
        }
    }
}
