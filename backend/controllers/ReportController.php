<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Institutional Reports Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Services\ReportService;
use Colm\Services\AuditService;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;

class ReportController {
    private array $currentUser;
    private ReportService $reportService;
    private AuditService $auditService;

    public function __construct() {
        $auth = new AuthMiddleware();
        $this->currentUser = $auth->handle();
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Admin']);

        $this->reportService = new ReportService();
        $this->auditService = new AuditService();
    }

    /**
     * GET /api/v1/reports/summary
     */
    public function summary(): void {
        $data = $this->reportService->getOverallSummaryStatistics();
        ResponseHelper::success($data, 'Overall summary statistics.');
    }

    /**
     * GET /api/v1/reports/daily
     */
    public function daily(): void {
        $date = !empty($_GET['date']) ? SecurityHelper::sanitizeString($_GET['date']) : date('Y-m-d');
        $data = $this->reportService->getDailyReport($date);
        ResponseHelper::success($data, 'Daily report retrieved.');
    }

    /**
     * GET /api/v1/reports/monthly
     */
    public function monthly(): void {
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('m'));
        $data = $this->reportService->getMonthlyReport($year, $month);
        ResponseHelper::success($data, 'Monthly report retrieved.');
    }

    /**
     * GET /api/v1/reports/document-types
     */
    public function documentTypes(): void {
        $startDate = !empty($_GET['start_date']) ? SecurityHelper::sanitizeString($_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? SecurityHelper::sanitizeString($_GET['end_date']) : null;
        $data = $this->reportService->getByDocumentTypeReport($startDate, $endDate);
        ResponseHelper::success($data, 'Requests by document type report.');
    }

    /**
     * GET /api/v1/reports/programs
     */
    public function programs(): void {
        $startDate = !empty($_GET['start_date']) ? SecurityHelper::sanitizeString($_GET['start_date']) : null;
        $endDate = !empty($_GET['end_date']) ? SecurityHelper::sanitizeString($_GET['end_date']) : null;
        $data = $this->reportService->getByProgramReport($startDate, $endDate);
        ResponseHelper::success($data, 'Requests by program report.');
    }

    /**
     * GET /api/v1/reports/pending
     */
    public function pending(): void {
        $data = $this->reportService->getPendingRequests();
        ResponseHelper::success($data, 'Pending requests report.');
    }

    /**
     * GET /api/v1/reports/completed
     */
    public function completed(): void {
        $data = $this->reportService->getCompletedRequests();
        ResponseHelper::success($data, 'Completed requests report.');
    }

    /**
     * GET /api/v1/reports/released
     */
    public function released(): void {
        $data = $this->reportService->getReleasedDocuments();
        ResponseHelper::success($data, 'Released documents report.');
    }

    /**
     * GET /api/v1/reports/cancelled
     */
    public function cancelled(): void {
        $data = $this->reportService->getCancelledRequests();
        ResponseHelper::success($data, 'Cancelled/Rejected requests report.');
    }

    /**
     * GET /api/v1/reports/personnel
     */
    public function personnel(): void {
        $data = $this->reportService->getPersonnelPerformance();
        ResponseHelper::success($data, 'Personnel workload report.');
    }

    /**
     * GET /api/v1/reports/processing-time
     */
    public function processingTime(): void {
        $data = $this->reportService->getAverageProcessingTime();
        ResponseHelper::success($data, 'Processing time report.');
    }

    /**
     * GET /api/v1/reports/overdue
     */
    public function overdue(): void {
        $data = $this->reportService->getOverdueRequests();
        ResponseHelper::success($data, 'Unreleased and overdue requests report.');
    }

    /**
     * GET /api/v1/reports/payments
     */
    public function payments(): void {
        $data = $this->reportService->getPaymentsReport();
        ResponseHelper::success($data, 'Payments assessment report.');
    }

    /**
     * GET /api/v1/reports/export?type=daily|monthly|pending|released|payments
     */
    public function export(): void {
        $type = $_GET['type'] ?? 'pending';

        $this->auditService->log(
            $this->currentUser['user_id'],
            'REPORT_EXPORTED',
            'reports',
            $type
        );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="COLM_Report_' . $type . '_' . date('Ymd_His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        switch ($type) {
            case 'daily':
                $data = $this->reportService->getDailyReport()['records'];
                $headers = [
                    'tracking_number' => 'Tracking Number',
                    'student_number'  => 'Student Number',
                    'student_name'    => 'Student Name',
                    'document_name'   => 'Document',
                    'copies'          => 'Copies',
                    'amount_due'      => 'Amount Due',
                    'payment_status'  => 'Payment Status',
                    'current_status'  => 'Status',
                    'submitted_at'    => 'Date Submitted'
                ];
                break;
            case 'released':
                $data = $this->reportService->getReleasedDocuments();
                $headers = [
                    'tracking_number'  => 'Tracking Number',
                    'student_number'   => 'Student Number',
                    'student_name'     => 'Student Name',
                    'document_name'    => 'Document',
                    'release_method'   => 'Release Method',
                    'recipient_type'   => 'Recipient',
                    'representative_name' => 'Representative',
                    'released_by_user' => 'Released By',
                    'released_at'      => 'Release Date'
                ];
                break;
            case 'payments':
                $data = $this->reportService->getPaymentsReport()['records'];
                $headers = [
                    'tracking_number'  => 'Tracking Number',
                    'student_number'   => 'Student Number',
                    'student_name'     => 'Student Name',
                    'document_name'    => 'Document',
                    'reference_number' => 'Reference Number',
                    'payment_method'   => 'Method',
                    'amount'           => 'Amount',
                    'payment_status'   => 'Status',
                    'payment_date'     => 'Payment Date'
                ];
                break;
            case 'pending':
            default:
                $data = $this->reportService->getPendingRequests();
                $headers = [
                    'tracking_number'  => 'Tracking Number',
                    'student_number'   => 'Student Number',
                    'student_name'     => 'Student Name',
                    'document_name'    => 'Document',
                    'program'          => 'Program',
                    'current_status'   => 'Status',
                    'overdue_status'   => 'Overdue Status',
                    'submitted_at'     => 'Date Submitted',
                    'target_completion_date' => 'Target Completion'
                ];
                break;
        }

        echo $this->reportService->exportCsv($data, $headers);
        exit;
    }
}
