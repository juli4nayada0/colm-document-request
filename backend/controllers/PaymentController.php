<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Payment Processing Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\Payment;
use Colm\Models\DocumentRequest;
use Colm\Models\RequestRequirement;
use Colm\Services\FileService;
use Colm\Services\AuditService;
use Colm\Services\RequestWorkflowService;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;
use Colm\Config\Database;

class PaymentController {
    private Payment $paymentModel;
    private DocumentRequest $requestModel;
    private FileService $fileService;
    private AuditService $auditService;
    private RequestWorkflowService $workflowService;
    private RequestRequirement $requestRequirementModel;

    public function __construct() {
        $this->paymentModel    = new Payment();
        $this->requestModel    = new DocumentRequest();
        $this->fileService     = new FileService();
        $this->auditService    = new AuditService();
        $this->workflowService = new RequestWorkflowService();
        $this->requestRequirementModel = new RequestRequirement();
    }

    /**
     * GET /api/v1/requests/{id}/payment
     */
    public function show(int $requestId): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();

        $request = $this->requestModel->findById($requestId);
        if (!$request) {
            ResponseHelper::error("Request #{$requestId} not found.", 404);
        }

        RoleMiddleware::authorizeStudentOwnership($user, (int)$request['student_id']);

        $payment = $this->paymentModel->findByRequestId($requestId);
        ResponseHelper::success($payment, 'Payment details retrieved.');
    }

    /**
     * POST /api/v1/requests/{id}/payment
     * Submit payment reference / proof
     */
    public function store(int $requestId): void {
        ResponseHelper::error('Online payment is disabled. Pay in person at the Registrar/Personnel office.', 403);
    }

    /**
     * POST /api/v1/requests/{id}/payment/confirm
     * Registrar/Personnel records an in-person cashier payment.
     */
    public function confirm(int $requestId): void {
        $auth = new AuthMiddleware();
        $user = $auth->handle();
        RoleMiddleware::authorize($user, ['Registrar', 'Personnel']);

        $request = $this->requestModel->findById($requestId);
        if (!$request) {
            ResponseHelper::error("Request #{$requestId} not found.", 404);
        }

        if ($request['current_status'] !== 'PENDING PAYMENT') {
            ResponseHelper::error('Only requests in PENDING PAYMENT can be confirmed.', 422);
        }

        if ($this->requestRequirementModel->hasUnverifiedRequired($requestId)) {
            ResponseHelper::error('All required supporting documents must be uploaded and verified before payment.', 422);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $amount = round((float)($data['amount'] ?? 0), 2);
        $outstanding = round((float)$request['amount_due'] - (float)$request['amount_paid'], 2);
        if ($amount <= 0 || abs($amount - $outstanding) > 0.01) {
            ResponseHelper::error('Amount paid must match the required amount of ' . number_format($outstanding, 2, '.', ''), 422);
        }

        if (($data['payment_method'] ?? 'Official Receipt (Cashier)') !== 'Official Receipt (Cashier)') {
            ResponseHelper::error('Only in-person cashier payment is accepted.', 422);
        }
        if (empty($data['payment_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data['payment_date'])) {
            ResponseHelper::error('A valid payment date is required.', 422);
        }
        if (($data['confirmation'] ?? false) !== true) {
            ResponseHelper::error('Cashier payment confirmation is required.', 422);
        }

        $existingPayment = $this->paymentModel->findByRequestId($requestId);
        if ($existingPayment && $existingPayment['payment_status'] === 'Paid') {
            ResponseHelper::error('This request has already been paid.', 409);
        }

        $reference = SecurityHelper::sanitizeString($data['reference_number'] ?? '');
        if ($reference !== '' && !preg_match('/^\d{1,20}$/', $reference)) {
            ResponseHelper::error('Official receipt/reference number must contain numbers only and be no more than 20 digits.', 422);
        }
        $db = Database::getConnection();
        $db->beginTransaction();
        try {
            $paymentId = $this->paymentModel->create([
                'request_id'        => $requestId,
                'reference_number'  => $reference,
                'amount'            => $amount,
                'payment_method'    => 'Official Receipt (Cashier)',
                'payment_status'    => 'Paid',
                'payment_date'      => $data['payment_date']
            ]);
            $this->paymentModel->verifyPayment($paymentId, 'Paid', $user['user_id']);

            $this->requestModel->updateStatus($requestId, 'PENDING PAYMENT', [
                'payment_status' => 'Paid',
                'amount_paid' => $amount
            ]);
            $this->workflowService->transition($requestId, 'PAID', $user['user_id'], $user['role'], 'Cashier payment confirmed.');
            $this->workflowService->transition($requestId, 'FOR PROCESSING', $user['user_id'], $user['role'], 'Payment confirmed; request ready for processing.');
            $this->auditService->log($user['user_id'], 'PAYMENT_CONFIRMED_IN_PERSON', 'payments', (string)$paymentId, null, [
                'request_id' => $requestId,
                'amount_paid' => $amount,
                'reference_number' => $reference
            ]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        ResponseHelper::success(['payment_id' => $paymentId, 'status' => 'FOR PROCESSING'], 'Cashier payment confirmed. Request is ready for processing.', 201);
    }

    /**
     * PATCH /api/v1/payments/{id}/verify
     * Personnel/Registrar verifies or rejects payment
     */
    public function verify(int $paymentId): void {
        ResponseHelper::error('Use the in-person cashier payment confirmation on the document request.', 403);
    }

}
