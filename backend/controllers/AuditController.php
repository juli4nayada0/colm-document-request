<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Audit Log Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\AuditLog;
use Colm\Services\AuditService;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;

class AuditController {
    private array $currentUser;
    private AuditLog $auditModel;
    private AuditService $auditService;

    public function __construct() {
        $auth = new AuthMiddleware();
        $this->currentUser = $auth->handle();
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Admin']);

        $this->auditModel = new AuditLog();
        $this->auditService = new AuditService();
    }

    /**
     * GET /api/v1/audit-logs
     */
    public function index(): void {
        $page = (int)($_GET['page'] ?? 1);
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 30)));
        $action = !empty($_GET['action']) ? SecurityHelper::sanitizeString($_GET['action']) : null;
        $entity = !empty($_GET['entity']) ? SecurityHelper::sanitizeString($_GET['entity']) : null;
        $userId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
        $startDate = !empty($_GET['start_date']) ? SecurityHelper::sanitizeString($_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? SecurityHelper::sanitizeString($_GET['end_date']) : null;
        $search = !empty($_GET['search']) ? SecurityHelper::sanitizeString($_GET['search']) : null;

        $result = $this->auditModel->getList($page, $limit, $action, $entity, $userId, $startDate, $endDate, $search);

        // Audit the access to audit logs
        $this->auditService->log(
            $this->currentUser['user_id'],
            'AUDIT_LOGS_VIEWED',
            'audit_logs',
            'PAGE_' . $page
        );

        ResponseHelper::success($result['records'], 'Audit logs retrieved.', 200, [
            'total' => $result['total'],
            'page'  => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages']
        ]);
    }
}
