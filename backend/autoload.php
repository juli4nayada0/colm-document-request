<?php
/**
 * COLM Registrar Document Request and Tracking System
 * PSR-4 Compatible Class Autoloader
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

spl_autoload_register(function (string $class) {
    $prefix = 'Colm\\';
    $baseDir = APP_ROOT . '/backend/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // Directory case sensitivity normalization
    $file = str_replace(
        ['backend/Config/', 'backend/Controllers/', 'backend/Middleware/', 'backend/Models/', 'backend/Services/', 'backend/Validators/', 'backend/Helpers/'],
        ['backend/config/', 'backend/controllers/', 'backend/middleware/', 'backend/models/', 'backend/services/', 'backend/validators/', 'backend/helpers/'],
        $file
    );

    if (file_exists($file)) {
        require_once $file;
    }
});
