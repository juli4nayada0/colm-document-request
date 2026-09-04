<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Security Helper: CSRF, Sanitization, IP Detection, Login Rate Limiting
 */

declare(strict_types=1);

namespace Colm\Helpers;

class SecurityHelper {
    /**
     * Start session if needed and headers not sent
     */
    private static function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                @session_start();
            }
        }
    }

    /**
     * Get Client IP Address accurately
     */
    public static function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get Client User Agent
     */
    public static function getUserAgent(): string {
        return mb_substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown User-Agent', 0, 500);
    }

    /**
     * Sanitize string for output or DB storage
     */
    public static function sanitizeString(?string $input): string {
        if ($input === null) {
            return '';
        }
        return htmlspecialchars(trim($input), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Generate or retrieve CSRF token from active session
     */
    public static function getCsrfToken(): string {
        self::ensureSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['csrf_token'];
        }
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate submitted CSRF token
     */
    public static function validateCsrfToken(?string $token): bool {
        self::ensureSession();
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Check and record login rate limiting
     */
    public static function checkRateLimit(string $identifier, int $maxAttempts = 5, int $decaySeconds = 900): bool {
        self::ensureSession();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => time()];

        if (time() - $attempts['first_attempt'] > $decaySeconds) {
            $attempts = ['count' => 0, 'first_attempt' => time()];
        }

        if ($attempts['count'] >= $maxAttempts) {
            return false;
        }

        return true;
    }

    /**
     * Increment rate limiting attempts
     */
    public static function incrementRateLimit(string $identifier): void {
        self::ensureSession();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $attempts = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => time()];

        if (time() - $attempts['first_attempt'] > 900) {
            $attempts = ['count' => 1, 'first_attempt' => time()];
        } else {
            $attempts['count']++;
        }

        $_SESSION[$key] = $attempts;
    }

    /**
     * Reset rate limit on successful authentication
     */
    public static function resetRateLimit(string $identifier): void {
        self::ensureSession();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $key = 'rate_limit_' . md5($identifier);
            unset($_SESSION[$key]);
        }
    }
}
