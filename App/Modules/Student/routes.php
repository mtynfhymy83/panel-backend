<?php

declare(strict_types=1);

use App\Http\Routers\Router;
use App\Modules\Student\Controllers\StudentController;

return static function (Router $router): void {
    $student = ['student'];

    $router->get('api', '/student/dashboard', StudentController::class, 'dashboard', $student);
    $router->get('api', '/student/messages', StudentController::class, 'listMessages', $student);
    $router->post('api', '/student/messages', StudentController::class, 'createMessage', $student);
    $router->patch('api', '/student/messages/mark-seen', StudentController::class, 'markMessagesSeen', $student);
};
