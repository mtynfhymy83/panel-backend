<?php

declare(strict_types=1);

use App\Http\Routers\Router;
use App\Modules\Admin\Controllers\AdminController;

return static function (Router $router): void {
    $admin = ['admin'];

    $router->get('api', '/admin/dashboard', AdminController::class, 'dashboard', $admin);
    $router->get('api', '/admin/users', AdminController::class, 'listUsers', $admin);
    $router->patch('api', '/admin/users/{user}/roles', AdminController::class, 'updateUserRoles', $admin);
    $router->delete('api', '/admin/users/{user}', AdminController::class, 'deleteUser', $admin);
    $router->get('api', '/admin/classes', AdminController::class, 'listClasses', $admin);
    $router->post('api', '/admin/classes', AdminController::class, 'createClass', $admin);
    $router->patch('api', '/admin/classes/{class}', AdminController::class, 'updateClass', $admin);
    $router->delete('api', '/admin/classes/{class}', AdminController::class, 'deleteClass', $admin);
    $router->post('api', '/admin/classes/{class}/members', AdminController::class, 'addMember', $admin);
    $router->delete('api', '/admin/classes/{class}/members/{user}', AdminController::class, 'removeMember', $admin);
    $router->get('api', '/admin/terms', AdminController::class, 'listTerms', $admin);
    $router->get('api', '/admin/exams', AdminController::class, 'listExams', $admin);
    $router->get('api', '/admin/progress', AdminController::class, 'progress', $admin);
    $router->get('api', '/admin/messages', AdminController::class, 'listMessages', $admin);
    $router->patch('api', '/admin/messages/{message}/review', AdminController::class, 'reviewMessage', $admin);
    $router->patch('api', '/admin/messages/{message}/reply', AdminController::class, 'replyMessage', $admin);
    $router->get('api', '/admin/teacher-feedbacks', AdminController::class, 'listFeedbacks', $admin);
    $router->post('api', '/admin/teacher-feedbacks', AdminController::class, 'createFeedback', $admin);
};
