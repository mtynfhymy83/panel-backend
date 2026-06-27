<?php

declare(strict_types=1);

use App\Http\Routers\Router;
use App\Http\Controllers\HelloController;
use App\Http\Controllers\NoteController;

/**
 * Route signature:
 *   $router->get($version, $path, $controllerClass, $method, $access = false, $inaccess = false)
 *
 * $access:
 *   false            -> public
 *   'auth' / 'all'   -> any authenticated role
 *   'owners'         -> ['support', 'admin']
 *   ['admin', ...]   -> explicit role list
 */
return static function (Router $router): void {

    // Public sample endpoints
    $router->get('v1', '/hello', HelloController::class, 'index');
    $router->get('v1', '/hello/{name}', HelloController::class, 'show');
    $router->post('v1', '/hello', HelloController::class, 'store');

    // DB-backed CRUD sample (needs the notes table — run scripts/migrate.php)
    $router->get('v1', '/notes', NoteController::class, 'index');
    $router->get('v1', '/notes/{id}', NoteController::class, 'show');
    $router->post('v1', '/notes', NoteController::class, 'store');
    $router->delete('v1', '/notes/{id}', NoteController::class, 'destroy');

    // Example of a protected route (requires a valid JWT with an allowed role):
    // $router->get('v1', '/admin/ping', HelloController::class, 'index', ['admin']);

    // Grouping with a shared prefix:
    // $router->prefix('/users', function (Router $r) {
    //     $r->get('v1', '/{id}', UserController::class, 'show');
    //     $r->post('v1', '/', UserController::class, 'store', 'auth');
    // });
};
