<?php

declare(strict_types=1);

namespace App\Modules\Student\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Student\Services\StudentService;
use App\Shared\Services\AuthContext;

class StudentController extends Controller
{
    public function __construct(private StudentService $student)
    {
    }

    public function dashboard(): array
    {
        return $this->ok($this->student->dashboard(AuthContext::requireUserId()));
    }

    public function listMessages(int $page = 1, int $per_page = 20): array
    {
        $r = $this->student->listMessages(AuthContext::requireUserId(), $page, $per_page);
        return $this->paginated($r['items'], $r['total'], $r['page'], $r['per_page']);
    }

    public function createMessage(array $request): array
    {
        return $this->created($this->student->createMessage(AuthContext::requireUserId(), $request));
    }

    public function markMessagesSeen(array $request): array
    {
        return $this->updated($this->student->markMessagesSeen(AuthContext::requireUserId(), $request));
    }
}
