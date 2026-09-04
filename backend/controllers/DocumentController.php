<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Document Catalog Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\Document;
use Colm\Models\DocumentRequirement;
use Colm\Services\AuditService;
use Colm\Validators\DocumentValidator;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;

class DocumentController {
    private Document $documentModel;
    private DocumentRequirement $requirementModel;
    private AuditService $auditService;
    private DocumentValidator $validator;

    public function __construct() {
        $this->documentModel = new Document();
        $this->requirementModel = new DocumentRequirement();
        $this->auditService = new AuditService();
        $this->validator = new DocumentValidator();
    }

    /**
     * GET /api/v1/documents
     */
    public function index(): void {
        $activeOnly = !isset($_GET['all']) || $_GET['all'] !== '1';
        $documents = $this->documentModel->getList($activeOnly);

        // Attach requirement counts / summary
        foreach ($documents as &$doc) {
            $reqs = $this->requirementModel->getByDocumentId((int)$doc['document_id'], true);
            $doc['requirements'] = $reqs;
        }

        ResponseHelper::success($documents, 'Document catalog retrieved.');
    }

    /**
     * GET /api/v1/documents/{id}
     */
    public function show(int $id): void {
        $doc = $this->documentModel->findById($id);
        if (!$doc) {
            ResponseHelper::error("Document #{$id} not found.", 404);
        }

        $reqs = $this->requirementModel->getByDocumentId($id, false);
        $doc['requirements'] = $reqs;

        ResponseHelper::success($doc, 'Document details retrieved.');
    }

    /**
     * POST /api/v1/documents
     */
    public function store(): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateSave($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $code = strtoupper(SecurityHelper::sanitizeString($data['document_code']));
        if ($this->documentModel->findByCode($code)) {
            ResponseHelper::error("Document code '{$code}' already exists.", 409);
        }

        $data['document_code'] = $code;
        $docId = $this->documentModel->create($data);

        $this->auditService->log(
            $user['user_id'],
            'DOCUMENT_TYPE_CREATED',
            'documents',
            (string)$docId,
            null,
            ['code' => $code, 'name' => $data['document_name']]
        );

        ResponseHelper::success(['document_id' => $docId], 'Document type created successfully.', 201);
    }

    /**
     * PUT /api/v1/documents/{id}
     */
    public function update(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $doc = $this->documentModel->findById($id);
        if (!$doc) {
            ResponseHelper::error("Document #{$id} not found.", 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateSave($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $code = strtoupper(SecurityHelper::sanitizeString($data['document_code']));
        $existing = $this->documentModel->findByCode($code);
        if ($existing && (int)$existing['document_id'] !== $id) {
            ResponseHelper::error("Document code '{$code}' is used by another document.", 409);
        }

        $data['document_code'] = $code;
        $this->documentModel->update($id, $data);

        $this->auditService->log(
            $user['user_id'],
            'DOCUMENT_TYPE_UPDATED',
            'documents',
            (string)$id,
            $doc,
            $data
        );

        ResponseHelper::success(null, 'Document details updated.');
    }

    /**
     * PATCH /api/v1/documents/{id}/status
     */
    public function toggleStatus(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $doc = $this->documentModel->findById($id);
        if (!$doc) {
            ResponseHelper::error("Document #{$id} not found.", 404);
        }

        $newStatus = (int)$doc['is_active'] === 1 ? 0 : 1;
        $this->documentModel->update($id, ['is_active' => $newStatus]);

        $this->auditService->log(
            $user['user_id'],
            $newStatus === 1 ? 'DOCUMENT_TYPE_ACTIVATED' : 'DOCUMENT_TYPE_DEACTIVATED',
            'documents',
            (string)$id,
            ['is_active' => $doc['is_active']],
            ['is_active' => $newStatus]
        );

        ResponseHelper::success(['is_active' => $newStatus], $newStatus === 1 ? 'Document activated.' : 'Document deactivated.');
    }
}
