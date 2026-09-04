<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Authentication Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Services\AuthService;
use Colm\Validators\UserValidator;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;

class AuthController {
    private AuthService $authService;
    private UserValidator $validator;

    public function __construct() {
        $this->authService = new AuthService();
        $this->validator = new UserValidator();
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateLogin($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $result = $this->authService->authenticate(
            SecurityHelper::sanitizeString($data['username'] ?? ''),
            (string)($data['password'] ?? '')
        );

        if (!$result['success']) {
            ResponseHelper::error($result['message'], $result['code'] ?? 401);
        }

        ResponseHelper::success($result['user'], $result['message']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(): void {
        $currentUser = $this->authService->getCurrentUser();
        if (!$currentUser) {
            ResponseHelper::error('Not authenticated.', 401);
        }
        ResponseHelper::success($currentUser, 'Authenticated user profile.');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): void {
        $this->authService->logout();
        ResponseHelper::success(null, 'Successfully logged out.');
    }

    /**
     * GET /api/v1/auth/csrf
     */
    public function csrf(): void {
        $token = SecurityHelper::getCsrfToken();
        ResponseHelper::success(['csrf_token' => $token], 'CSRF Token generated.');
    }
}
