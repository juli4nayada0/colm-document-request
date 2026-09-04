<?php
/**
 * COLM Registrar Document Request and Tracking System
 * CSRF Protection Middleware
 */

declare(strict_types=1);

namespace Colm\Middleware;

use Colm\Helpers\SecurityHelper;
use Colm\Helpers\ResponseHelper;

class CsrfMiddleware {
    /**
     * Verify CSRF token for mutating state HTTP methods (POST, PUT, PATCH, DELETE)
     */
    public static function verify(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            self::ensureConfiguredSession();

            // Check HTTP Header X-CSRF-Token or POST body
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;

            // If payload was JSON, parse if token is inside
            if (empty($token)) {
                $rawInput = file_get_contents('php://input');
                if (!empty($rawInput)) {
                    $json = json_decode($rawInput, true);
                    $token = $json['csrf_token'] ?? null;
                }
            }

            // Exclude public login/activation if session not yet established
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (str_contains($uri, '/auth/login') || str_contains($uri, '/track')) {
                return;
            }

            if (!SecurityHelper::validateCsrfToken($token)) {
                ResponseHelper::error('CSRF token validation failed or session expired. Please refresh the page.', 403, null, 'INVALID_CSRF_TOKEN');
            }
        }
    }

    private static function ensureConfiguredSession(): void {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }

        $config = require dirname(__DIR__) . '/config/config.php';
        $session = $config['session'];
        session_set_cookie_params([
            'lifetime' => $session['lifetime'],
            'path'     => '/',
            'domain'   => '',
            'secure'   => $session['secure'],
            'httponly' => $session['httponly'],
            'samesite' => $session['samesite']
        ]);
        session_name($session['name']);
        session_start();
    }
}
