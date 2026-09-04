<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Institutional Reporting & Analytics Engine (13 Official Reports)
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Config\Database;
use Colm\Helpers\DateHelper;
use PDO;

class ReportService {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Report 1: Daily Document Requests
     */
    public function getDailyReport(?string $date = null): array {
        $date = $date ?? date('Y-m-d');
        $sql = "SELECT r.tracking_number, r.submitted_at, r.current_status, r.copies, r.amount_due, r.payment_status,
                       s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name,
                       d.document_name, d.document_code,
                       e.program
                FROM document_requests r
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                WHERE DATE(r.submitted_at) = :date
                ORDER BY r.submitted_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['date' => $date]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $summary = [
            'report_date'   => $date,
            'total_requests'=> count($rows),
            'total_amount'  => array_sum(array_column($rows, 'amount_due')),
            'records'       => $rows
        ];

        return $summary;
    }

    /**
     * Report 2: Monthly Document Requests
     */
    public function getMonthlyReport(int $year, int $month): array {
        $sql = "SELECT DATE(r.submitted_at) as request_date, 
                       COUNT(r.request_id) as total_requests,
                       SUM(CASE WHEN r.current_status IN ('RELEASED', 'COMPLETED') THEN 1 ELSE 0 END) as released_count,
                       SUM(CASE WHEN r.current_status NOT IN ('RELEASED', 'COMPLETED', 'REJECTED/CANCELLED') THEN 1 ELSE 0 END) as pending_count,
                       SUM(r.amount_due) as total_revenue
                FROM document_requests r
                WHERE YEAR(r.submitted_at) = :year AND MONTH(r.submitted_at) = :month
                GROUP BY DATE(r.submitted_at)
                ORDER BY request_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year' => $year, 'month' => $month]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'year'          => $year,
            'month'         => $month,
            'total_month_requests' => array_sum(array_column($records, 'total_requests')),
            'total_month_revenue'  => array_sum(array_column($records, 'total_revenue')),
            'records'       => $records
        ];
    }

    /**
     * Report 3: Requests by Document Type
     */
    public function getByDocumentTypeReport(?string $startDate = null, ?string $endDate = null): array {
        $conditions = [];
        $params = [];

        if ($startDate) {
            $conditions[] = "DATE(r.submitted_at) >= :start_date";
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $conditions[] = "DATE(r.submitted_at) <= :end_date";
            $params['end_date'] = $endDate;
        }
        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT d.document_id, d.document_code, d.document_name, d.fee_amount,
                       COUNT(r.request_id) as total_requests,
                       SUM(r.copies) as total_copies,
                       SUM(r.amount_due) as total_assessed,
                       SUM(CASE WHEN r.current_status IN ('RELEASED', 'COMPLETED') THEN 1 ELSE 0 END) as total_released
                FROM documents d
                LEFT JOIN document_requests r ON d.document_id = r.document_id {$where}
                GROUP BY d.document_id, d.document_code, d.document_name, d.fee_amount
                ORDER BY total_requests DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report 4: Requests by Academic Program
     */
    public function getByProgramReport(?string $startDate = null, ?string $endDate = null): array {
        $conditions = [];
        $params = [];

        if ($startDate) {
            $conditions[] = "DATE(r.submitted_at) >= :start_date";
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $conditions[] = "DATE(r.submitted_at) <= :end_date";
            $params['end_date'] = $endDate;
        }
        $where = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT COALESCE(e.program, 'Unspecified') as program_name,
                       COUNT(r.request_id) as total_requests,
                       SUM(CASE WHEN r.current_status IN ('RELEASED', 'COMPLETED') THEN 1 ELSE 0 END) as completed_requests,
                       SUM(r.amount_due) as total_amount
                FROM document_requests r
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON r.student_id = e.student_id
                {$where}
                GROUP BY program_name
                ORDER BY total_requests DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report 5: Pending Requests Queue
     */
    public function getPendingRequests(): array {
        $sql = "SELECT r.*, s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name,
                       d.document_name, d.processing_days, u.username as assigned_personnel,
                       e.program
                FROM document_requests r
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN users u ON r.assigned_personnel_id = u.user_id
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                WHERE r.current_status NOT IN ('RELEASED', 'COMPLETED', 'REJECTED/CANCELLED')
                ORDER BY r.submitted_at ASC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['overdue_status'] = DateHelper::getOverdueStatus(
                $row['submitted_at'],
                (int)$row['processing_days'],
                $row['completed_at'],
                $row['current_status']
            );
            $row['target_completion_date'] = DateHelper::calculateTargetDate(
                $row['submitted_at'],
                (int)$row['processing_days']
            );
        }

        return $rows;
    }

    /**
     * Report 6: Completed / Ready for Release Requests
     */
    public function getCompletedRequests(): array {
        $sql = "SELECT r.*, s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name,
                       d.document_name, u.username as assigned_personnel
                FROM document_requests r
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN users u ON r.assigned_personnel_id = u.user_id
                WHERE r.current_status IN ('READY FOR RELEASE', 'RELEASED', 'COMPLETED')
                ORDER BY r.completed_at DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report 7: Released Documents with Claiming/Representative details
     */
    public function getReleasedDocuments(): array {
        $sql = "SELECT r.tracking_number, r.submitted_at, r.released_at, r.amount_paid,
                       s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name,
                       d.document_name,
                       dr.release_method, dr.recipient_type, dr.representative_name, dr.representative_relationship,
                       u.username as released_by_user
                FROM document_releases dr
                INNER JOIN document_requests r ON dr.request_id = r.request_id
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN users u ON dr.released_by = u.user_id
                ORDER BY dr.released_at DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report 8: Cancelled / Rejected Requests
     */
    public function getCancelledRequests(): array {
        $sql = "SELECT r.tracking_number, r.submitted_at, r.updated_at as cancelled_at, r.purpose,
                       s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name,
                       d.document_name
                FROM document_requests r
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                WHERE r.current_status = 'REJECTED/CANCELLED'
                ORDER BY r.updated_at DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report 9: Performance by Processing Personnel
     */
    public function getPersonnelPerformance(): array {
        $sql = "SELECT u.user_id, u.username, u.role,
                       COUNT(r.request_id) as total_assigned,
                       SUM(CASE WHEN r.current_status IN ('RELEASED', 'COMPLETED') THEN 1 ELSE 0 END) as released_count,
                       SUM(CASE WHEN r.current_status IN ('READY FOR RELEASE', 'FOR REVIEW/APPROVAL') THEN 1 ELSE 0 END) as completed_count,
                       SUM(CASE WHEN r.current_status IN ('FOR PROCESSING', 'PROCESSING', 'FOR VERIFICATION', 'PENDING PAYMENT', 'FOR PAYMENT') THEN 1 ELSE 0 END) as active_workload
                FROM users u
                LEFT JOIN document_requests r ON u.user_id = r.assigned_personnel_id
                WHERE u.role IN ('Personnel', 'Registrar')
                GROUP BY u.user_id, u.username, u.role
                ORDER BY total_assigned DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report 10: Average Processing Time
     */
    public function getAverageProcessingTime(): array {
        $sql = "SELECT d.document_id, d.document_code, d.document_name, d.processing_days as target_days,
                       COUNT(r.request_id) as total_completed,
                       ROUND(AVG(TIMESTAMPDIFF(HOUR, r.submitted_at, r.completed_at) / 24.0), 1) as avg_actual_days,
                       ROUND(MIN(TIMESTAMPDIFF(HOUR, r.submitted_at, r.completed_at) / 24.0), 1) as min_days,
                       ROUND(MAX(TIMESTAMPDIFF(HOUR, r.submitted_at, r.completed_at) / 24.0), 1) as max_days
                FROM documents d
                INNER JOIN document_requests r ON d.document_id = r.document_id
                WHERE r.completed_at IS NOT NULL
                GROUP BY d.document_id, d.document_code, d.document_name, d.processing_days
                ORDER BY total_completed DESC";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Report 11: Unreleased / Overdue Requests
     */
    public function getOverdueRequests(): array {
        $pending = $this->getPendingRequests();
        return array_values(array_filter($pending, function($req) {
            return in_array($req['overdue_status'], ['Overdue', 'Due Soon'], true);
        }));
    }

    /**
     * Report 12: Payment Status Assessment Report
     */
    public function getPaymentsReport(): array {
        $sql = "SELECT p.*, r.tracking_number, r.current_status as request_status,
                       s.student_number, CONCAT(s.last_name, ', ', s.first_name) as student_name,
                       d.document_name, u.username as verifier_username
                FROM payments p
                INNER JOIN document_requests r ON p.request_id = r.request_id
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN users u ON p.verified_by = u.user_id
                ORDER BY p.created_at DESC";

        $stmt = $this->db->query($sql);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPaid = 0.0;
        $totalPending = 0.0;

        foreach ($records as $p) {
            if ($p['payment_status'] === 'Paid') {
                $totalPaid += (float)$p['amount'];
            } elseif ($p['payment_status'] === 'For Verification') {
                $totalPending += (float)$p['amount'];
            }
        }

        return [
            'total_paid'    => $totalPaid,
            'total_pending' => $totalPending,
            'records'       => $records
        ];
    }

    /**
     * Report 13: Summary Statistics & Dashboard KPI Metrics
     */
    public function getOverallSummaryStatistics(): array {
        $kpiSql = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN current_status = 'REQUEST SUBMITTED' THEN 1 ELSE 0 END) as status_submitted,
            SUM(CASE WHEN current_status = 'FOR VERIFICATION' THEN 1 ELSE 0 END) as status_verification,
            SUM(CASE WHEN current_status = 'FOR PAYMENT' THEN 1 ELSE 0 END) as status_payment,
            SUM(CASE WHEN current_status IN ('FOR PROCESSING', 'PROCESSING') THEN 1 ELSE 0 END) as status_processing,
            SUM(CASE WHEN current_status = 'FOR REVIEW/APPROVAL' THEN 1 ELSE 0 END) as status_review,
            SUM(CASE WHEN current_status = 'READY FOR RELEASE' THEN 1 ELSE 0 END) as status_ready,
            SUM(CASE WHEN current_status IN ('RELEASED', 'COMPLETED') THEN 1 ELSE 0 END) as status_released,
            SUM(CASE WHEN current_status = 'ON HOLD' THEN 1 ELSE 0 END) as status_on_hold,
            SUM(CASE WHEN current_status = 'INCOMPLETE' THEN 1 ELSE 0 END) as status_incomplete,
            SUM(CASE WHEN current_status = 'FOR CORRECTION' THEN 1 ELSE 0 END) as status_correction,
            SUM(CASE WHEN current_status = 'REJECTED/CANCELLED' THEN 1 ELSE 0 END) as status_rejected,
            SUM(amount_due) as total_assessed,
            SUM(amount_paid) as total_collected
        FROM document_requests";

        $stmt = $this->db->query($kpiSql);
        $kpis = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate active pending workload
        $pendingCount = (int)$kpis['status_submitted'] + (int)$kpis['status_verification'] + (int)$kpis['status_payment'] + (int)$kpis['status_processing'] + (int)$kpis['status_review'];

        // Overdue count
        $overdueCount = count($this->getOverdueRequests());

        return [
            'kpis' => array_merge($kpis, [
                'active_pending' => $pendingCount,
                'overdue_count'  => $overdueCount
            ]),
            'top_documents' => array_slice($this->getByDocumentTypeReport(), 0, 5),
            'top_programs'  => array_slice($this->getByProgramReport(), 0, 5)
        ];
    }

    /**
     * Generate CSV export string for any dataset array
     */
    public function exportCsv(array $data, array $columnHeaders): string {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_values($columnHeaders));

        foreach ($data as $row) {
            $csvRow = [];
            foreach (array_keys($columnHeaders) as $key) {
                $csvRow[] = $row[$key] ?? '';
            }
            fputcsv($output, $csvRow);
        }

        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }
}
