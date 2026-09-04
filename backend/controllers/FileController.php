<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Secure Authenticated File Streaming Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\RequestRequirement;
use Colm\Models\Payment;
use Colm\Models\DocumentRequest;
use Colm\Services\FileService;
use Colm\Services\AuditService;
use Colm\Helpers\ResponseHelper;

class FileController {
    private FileService $fileService;
    private AuditService $auditService;
    private RequestRequirement $reqReqModel;
    private Payment $paymentModel;
    private DocumentRequest $requestModel;

    public function __construct() {
        $this->fileService  = new FileService();
        $this->auditService = new AuditService();
        $this->reqReqModel  = new RequestRequirement();
        $this->paymentModel = new Payment();
        $this->requestModel = new DocumentRequest();
    }

    /**
     * GET /api/v1/files/download?type=requirement|payment&id=...
     */
    public function download(): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();

        $type = $_GET['type'] ?? 'requirement';
        $id = (int)($_GET['id'] ?? 0);

        if ($id <= 0) {
            ResponseHelper::error('Invalid file reference ID.', 400);
        }

        $filePath = '';
        $downloadFilename = '';
        $mimeType = 'application/octet-stream';
        $requestId = 0;

        if ($type === 'requirement') {
            $record = $this->reqReqModel->findById($id);
            if (!$record) {
                ResponseHelper::error('Uploaded requirement record not found.', 404);
            }
            $requestId = (int)$record['request_id'];
            $filePath = $this->fileService->getAbsolutePath($record['file_path']);
            $downloadFilename = $record['original_filename'];
            $mimeType = $record['mime_type'];
        } elseif ($type === 'payment') {
            $payment = $this->paymentModel->findById($id);
            if (!$payment || empty($payment['proof_file'])) {
                ResponseHelper::error('Payment proof file not found.', 404);
            }
            $requestId = (int)$payment['request_id'];
            $filePath = $this->fileService->getAbsolutePath($payment['proof_file']);
            $downloadFilename = $payment['original_filename'] ?? 'payment_receipt.png';
            $mimeType = 'image/jpeg';
        } else {
            ResponseHelper::error("Invalid file type category '{$type}'.", 400);
        }

        // Verify authorization against associated request
        $request = $this->requestModel->findById($requestId);
        if (!$request) {
            ResponseHelper::error('Associated request does not exist.', 404);
        }

        // Student data isolation check
        RoleMiddleware::authorizeStudentOwnership($user, (int)$request['student_id']);

        // Log sensitive file access in audit trail
        $this->auditService->log(
            $user['user_id'],
            'FILE_DOWNLOADED',
            $type,
            (string)$id,
            null,
            ['filename' => $downloadFilename, 'request_id' => $requestId]
        );

        ResponseHelper::streamFile($filePath, $downloadFilename, $mimeType);
    }
}
