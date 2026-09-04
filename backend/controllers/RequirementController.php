<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Requirement Management Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\DocumentRequirement;
use Colm\Services\AuditService;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;

class RequirementController {
    private DocumentRequirement $requirementModel;
    private AuditService $auditService;

    public function __construct() {
        $this->requirementModel = new DocumentRequirement();
        $this->auditService = new AuditService();
    }

    /**
     * GET /api/v1/documents/{id}/requirements
     */
    public function getByDocument(int $documentId): void {
        $reqs = $this->requirementModel->getByDocumentId($documentId, false);
        ResponseHelper::success($reqs, 'Requirements retrieved.');
    }

    /**
     * POST /api/v1/documents/{id}/requirements
     */
    public function store(int $documentId): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['requirement_name'])) {
            ResponseHelper::error('Requirement name is required.', 422);
        }

        $data['document_id'] = $documentId;
        $reqId = $this->requirementModel->create($data);

        $this->auditService->log(
            $user['user_id'],
            'REQUIREMENT_CREATED',
            'document_requirements',
            (string)$reqId,
            null,
            ['document_id' => $documentId, 'name' => $data['requirement_name']]
        );

        ResponseHelper::success(['requirement_id' => $reqId], 'Requirement created.', 201);
    }

    /**
     * PUT /api/v1/requirements/{id}
     */
    public function update(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $req = $this->requirementModel->findById($id);
        if (!$req) {
            ResponseHelper::error("Requirement #{$id} not found.", 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $this->requirementModel->update($id, $data);

        $this->auditService->log(
            $user['user_id'],
            'REQUIREMENT_UPDATED',
            'document_requirements',
            (string)$id,
            $req,
            $data
        );

        ResponseHelper::success(null, 'Requirement updated.');
    }

    /**
     * PATCH /api/v1/requirements/{id}/status
     */
    public function toggleStatus(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $req = $this->requirementModel->findById($id);
        if (!$req) {
            ResponseHelper::error("Requirement #{$id} not found.", 404);
        }

        $newStatus = (int)$req['is_active'] === 1 ? 0 : 1;
        $this->requirementModel->update($id, ['is_active' => $newStatus]);

        $this->auditService->log(
            $user['user_id'],
            $newStatus === 1 ? 'REQUIREMENT_ACTIVATED' : 'REQUIREMENT_DEACTIVATED',
            'document_requirements',
            (string)$id,
            ['is_active' => $req['is_active']],
            ['is_active' => $newStatus]
        );

        ResponseHelper::success(['is_active' => $newStatus], $newStatus === 1 ? 'Requirement activated.' : 'Requirement deactivated.');
    }
}
