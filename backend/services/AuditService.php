<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Audit Logging Service
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Models\AuditLog;
use Colm\Helpers\SecurityHelper;

class AuditService {
    private AuditLog $auditModel;

    public function __construct() {
        $this->auditModel = new AuditLog();
    }

    /**
     * Log an audit event
     */
    public function log(
        ?int $userId,
        string $action,
        string $entityAffected,
        string $entityId,
        mixed $prevState = null,
        mixed $newState = null
    ): int {
        $ip = SecurityHelper::getClientIp();
        $userAgent = SecurityHelper::getUserAgent();

        return $this->auditModel->log(
            $userId,
            $action,
            $entityAffected,
            $entityId,
            $prevState,
            $newState,
            $ip,
            $userAgent
        );
    }
}
