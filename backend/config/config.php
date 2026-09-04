<?php
/**
 * COLM Registrar Document Request and Tracking System
 * System Configuration File
 */

declare(strict_types=1);

// Prevent direct execution outside of application flow
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

// Load Environment Variables if .env exists, or use default local constants
if (!function_exists('loadEnv')) {
    function loadEnv(string $envFile): void {
        if (!file_exists($envFile)) {
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2) + [1 => ''];
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

loadEnv(APP_ROOT . '/.env');

date_default_timezone_set('Asia/Manila');

return [
    'app' => [
        'name' => getenv('APP_NAME') ?: 'COLM Registrar Document Request and Tracking System',
        'env' => getenv('APP_ENV') ?: 'development',
        'url' => getenv('APP_URL') ?: 'http://localhost/colmregistrar',
        'timezone' => 'Asia/Manila',
        'institution' => 'College of Our Lady of Mercy of Pulilan Foundation, Inc.',
        'campus' => 'Longos, Pulilan, Bulacan',
        'version' => '1.0.0',
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'colm_rdrts_db',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => getenv('SESSION_NAME') ?: 'COLM_REGISTRAR_SESSID',
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 3600 * 4), // 4 hours
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    'storage' => [
        'private_dir' => APP_ROOT . '/backend/storage/private',
        'requirements_dir' => APP_ROOT . '/backend/storage/private/requirements',
        'payments_dir' => APP_ROOT . '/backend/storage/private/payments',
        'completed_docs_dir' => APP_ROOT . '/backend/storage/private/completed_docs',
        'max_file_size_mb' => (int)(getenv('UPLOAD_MAX_SIZE') ?: 10),
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/pjpeg',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ],
    ],
    'security' => [
        'max_login_attempts' => 5,
        'lockout_time_seconds' => 900, // 15 minutes
    ]
];
