<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Authentication & Session Management Service
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Models\User;
use Colm\Models\Student;
use Colm\Helpers\SecurityHelper;

class AuthService {
    private User $userModel;
    private Student $studentModel;
    private AuditService $auditService;

    public function __construct() {
        $this->userModel = new User();
        $this->studentModel = new Student();
        $this->auditService = new AuditService();
        $this->initSession();
    }

    /**
     * Start secure PHP session if not already active
     */
    public function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $config = require dirname(__DIR__) . '/config/config.php';
            $sess = $config['session'];

            if (!headers_sent()) {
                session_set_cookie_params([
                    'lifetime' => $sess['lifetime'],
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $sess['secure'],
                    'httponly' => $sess['httponly'],
                    'samesite' => $sess['samesite']
                ]);
                session_name($sess['name']);
                session_start();
            } else {
                @session_start();
            }
        }
    }

    /**
     * Authenticate user credentials
     */
    public function authenticate(string $username, string $password): array {
        $ip = SecurityHelper::getClientIp();
        $userAgent = SecurityHelper::getUserAgent();

        // Check login throttling / rate limiting
        if (!SecurityHelper::checkRateLimit($username . '_' . $ip, 5, 900)) {
            $this->auditService->log(null, 'LOGIN_LOCKED', 'users', $username, null, ['ip' => $ip, 'reason' => 'Rate limit exceeded']);
            return [
                'success' => false,
                'message' => 'Too many failed login attempts. Please try again after 15 minutes.',
                'code'    => 429
            ];
        }

        $user = $this->userModel->findByUsername(trim($username));

        // If user not found, perform dummy hash verification to prevent timing attacks
        if (!$user) {
            password_verify($password, '$2y$10$4TLgSOE3Y4U7vbnPIj0T2e6lcnV77X5C9Pvlf980bkUDdQVu0FYEi');
            SecurityHelper::incrementRateLimit($username . '_' . $ip);
            $this->auditService->log(null, 'LOGIN_FAILED', 'users', $username, null, ['ip' => $ip, 'reason' => 'User not found']);
            return [
                'success' => false,
                'message' => 'Invalid username or password.',
                'code'    => 401
            ];
        }

        if ($user['role'] === 'Student' && !preg_match('/^\d{4}-\d{5}$/', trim($username))) {
            SecurityHelper::incrementRateLimit($username . '_' . $ip);
            return [
                'success' => false,
                'message' => 'Student Number must follow the format YYYY-NNNNN.',
                'code'    => 401
            ];
        }

        // Check if account is active
        if ((int)$user['is_active'] !== 1) {
            $this->auditService->log((int)$user['user_id'], 'LOGIN_BLOCKED', 'users', (string)$user['user_id'], null, ['reason' => 'Account deactivated']);
            return [
                'success' => false,
                'message' => 'Your account is currently inactive or deactivated. Please contact the Office of the Registrar.',
                'code'    => 403
            ];
        }

        // Verify password hash
        if (!password_verify($password, $user['password_hash'])) {
            SecurityHelper::incrementRateLimit($username . '_' . $ip);
            $this->auditService->log((int)$user['user_id'], 'LOGIN_FAILED', 'users', (string)$user['user_id'], null, ['ip' => $ip, 'reason' => 'Incorrect password']);
            return [
                'success' => false,
                'message' => 'Invalid username or password.',
                'code'    => 401
            ];
        }

        // Rehash password if algorithm options updated
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $this->userModel->update((int)$user['user_id'], ['password_hash' => $newHash]);
        }

        // Authentication Successful: Reset rate limiter and regenerate session ID if active
        SecurityHelper::resetRateLimit($username . '_' . $ip);
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        // Fetch associated student profile if role is Student
        $studentProfile = null;
        if ($user['role'] === 'Student') {
            $studentProfile = $this->studentModel->findByUserId((int)$user['user_id']);
        }

        // Populate session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['user_id']       = (int)$user['user_id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['student_id']    = $studentProfile['student_id'] ?? null;
            $_SESSION['student_number']= $studentProfile['student_number'] ?? null;
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address']    = $ip;
        }

        // Update last login timestamp in DB
        $this->userModel->updateLastLogin((int)$user['user_id']);

        // Log successful login
        $this->auditService->log((int)$user['user_id'], 'LOGIN_SUCCESS', 'users', (string)$user['user_id'], null, ['role' => $user['role']]);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user'    => [
                'user_id'        => (int)$user['user_id'],
                'username'       => $user['username'],
                'role'           => $user['role'],
                'student_id'     => $studentProfile['student_id'] ?? null,
                'student_number' => $studentProfile['student_number'] ?? null,
                'full_name'      => $studentProfile ? trim("{$studentProfile['first_name']} {$studentProfile['last_name']}") : $user['username'],
                'csrf_token'     => SecurityHelper::getCsrfToken()
            ]
        ];
    }

    /**
     * Get currently authenticated user data
     */
    public function getCurrentUser(): ?array {
        $this->initSession();

        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['user_id'])) {
            return null;
        }

        // Verify session expiration (4 hours inactivity)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 14400)) {
            $this->logout();
            return null;
        }
        $_SESSION['last_activity'] = time();

        $user = $this->userModel->findById((int)$_SESSION['user_id']);
        if (!$user || (int)$user['is_active'] !== 1) {
            $this->logout();
            return null;
        }

        $studentProfile = null;
        if ($user['role'] === 'Student') {
            $studentProfile = $this->studentModel->findByUserId((int)$user['user_id']);
        }

        return [
            'user_id'        => (int)$user['user_id'],
            'username'       => $user['username'],
            'role'           => $user['role'],
            'student_id'     => $studentProfile['student_id'] ?? null,
            'student_number' => $studentProfile['student_number'] ?? null,
            'full_name'      => $studentProfile ? trim("{$studentProfile['first_name']} {$studentProfile['last_name']}") : $user['username'],
            'email'          => $studentProfile['email'] ?? null,
            'program'        => $studentProfile['program'] ?? null,
            'year_level'     => $studentProfile['year_level'] ?? null,
            'csrf_token'     => SecurityHelper::getCsrfToken()
        ];
    }

    /**
     * Destroy user session (Logout)
     */
    public function logout(): void {
        $this->initSession();

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
            $this->auditService->log((int)$_SESSION['user_id'], 'LOGOUT', 'users', (string)$_SESSION['user_id']);
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
