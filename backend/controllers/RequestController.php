<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Document Request Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\DocumentRequest;
use Colm\Models\Document;
use Colm\Models\Student;
use Colm\Models\DocumentRequirement;
use Colm\Models\RequestRequirement;
use Colm\Models\RequestStatusLog;
use Colm\Models\Payment;
use Colm\Models\DocumentRelease;
use Colm\Services\TrackingNumberService;
use Colm\Services\RequestWorkflowService;
use Colm\Services\FileService;
use Colm\Services\AuditService;
use Colm\Validators\RequestValidator;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;
use Colm\Helpers\DateHelper;

class RequestController {
    private DocumentRequest $requestModel;
    private Document $documentModel;
    private Student $studentModel;
    private DocumentRequirement $docReqModel;
    private RequestRequirement $reqReqModel;
    private RequestStatusLog $statusLogModel;
    private Payment $paymentModel;
    private DocumentRelease $releaseModel;
    private RequestWorkflowService $workflowService;
    private FileService $fileService;
    private AuditService $auditService;
    private RequestValidator $validator;

    public function __construct() {
        $this->requestModel    = new DocumentRequest();
        $this->documentModel   = new Document();
        $this->studentModel    = new Student();
        $this->docReqModel     = new DocumentRequirement();
        $this->reqReqModel     = new RequestRequirement();
        $this->statusLogModel  = new RequestStatusLog();
        $this->paymentModel    = new Payment();
        $this->releaseModel    = new DocumentRelease();
        $this->workflowService = new RequestWorkflowService();
        $this->fileService     = new FileService();
        $this->auditService    = new AuditService();
        $this->validator       = new RequestValidator();
    }

    /**
     * GET /api/v1/requests
     */
    public function index(): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();

        $page = (int)($_GET['page'] ?? 1);
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        
        $studentId = null;
        if ($user['role'] === 'Student') {
            $studentId = (int)$user['student_id'];
        } elseif (!empty($_GET['student_id'])) {
            $studentId = (int)$_GET['student_id'];
        }

        $status = !empty($_GET['status']) ? SecurityHelper::sanitizeString($_GET['status']) : null;
        $documentId = !empty($_GET['document_id']) ? (int)$_GET['document_id'] : null;
        $paymentStatus = !empty($_GET['payment_status']) ? SecurityHelper::sanitizeString($_GET['payment_status']) : null;
        $personnelId = !empty($_GET['personnel_id']) ? (int)$_GET['personnel_id'] : null;
        $program = !empty($_GET['program']) ? SecurityHelper::sanitizeString($_GET['program']) : null;
        $releaseMethod = !empty($_GET['release_method']) ? SecurityHelper::sanitizeString($_GET['release_method']) : null;
        $startDate = !empty($_GET['start_date']) ? SecurityHelper::sanitizeString($_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? SecurityHelper::sanitizeString($_GET['end_date']) : null;
        $search = !empty($_GET['search']) ? SecurityHelper::sanitizeString($_GET['search']) : null;
        $scope = !empty($_GET['scope']) ? SecurityHelper::sanitizeString($_GET['scope']) : null;
        $sortField = !empty($_GET['sort_field']) ? SecurityHelper::sanitizeString($_GET['sort_field']) : 'submitted_at';
        $sortDirection = !empty($_GET['sort_dir']) ? SecurityHelper::sanitizeString($_GET['sort_dir']) : 'DESC';

        // Filter personnel queue if requested
        if ($user['role'] === 'Personnel' && isset($_GET['my_queue']) && $_GET['my_queue'] === '1') {
            $personnelId = $user['user_id'];
        }

        $result = $this->requestModel->getList(
            $page,
            $limit,
            $studentId,
            $status,
            $documentId,
            $paymentStatus,
            $personnelId,
            $program,
            $releaseMethod,
            $startDate,
            $endDate,
            $search,
            $sortField,
            $sortDirection
            , $scope
        );

        // Compute operational overdue status on results
        foreach ($result['records'] as &$rec) {
            $rec['overdue_status'] = DateHelper::getOverdueStatus(
                $rec['submitted_at'],
                (int)$rec['processing_days'],
                $rec['completed_at'],
                $rec['current_status']
            );
            $rec['target_completion_date'] = DateHelper::calculateTargetDate(
                $rec['submitted_at'],
                (int)$rec['processing_days']
            );
        }

        ResponseHelper::success($result['records'], 'Requests retrieved.', 200, [
            'total' => $result['total'],
            'page'  => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages']
        ]);
    }

    /**
     * GET /api/v1/requests/{id}
     */
    public function show(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();

        $request = $this->requestModel->findById($id);
        if (!$request) {
            ResponseHelper::error("Request #{$id} not found.", 404);
        }

        // Student data isolation check
        RoleMiddleware::authorizeStudentOwnership($user, (int)$request['student_id']);

        // Fetch requirements, status timeline, payment, and release details
        $uploadedRequirements = $this->reqReqModel->getByRequestId($id);
        $timeline = $this->statusLogModel->getTimeline($id);
        $payment = $this->paymentModel->findByRequestId($id);
        $release = $this->releaseModel->findByRequestId($id);

        $request['overdue_status'] = DateHelper::getOverdueStatus(
            $request['submitted_at'],
            (int)$request['processing_days'],
            $request['completed_at'],
            $request['current_status']
        );
        $request['target_completion_date'] = DateHelper::calculateTargetDate(
            $request['submitted_at'],
            (int)$request['processing_days']
        );

        ResponseHelper::success([
            'request'               => $request,
            'uploaded_requirements' => $uploadedRequirements,
            'timeline'              => $timeline,
            'payment'               => $payment,
            'release'               => $release
        ], 'Request details retrieved.');
    }

    /**
     * GET /api/v1/requests/{id}/timeline
     */
    public function timeline(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();

        $request = $this->requestModel->findById($id);
        if (!$request) {
            ResponseHelper::error("Request #{$id} not found.", 404);
        }

        RoleMiddleware::authorizeStudentOwnership($user, (int)$request['student_id']);
        $timeline = $this->statusLogModel->getTimeline($id);

        ResponseHelper::success($timeline, 'Timeline retrieved.');
    }

    /**
     * POST /api/v1/requests
     * Student multi-step document request submission
     */
    public function store(): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Student']);

        $studentId = $user['student_id'];
        if (!$studentId) {
            ResponseHelper::error('No student profile found for this user.', 400);
        }

        $data = $_POST;
        if (empty($data)) {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];
        }

        if (!$this->validator->validateSubmission($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $documentId = (int)$data['document_id'];
        $doc = $this->documentModel->findById($documentId);
        if (!$doc || (int)$doc['is_active'] !== 1) {
            ResponseHelper::error('Selected document type is not available.', 400);
        }

        $requiredRequirements = array_filter(
            $this->docReqModel->getByDocumentId($documentId, true),
            static fn(array $requirement): bool => (int)$requirement['is_required'] === 1
        );
        foreach ($requiredRequirements as $requirement) {
            $field = 'req_' . $requirement['requirement_id'];
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                ResponseHelper::error('All required supporting documents must be uploaded before submitting.', 422, [
                    'requirement_id' => (int)$requirement['requirement_id'],
                    'requirement_name' => $requirement['requirement_name']
                ]);
            }
        }

        $copies = max(1, (int)$data['copies']);
        $amountDue = (float)$doc['fee_amount'] * $copies;

        // Generate unique sequential tracking number (REG-YYYY-XXXXXX)
        $trackingNumber = TrackingNumberService::generateNextTrackingNumber();

        $requestData = [
            'tracking_number'         => $trackingNumber,
            'student_id'              => $studentId,
            'document_id'             => $documentId,
            'copies'                  => $copies,
            'purpose'                 => SecurityHelper::sanitizeString($data['purpose']),
            'release_method'          => $data['release_method'],
            'preferred_claiming_date' => !empty($data['preferred_claiming_date']) ? $data['preferred_claiming_date'] : null,
            'additional_instructions' => !empty($data['additional_instructions']) ? SecurityHelper::sanitizeString($data['additional_instructions']) : null,
            'current_status'          => 'PENDING PAYMENT',
            'assigned_personnel_id'   => null,
            'payment_status'          => $amountDue > 0 ? 'Unpaid' : 'Paid',
            'amount_due'              => $amountDue,
            'amount_paid'             => 0.00
        ];

        $requestId = $this->requestModel->create($requestData);

        // Record initial status log
        $this->statusLogModel->create($requestId, $user['user_id'], 'NONE', 'PENDING PAYMENT', 'Request submitted. Please pay in person at the Registrar/Personnel office.');

        // Handle file uploads for requirements if submitted via multipart/form-data
        if (!empty($_FILES)) {
            foreach ($_FILES as $key => $file) {
                if (str_starts_with($key, 'req_') && $file['error'] === UPLOAD_ERR_OK) {
                    $reqId = (int)str_replace('req_', '', $key);
                    try {
                        $stored = $this->fileService->storeUploadedFile($file, 'requirements');
                        $this->reqReqModel->create([
                            'request_id'        => $requestId,
                            'requirement_id'    => $reqId,
                            'file_path'         => $stored['file_path'],
                            'original_filename' => $stored['original_filename'],
                            'stored_filename'   => $stored['stored_filename'],
                            'mime_type'         => $stored['mime_type'],
                            'file_size'         => $stored['file_size'],
                            'verification_status'=> 'Pending'
                        ]);
                    } catch (\Throwable $e) {
                        // Log upload error
                        error_log("[COLM FILE UPLOAD ERROR] " . $e->getMessage());
                    }
                }
            }
        }

        // Audit Log
        $this->auditService->log(
            $user['user_id'],
            'REQUEST_SUBMITTED',
            'document_requests',
            (string)$requestId,
            null,
            ['tracking_number' => $trackingNumber, 'document' => $doc['document_name'], 'amount_due' => $amountDue]
        );

        ResponseHelper::success([
            'request_id'      => $requestId,
            'tracking_number' => $trackingNumber,
            'amount_due'      => $amountDue
        ], 'Document request submitted successfully.', 201);
    }

    /**
     * PATCH /api/v1/requests/{id}/status
     * Authorized status transition using the workflow engine
     */
    public function updateStatus(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Personnel', 'Admin']);

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateStatusUpdate($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $targetStatus = $data['new_status'];
        $remarks = !empty($data['remarks']) ? SecurityHelper::sanitizeString($data['remarks']) : null;

        if (in_array($targetStatus, ['PAID', 'FOR PROCESSING'], true)) {
            ResponseHelper::error('Payment confirmation must be completed through the cashier payment confirmation action.', 422);
        }

        $extraFields = [];
        if (!empty($data['payment_status'])) {
            $extraFields['payment_status'] = $data['payment_status'];
        }
        if ($targetStatus === 'PROCESSING') {
            $extraFields['assigned_personnel_id'] = $user['user_id'];
        }

        $this->workflowService->transition(
            $id,
            $targetStatus,
            $user['user_id'],
            $user['role'],
            $remarks,
            $extraFields
        );

        ResponseHelper::success(['status' => $targetStatus], "Request status transitioned to '{$targetStatus}'.");
    }

    /**
     * POST /api/v1/requests/{id}/assign
     * Assign processing personnel to a request (Registrar / Admin only)
     */
    public function assign(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Admin']);

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $personnelId = !empty($data['personnel_id']) ? (int)$data['personnel_id'] : null;

        $request = $this->requestModel->findById($id);
        if (!$request) {
            ResponseHelper::error("Request #{$id} not found.", 404);
        }

        $this->requestModel->updateStatus($id, $request['current_status'], ['assigned_personnel_id' => $personnelId]);

        $this->auditService->log(
            $user['user_id'],
            'REQUEST_ASSIGNED',
            'document_requests',
            (string)$id,
            ['assigned_personnel_id' => $request['assigned_personnel_id']],
            ['assigned_personnel_id' => $personnelId]
        );

        ResponseHelper::success(['assigned_personnel_id' => $personnelId], 'Personnel assigned to request.');
    }

    /**
     * POST /api/v1/requests/{id}/requirements
     * Upload an additional requirement file to an existing request
     */
    public function uploadRequirement(int $id): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();

        $request = $this->requestModel->findById($id);
        if (!$request) {
            ResponseHelper::error("Request #{$id} not found.", 404);
        }

        RoleMiddleware::authorizeStudentOwnership($user, (int)$request['student_id']);

        if (!isset($_FILES['file']) || empty($_POST['requirement_id'])) {
            ResponseHelper::error('File and requirement_id are required.', 400);
        }

        $reqId = (int)$_POST['requirement_id'];
        $stored = $this->fileService->storeUploadedFile($_FILES['file'], 'requirements');

        $reqReqId = $this->reqReqModel->create([
            'request_id'          => $id,
            'requirement_id'      => $reqId,
            'file_path'           => $stored['file_path'],
            'original_filename'   => $stored['original_filename'],
            'stored_filename'     => $stored['stored_filename'],
            'mime_type'           => $stored['mime_type'],
            'file_size'           => $stored['file_size'],
            'verification_status' => 'Pending'
        ]);

        $this->auditService->log(
            $user['user_id'],
            'REQUIREMENT_UPLOADED',
            'request_requirements',
            (string)$reqReqId,
            null,
            ['request_id' => $id, 'requirement_id' => $reqId, 'filename' => $stored['original_filename']]
        );

        ResponseHelper::success(['request_requirement_id' => $reqReqId], 'Requirement uploaded successfully.');
    }

    /**
     * PATCH /api/v1/requests/requirements/{id}/verify
     * Personnel/Registrar checks and verifies a specific requirement
     */
    public function verifyRequirement(int $reqReqId): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Personnel']);

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $status = $data['status'] ?? 'Verified';
        $remarks = !empty($data['remarks']) ? SecurityHelper::sanitizeString($data['remarks']) : null;

        if (!in_array($status, ['Verified', 'Rejected', 'Resubmission Requested'], true)) {
            ResponseHelper::error('Invalid verification status.', 422);
        }

        $this->reqReqModel->updateVerification($reqReqId, $status, $user['user_id'], $remarks);

        $this->auditService->log(
            $user['user_id'],
            'REQUIREMENT_VERIFIED',
            'request_requirements',
            (string)$reqReqId,
            null,
            ['status' => $status, 'remarks' => $remarks]
        );

        ResponseHelper::success(['status' => $status], 'Requirement verification status recorded.');
    }

    /**
     * GET /api/v1/requests/track/{trackingNumber}
     * Public Tracking Endpoint (Sanitized - No confidential student data exposed)
     */
    public function publicTrack(string $trackingNumber): void {
        $cleanTracking = SecurityHelper::sanitizeString($trackingNumber);
        $request = $this->requestModel->findByTrackingNumber($cleanTracking);

        if (!$request) {
            ResponseHelper::error("No document request found for tracking number '{$cleanTracking}'.", 404);
        }

        $timeline = $this->statusLogModel->getTimeline((int)$request['request_id']);

        // Sanitize timeline logs (strip internal private notes if any)
        $publicTimeline = array_map(function($t) {
            return [
                'status'     => $t['new_status'],
                'date'       => $t['created_at'],
                'remarks'    => $t['remarks']
            ];
        }, $timeline);

        $sanitizedData = [
            'tracking_number'        => $request['tracking_number'],
            'document_name'          => $request['document_name'],
            'current_status'         => $request['current_status'],
            'submitted_at'           => $request['submitted_at'],
            'release_method'         => $request['release_method'],
            'copies'                 => $request['copies'],
            'target_completion_date' => DateHelper::calculateTargetDate($request['submitted_at'], (int)$request['processing_days']),
            'timeline'               => $publicTimeline
        ];

        ResponseHelper::success($sanitizedData, 'Tracking information retrieved.');
    }
}
