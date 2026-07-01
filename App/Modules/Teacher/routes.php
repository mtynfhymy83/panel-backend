<?php

declare(strict_types=1);

use App\Http\Routers\Router;
use App\Modules\Teacher\Controllers\TeacherController;

return static function (Router $router): void {
    $teacher = ['teacher'];

    $router->get('api', '/teacher/dashboard', TeacherController::class, 'dashboard', $teacher);
    $router->get('api', '/teacher/classes', TeacherController::class, 'listClasses', $teacher);
    $router->post('api', '/teacher/classes/{class}/terms', TeacherController::class, 'createTerm', $teacher);
    $router->patch('api', '/teacher/terms/{term}/end', TeacherController::class, 'endTerm', $teacher);
    $router->get('api', '/teacher/classes/{class}', TeacherController::class, 'showClass', $teacher);
    $router->get('api', '/teacher/classes/{class}/students', TeacherController::class, 'listStudents', $teacher);
    $router->get('api', '/teacher/classes/{class}/exams', TeacherController::class, 'listExams', $teacher);
    $router->post('api', '/teacher/grades', TeacherController::class, 'submitGrade', $teacher);
    $router->get('api', '/teacher/feedbacks', TeacherController::class, 'listFeedbacks', $teacher);
    $router->patch('api', '/teacher/feedbacks/mark-seen', TeacherController::class, 'markFeedbacksSeen', $teacher);
};
