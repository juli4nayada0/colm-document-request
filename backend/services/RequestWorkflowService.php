<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Request Workflow & State Machine Service
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Config\Database;
use Colm\Models\DocumentRequest;
use Colm\Models\RequestStatusLog;
use Colm\Models\Student;
use Colm\Models\Document;
use Colm\Models\RequestRequirement;
use InvalidArgumentException;
use RuntimeException;

class RequestWorkflowService {
    private DocumentRequest $requestModel;
    private RequestStatusLog $statusLogModel;
    private Student $studentModel;
    private AuditService $auditService;
    private NotificationService $notificationService;
    private RequestRequirement $requestRequirementModel;

    // Defined official valid state machine transitions
    public const ALLOWED_TRANSITIONS = [
        'REQUEST SUBMITTED' => ['PENDING PAYMENT', 'FOR VERIFICATION', 'REJECTED/CANCELLED'],
        'PENDING PAYMENT' => ['PAID', 'ON HOLD', 'REJECTED/CANCELLED'],
        'PAID' => ['FOR PROCESSING'],
        'FOR PROCESSING' => ['PROCESSING', 'ON HOLD'],
        'FOR VERIFICATION' => [
            'FOR PAYMENT',
            'PROCESSING',
            'INCOMPLETE',
            'FOR CORRECTION',
            'REJECTED/CANCELLED',
            'ON HOLD'
        ],
        'FOR PAYMENT' => ['PROCESSING', 'ON HOLD', 'INCOMPLETE', 'REJECTED/CANCELLED'],
        'PROCESSING' => [
            'FOR REVIEW/APPROVAL',
            'READY FOR RELEASE', // If document doesn't require separate approval
            'FOR CORRECTION',
            'ON HOLD'
        ],
        'FOR REVIEW/APPROVAL' => [
            'READY FOR RELEASE',
            'FOR CORRECTION',
            'REJECTED/CANCELLED',
            'ON HOLD'
        ],
        'READY FOR RELEASE' => ['RELEASED', 'ON HOLD'],
        'RELEASED' => ['COMPLETED'],
        'ON HOLD' => [
            'FOR VERIFICATION',
            'FOR PAYMENT',
            'PROCESSING',
            'FOR REVIEW/APPROVAL',
            'READY FOR RELEASE',
            'REJECTED/CANCELLED'
        ],
        'INCOMPLETE' => [
            'FOR VERIFICATION',
            'REJECTED/CANCELLED'
        ],
        'FOR CORRECTION' => [
            'FOR VERIFICATION',
            'PROCESSING',
            'FOR REVIEW/APPROVAL'
        ],
        'REJECTED/CANCELLED' => [],
        'COMPLETED'          => []
    ];

    public function __construct() {
        $this->requestModel = new DocumentRequest();
        $this->statusLogModel = new RequestStatusLog();
        $this->studentModel = new Student();
        $this->auditService = new AuditService();
        $this->notificationService = new NotificationService();
        $this->requestRequirementModel = new RequestRequirement();
    }

    /**
     * Check if status transition is valid according to state machine
     */
    public function canTransition(string $currentStatus, string $targetStatus, string $userRole = 'Personnel'): bool {
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        return in_array($targetStatus, $allowed, true);
    }

    /**
     * Execute transactional state transition
     */
    public function transition(
        int $requestId,
        string $targetStatus,
        int $userId,
        string $userRole,
        ?string $remarks = null,
        array $extraFields = []
    ): bool {
        $request = $this->requestModel->findById($requestId);
        if (!$request) {
            throw new InvalidArgumentException("Request #{$requestId} does not exist.", 404);
        }

        $currentStatus = $request['current_status'];

        if ($currentStatus === $targetStatus) {
            return true; // No change needed
        }

        if (in_array($targetStatus, ['FOR PAYMENT', 'PENDING PAYMENT'], true) && $this->requestRequirementModel->hasUnverifiedRequired($requestId)) {
            throw new InvalidArgumentException('All required documents must be uploaded and verified before payment.', 422);
        }

        if (in_array($targetStatus, ['PAID', 'FOR PROCESSING', 'PROCESSING', 'FOR REVIEW/APPROVAL', 'READY FOR RELEASE', 'RELEASED', 'COMPLETED'], true)
            && (float)$request['amount_due'] > 0
            && $request['payment_status'] !== 'Paid') {
            throw new InvalidArgumentException('Payment must be verified before this request can proceed.', 422);
        }

        // Validate state machine rule
        if (!$this->canTransition($currentStatus, $targetStatus, $userRole)) {
            throw new InvalidArgumentException(
                "Invalid status transition from '{$currentStatus}' to '{$targetStatus}' for role {$userRole}.",
                422
            );
        }

        $db = Database::getConnection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $timestampUpdates = [];
            $now = date('Y-m-d H:i:s');

            // Apply appropriate lifecycle timestamps
            if ($targetStatus === 'FOR VERIFICATION' && empty($request['verified_at'])) {
                $timestampUpdates['verified_at'] = $now;
            } elseif (in_array($targetStatus, ['FOR PROCESSING', 'PROCESSING'], true) && empty($request['processing_started_at'])) {
                $timestampUpdates['processing_started_at'] = $now;
            } elseif ($targetStatus === 'READY FOR RELEASE' && empty($request['processing_completed_at'])) {
                $timestampUpdates['processing_completed_at'] = $now;
            } elseif ($targetStatus === 'COMPLETED' && empty($request['completed_at'])) {
                $timestampUpdates['completed_at'] = $now;
            } elseif ($targetStatus === 'RELEASED' && empty($request['released_at'])) {
                $timestampUpdates['released_at'] = $now;
            }

            // Merge extra fields (e.g. payment_status, assigned_personnel_id)
            $allUpdates = array_merge($timestampUpdates, $extraFields);

            // 1. Update Document Request
            $this->requestModel->updateStatus($requestId, $targetStatus, $allUpdates);

            // 2. Append Status Log
            $this->statusLogModel->create($requestId, $userId, $currentStatus, $targetStatus, $remarks);

            // 3. Append Audit Log
            $this->auditService->log(
                $userId,
                'REQUEST_STATUS_CHANGE',
                'document_requests',
                (string)$requestId,
                ['status' => $currentStatus],
                ['status' => $targetStatus, 'remarks' => $remarks]
            );

            // 4. Send Notification to Student
            $student = $this->studentModel->findById((int)$request['student_id']);
            if ($student && !empty($student['user_id'])) {
                $trackingNum = $request['tracking_number'];
                $docName = $request['document_name'];
                
                $notifDetails = $this->getNotificationMessage($targetStatus, $trackingNum, $docName, $remarks);
                $this->notificationService->notify(
                    (int)$student['user_id'],
                    $notifDetails['title'],
                    $notifDetails['message'],
                    $notifDetails['type'],
                    $requestId
                );
            }

            if ($ownsTransaction) {
                $db->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new RuntimeException("Workflow transition error: " . $e->getMessage(), 500);
        }
    }

    /**
     * Map target status to clear user-facing notification message
     */
    private function getNotificationMessage(string $status, string $tracking, string $docName, ?string $remarks): array {
        switch ($status) {
            case 'FOR VERIFICATION':
                return [
                    'title'   => "Request Under Verification ({$tracking})",
                    'message' => "Your request for {$docName} is currently being verified by the Registrar Office.",
                    'type'    => 'info'
                ];
            case 'FOR PAYMENT':
                return [
                    'title'   => "Payment Required ({$tracking})",
                    'message' => "Your request for {$docName} has been verified and is now awaiting payment settlement.",
                    'type'    => 'warning'
                ];
            case 'PENDING PAYMENT':
                return [
                    'title'   => "Payment Pending ({$tracking})",
                    'message' => "Please pay {$docName} in person at the Registrar/Personnel office.",
                    'type'    => 'warning'
                ];
            case 'PAID':
                return [
                    'title'   => "Payment Confirmed ({$tracking})",
                    'message' => "Your payment for {$docName} was confirmed. The request is ready for processing.",
                    'type'    => 'success'
                ];
            case 'FOR PROCESSING':
                return [
                    'title'   => "Ready for Processing ({$tracking})",
                    'message' => "Your paid request for {$docName} is now in the processing queue.",
                    'type'    => 'info'
                ];
            case 'PROCESSING':
                return [
                    'title'   => "Document Processing Started ({$tracking})",
                    'message' => "Payment confirmed. Your {$docName} is now in processing queue.",
                    'type'    => 'info'
                ];
            case 'FOR REVIEW/APPROVAL':
                return [
                    'title'   => "Under Registrar Review ({$tracking})",
                    'message' => "Your {$docName} has been prepared and is queued for final approval/signatures.",
                    'type'    => 'info'
                ];
            case 'READY FOR RELEASE':
                return [
                    'title'   => "Ready for Claiming ({$tracking})",
                    'message' => "Your {$docName} is ready! " . ($remarks ? "Note: {$remarks}" : "Please claim at Registrar Office."),
                    'type'    => 'success'
                ];
            case 'RELEASED':
                return [
                    'title'   => "Document Released ({$tracking})",
                    'message' => "Your requested {$docName} has been released. Thank you!",
                    'type'    => 'success'
                ];
            case 'COMPLETED':
                return [
                    'title'   => "Request Completed ({$tracking})",
                    'message' => "Your {$docName} request has been completed and released.",
                    'type'    => 'success'
                ];
            case 'INCOMPLETE':
                return [
                    'title'   => "Incomplete Requirements ({$tracking})",
                    'message' => "Additional requirements required for {$docName}. " . ($remarks ? "Details: {$remarks}" : ""),
                    'type'    => 'warning'
                ];
            case 'FOR CORRECTION':
                return [
                    'title'   => "Correction Needed ({$tracking})",
                    'message' => "Your request requires corrections. " . ($remarks ? "Remarks: {$remarks}" : ""),
                    'type'    => 'warning'
                ];
            case 'ON HOLD':
                return [
                    'title'   => "Request On Hold ({$tracking})",
                    'message' => "Your request {$tracking} has been placed on hold. " . ($remarks ? "Reason: {$remarks}" : "Please contact the Registrar."),
                    'type'    => 'warning'
                ];
            case 'REJECTED/CANCELLED':
                return [
                    'title'   => "Request Cancelled/Rejected ({$tracking})",
                    'message' => "Your request for {$docName} has been cancelled/rejected. " . ($remarks ? "Reason: {$remarks}" : ""),
                    'type'    => 'error'
                ];
            default:
                return [
                    'title'   => "Request Status Updated ({$tracking})",
                    'message' => "Your request is now in status: {$status}.",
                    'type'    => 'info'
                ];
        }
    }
}
