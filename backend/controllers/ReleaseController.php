<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Document Release Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\DocumentRelease;
use Colm\Models\DocumentRequest;
use Colm\Services\AuditService;
use Colm\Services\RequestWorkflowService;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;
use Colm\Config\Database;

class ReleaseController {
    private DocumentRelease $releaseModel;
    private DocumentRequest $requestModel;
    private AuditService $auditService;
    private RequestWorkflowService $workflowService;

    public function __construct() {
        $this->releaseModel    = new DocumentRelease();
        $this->requestModel    = new DocumentRequest();
        $this->auditService    = new AuditService();
        $this->workflowService = new RequestWorkflowService();
    }

    /**
     * GET /api/v1/requests/{id}/release
     */
    public function show(int $requestId): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();

        $request = $this->requestModel->findById($requestId);
        if (!$request) {
            ResponseHelper::error("Request #{$requestId} not found.", 404);
        }

        RoleMiddleware::authorizeStudentOwnership($user, (int)$request['student_id']);

        $release = $this->releaseModel->findByRequestId($requestId);
        ResponseHelper::success($release, 'Release record retrieved.');
    }

    /**
     * POST /api/v1/requests/{id}/release
     * Record document release and advance status to RELEASED
     */
    public function store(int $requestId): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Personnel']);

        $request = $this->requestModel->findById($requestId);
        if (!$request) {
            ResponseHelper::error("Request #{$requestId} not found.", 404);
        }

        if ($request['current_status'] !== 'READY FOR RELEASE') {
            ResponseHelper::error('Only requests marked READY FOR RELEASE can be released.', 422);
        }
        if ((float)$request['amount_due'] > 0 && $request['payment_status'] !== 'Paid') {
            ResponseHelper::error('Payment must be confirmed before releasing the document.', 422);
        }
        if ($this->releaseModel->findByRequestId($requestId)) {
            ResponseHelper::error('This request already has a release record.', 409);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        $recipientType = $data['recipient_type'] ?? 'Student';
        if (!in_array($recipientType, ['Student', 'Representative'], true)) {
            ResponseHelper::error('Invalid recipient type.', 422);
        }
        $releaseMethod = $data['release_method'] ?? $request['release_method'];
        if (!in_array($releaseMethod, ['Personal Claiming', 'Authorized Representative', 'Digital Copy', 'Other Approved Delivery'], true)) {
            ResponseHelper::error('Invalid release method.', 422);
        }
        if ($releaseMethod !== $request['release_method']) {
            ResponseHelper::error('Release method must match the student request.', 422);
        }
        if ($releaseMethod === 'Digital Copy' && (int)$request['is_digital_allowed'] !== 1) {
            ResponseHelper::error('Digital release is not allowed for this document.', 422);
        }
        $repName = !empty($data['representative_name']) ? SecurityHelper::sanitizeString($data['representative_name']) : null;
        $repRel = !empty($data['representative_relationship']) ? SecurityHelper::sanitizeString($data['representative_relationship']) : null;
        $authRef = !empty($data['authorization_reference']) ? SecurityHelper::sanitizeString($data['authorization_reference']) : null;
        $idVerified = (int)($data['identification_verified'] ?? 1);
        if ($idVerified !== 1) {
            ResponseHelper::error('Identity verification is required before release.', 422);
        }
        $remarks = !empty($data['remarks']) ? SecurityHelper::sanitizeString($data['remarks']) : 'Document released successfully.';

        if ($recipientType === 'Representative' && (empty($repName) || empty($repRel) || empty($authRef))) {
            ResponseHelper::error('Representative name, relationship, and authorization reference are required.', 422);
        }

        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $releaseId = $this->releaseModel->create([
                'request_id'                  => $requestId,
                'released_by'                 => $user['user_id'],
                'release_method'              => $releaseMethod,
                'recipient_type'              => $recipientType,
                'recipient_name'              => $recipientType === 'Student'
                    ? trim($request['first_name'] . ' ' . $request['last_name'])
                    : $repName,
                'representative_name'         => $repName,
                'representative_relationship' => $repRel,
                'authorization_reference'     => $authRef,
                'identification_verified'     => $idVerified,
                'claiming_date'               => date('Y-m-d'),
                'remarks'                     => $remarks
            ]);

            $this->workflowService->transition(
                $requestId,
                'RELEASED',
                $user['user_id'],
                $user['role'],
                $remarks
            );

            $this->workflowService->transition(
                $requestId,
                'COMPLETED',
                $user['user_id'],
                $user['role'],
                'Document release completed.'
            );

            $this->auditService->log(
                $user['user_id'],
                'DOCUMENT_RELEASED',
                'document_releases',
                (string)$releaseId,
                null,
                ['request_id' => $requestId, 'recipient' => $recipientType, 'representative' => $repName]
            );
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        ResponseHelper::success(['release_id' => $releaseId], 'Document release recorded successfully.', 201);
    }
}
