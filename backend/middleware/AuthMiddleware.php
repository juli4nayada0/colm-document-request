<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Authentication Middleware
 */

declare(strict_types=1);

namespace Colm\Middleware;

use Colm\Services\AuthService;
use Colm\Helpers\ResponseHelper;

class AuthMiddleware {
    private AuthService $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }

    /**
     * Ensure request is authenticated. Returns user array or halts with 401 error.
     */
    public function handle(): array {
        $user = $this->authService->getCurrentUser();
        if (!$user) {
            ResponseHelper::error('Unauthenticated. Please log in to proceed.', 401, null, 'UNAUTHENTICATED');
        }
        return $user;
    }
}
