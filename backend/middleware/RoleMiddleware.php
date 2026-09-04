<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Role-Based Access Control (RBAC) Middleware
 */

declare(strict_types=1);

namespace Colm\Middleware;

use Colm\Helpers\ResponseHelper;

class RoleMiddleware {
    /**
     * Enforce that the authenticated user possesses one of the authorized roles
     */
    public static function authorize(array $user, array $allowedRoles): void {
        if (!in_array($user['role'], $allowedRoles, true)) {
            ResponseHelper::error(
                "Access Denied. Your role ({$user['role']}) does not have permission to access this resource.",
                403,
                null,
                'FORBIDDEN'
            );
        }
    }

    /**
     * Enforce student isolation: A student can only view/modify their own requests
     */
    public static function authorizeStudentOwnership(array $user, int $ownerStudentId): void {
        if ($user['role'] === 'Student') {
            if (empty($user['student_id']) || (int)$user['student_id'] !== $ownerStudentId) {
                ResponseHelper::error('Access Denied. You can only access your own document requests.', 403, null, 'UNAUTHORIZED_ACCESS');
            }
        }
    }
}
