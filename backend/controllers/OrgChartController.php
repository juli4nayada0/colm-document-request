<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Organizational Chart Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\OrgChart;
use Colm\Services\AuditService;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;

class OrgChartController {
    private OrgChart $model;
    private AuditService $auditService;

    public function __construct() {
        $this->model        = new OrgChart();
        $this->auditService = new AuditService();
    }

    /**
     * GET /api/v1/org-chart
     * Public: any authenticated user may read
     */
    public function index(): void {
        $auth = new AuthMiddleware();
        $auth->handle(); // must be logged in; no role restriction
        $members = $this->model->getAll();
        ResponseHelper::success($members, 'Org chart retrieved.');
    }

    /**
     * POST /api/v1/org-chart
     * Registrar / Admin only
     */
    public function store(): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $errors = $this->validate($data);
        if ($errors) {
            ResponseHelper::error('Validation failed.', 422, $errors);
        }

        $data['full_name']      = SecurityHelper::sanitizeString($data['full_name']);
        $data['position_title'] = SecurityHelper::sanitizeString($data['position_title']);
        $data['department']     = isset($data['department']) ? SecurityHelper::sanitizeString($data['department']) : null;

        $id = $this->model->create($data);

        $this->auditService->log(
            $user['user_id'], 'ORG_CHART_MEMBER_CREATED',
            'org_chart_members', (string)$id, null,
            ['full_name' => $data['full_name'], 'position_title' => $data['position_title']]
        );

        ResponseHelper::success(['member_id' => $id], 'Member added to org chart.', 201);
    }

    /**
     * PUT /api/v1/org-chart/{id}
     * Registrar / Admin only
     */
    public function update(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $member = $this->model->findById($id);
        if (!$member) {
            ResponseHelper::error("Member #{$id} not found.", 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $errors = $this->validate($data);
        if ($errors) {
            ResponseHelper::error('Validation failed.', 422, $errors);
        }

        $data['full_name']      = SecurityHelper::sanitizeString($data['full_name']);
        $data['position_title'] = SecurityHelper::sanitizeString($data['position_title']);
        $data['department']     = isset($data['department']) ? SecurityHelper::sanitizeString($data['department']) : null;

        $this->model->update($id, $data);

        $this->auditService->log(
            $user['user_id'], 'ORG_CHART_MEMBER_UPDATED',
            'org_chart_members', (string)$id, $member, $data
        );

        ResponseHelper::success(null, 'Member updated.');
    }

    /**
     * DELETE /api/v1/org-chart/{id}
     * Registrar / Admin only
     */
    public function destroy(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $member = $this->model->findById($id);
        if (!$member) {
            ResponseHelper::error("Member #{$id} not found.", 404);
        }

        $this->model->delete($id);

        $this->auditService->log(
            $user['user_id'], 'ORG_CHART_MEMBER_DELETED',
            'org_chart_members', (string)$id, $member, null
        );

        ResponseHelper::success(null, 'Member removed from org chart.');
    }

    // ------------------------------------------------------------------ helpers
    private function validate(array $data): array {
        $errors = [];
        if (empty($data['full_name']))      $errors['full_name']      = 'Full name is required.';
        if (empty($data['position_title'])) $errors['position_title'] = 'Position title is required.';

        $validLevels = ['vp', 'registrar', 'admin_assistant', 'coordinator', 'staff'];
        if (!empty($data['role_level']) && !in_array($data['role_level'], $validLevels, true)) {
            $errors['role_level'] = 'Invalid role level.';
        }
        return $errors;
    }
}
