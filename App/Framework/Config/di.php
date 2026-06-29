<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use function DI\autowire;

use App\Domain\Contracts\Services\GreeterServiceInterface;
use App\Domain\Services\GreeterService;
use App\Domain\Contracts\Services\NoteServiceInterface;
use App\Domain\Services\NoteService;
use App\Domain\Contracts\Repositories\NoteRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\NoteRepository;

use App\Infrastructure\Cache\CacheFactory;
use App\Infrastructure\Cache\CacheInterface;
use App\Infrastructure\Mail\MailFactory;
use App\Infrastructure\Mail\MailServiceInterface;
use App\Infrastructure\Sms\SmsFactory;
use App\Infrastructure\Sms\SmsServiceInterface;

use App\Shared\Repositories\UserRepository;
use App\Shared\Repositories\RefreshTokenRepository;
use App\Shared\Repositories\ClassRepository;
use App\Shared\Repositories\TermRepository;
use App\Shared\Repositories\GradeRepository;
use App\Shared\Repositories\ExamRepository;
use App\Shared\Repositories\MessageRepository;
use App\Shared\Repositories\FeedbackRepository;
use App\Shared\Services\JwtService;
use App\Shared\Services\OtpService;
use App\Shared\Services\RefreshTokenService;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Admin\Services\AdminUserService;
use App\Modules\Admin\Services\AdminClassService;
use App\Modules\Admin\Services\AdminDashboardService;
use App\Modules\Admin\Services\AdminMessageService;
use App\Modules\Admin\Services\AdminFeedbackService;
use App\Modules\Admin\Controllers\AdminController;
use App\Modules\Teacher\Services\TeacherTermService;
use App\Modules\Teacher\Services\TeacherGradeService;
use App\Modules\Teacher\Controllers\TeacherController;
use App\Modules\Examiner\Services\ExaminerService;
use App\Modules\Examiner\Controllers\ExaminerController;
use App\Modules\Student\Services\StudentService;
use App\Modules\Student\Controllers\StudentController;

use App\Http\Middlewares\CheckAccessMiddleware;
use App\Http\Middlewares\JwtAuthMiddleware;

return function (): ContainerInterface {
    $builder = new ContainerBuilder();

    $builder->addDefinitions([
        CacheInterface::class => static fn () => CacheFactory::create(),
        SmsServiceInterface::class => static fn () => SmsFactory::create(),
        MailServiceInterface::class => static fn () => MailFactory::create(),

        JwtService::class => autowire(),
        OtpService::class => autowire(),
        RefreshTokenService::class => autowire(),
        CheckAccessMiddleware::class => autowire(),
        JwtAuthMiddleware::class => autowire(),

        UserRepository::class => autowire(),
        RefreshTokenRepository::class => autowire(),
        ClassRepository::class => autowire(),
        TermRepository::class => autowire(),
        GradeRepository::class => autowire(),
        ExamRepository::class => autowire(),
        MessageRepository::class => autowire(),
        FeedbackRepository::class => autowire(),

        AuthService::class => autowire(),
        AuthController::class => autowire(),
        AdminUserService::class => autowire(),
        AdminClassService::class => autowire(),
        AdminDashboardService::class => autowire(),
        AdminMessageService::class => autowire(),
        AdminFeedbackService::class => autowire(),
        AdminController::class => autowire(),
        TeacherTermService::class => autowire(),
        TeacherGradeService::class => autowire(),
        TeacherController::class => autowire(),
        ExaminerService::class => autowire(),
        ExaminerController::class => autowire(),
        StudentService::class => autowire(),
        StudentController::class => autowire(),

        GreeterServiceInterface::class => autowire(GreeterService::class),
        GreeterService::class => autowire(),
        NoteServiceInterface::class => autowire(NoteService::class),
        NoteRepositoryInterface::class => autowire(NoteRepository::class),
        NoteService::class => autowire(),
        NoteRepository::class => autowire(),
    ]);

    return $builder->build();
};
