<?php

declare(strict_types=1);

use App\Http\Routers\Router;

/**
 * Loads all module route files from App/Modules/{Name}/routes.php
 */
return static function (Router $router): void {
    $modulesDir = dirname(__DIR__, 2) . '/Modules';
    if (!is_dir($modulesDir)) {
        return;
    }

    foreach (glob($modulesDir . '/*/routes.php') ?: [] as $routeFile) {
        $callback = require $routeFile;
        if (is_callable($callback)) {
            $callback($router);
        }
    }
};
