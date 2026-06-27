<?php

declare(strict_types=1);

use App\Http\Routers\Router;
use App\Modules\Examiner\Controllers\ExaminerController;

return static function (Router $router): void {
    $examiner = ['examiner'];

    $router->get('api', '/examiner/dashboard', ExaminerController::class, 'dashboard', $examiner);
    $router->get('api', '/examiner/classes/{class}/exam', ExaminerController::class, 'getExam', $examiner);
    $router->post('api', '/examiner/classes/{class}/exam', ExaminerController::class, 'submitExam', $examiner);
};
