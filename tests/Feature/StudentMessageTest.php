<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Student\Services\StudentService;
use App\Shared\Enums\MessageType;
use App\Shared\Enums\Role;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\TermRepository;
use Tests\TestCase;

class StudentMessageTest extends TestCase
{
    public function testCreateMessageAcceptsClassIdSnakeCase(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09128000001', 'Ali', 'Teacher');
        $studentId = $this->createUser(Role::Student->value, '09128000002');

        $classes = new ClassRepository();
        $classId = $classes->create('Class Message', '2');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $studentId, 'student');

        $service = new StudentService(new GradeRepository(), new TermRepository(), $classes, new MessageRepository());
        $message = $service->createMessage($studentId, [
            'type'     => MessageType::Question->value,
            'title'    => 'سوال درباره کلاس',
            'body'     => 'متن پیام',
            'class_id' => $classId,
        ]);

        $this->assertSame($classId, $message['classId']);
    }

    public function testCreateMessageAcceptsClassIdCamelCase(): void
    {
        $teacherId = $this->createUser(Role::Teacher->value, '09128000003');
        $studentId = $this->createUser(Role::Student->value, '09128000004');

        $classes = new ClassRepository();
        $classId = $classes->create('Class Message 2', '3');
        $classes->addMember($classId, $teacherId, 'teacher');
        $classes->addMember($classId, $studentId, 'student');

        $service = new StudentService(new GradeRepository(), new TermRepository(), $classes, new MessageRepository());
        $message = $service->createMessage($studentId, [
            'type'    => MessageType::General->value,
            'title'   => 'پیام',
            'body'    => 'متن',
            'classId' => $classId,
        ]);

        $this->assertSame($classId, $message['classId']);
    }
}
