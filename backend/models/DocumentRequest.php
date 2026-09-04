<?php
/**
 * COLM Registrar Document Request and Tracking System
 * DocumentRequest Model
 */

declare(strict_types=1);

namespace Colm\Models;

class DocumentRequest extends BaseModel {
    /**
     * Find request by ID with complete student and document joins
     */
    public function findById(int $requestId): ?array {
        $sql = "SELECT r.*, 
                       s.student_number, s.last_name, s.first_name, s.middle_name, s.email, s.contact_number, s.address,
                       d.document_code, d.document_name, d.processing_days, d.fee_amount, d.is_digital_allowed, d.requires_approval,
                       u.username as assigned_personnel_name,
                       e.program, e.year_level, e.academic_year, e.semester
                FROM document_requests r
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN users u ON r.assigned_personnel_id = u.user_id
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id 
                        FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                WHERE r.request_id = :id
                LIMIT 1";
        return $this->fetchOne($sql, ['id' => $requestId]);
    }

    /**
     * Find request by tracking number (with sanitized options for public tracking)
     */
    public function findByTrackingNumber(string $trackingNumber): ?array {
        $sql = "SELECT r.*, 
                       s.student_number, s.last_name, s.first_name, s.middle_name, s.email, s.contact_number, s.address,
                       d.document_code, d.document_name, d.processing_days, d.fee_amount, d.is_digital_allowed, d.requires_approval,
                       u.username as assigned_personnel_name,
                       e.program, e.year_level
                FROM document_requests r
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN users u ON r.assigned_personnel_id = u.user_id
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id 
                        FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                WHERE r.tracking_number = :tracking
                LIMIT 1";
        return $this->fetchOne($sql, ['tracking' => $trackingNumber]);
    }

    /**
     * Get paginated requests list with advanced filters
     */
    public function getList(
        int $page = 1,
        int $limit = 25,
        ?int $studentId = null,
        ?string $status = null,
        ?int $documentId = null,
        ?string $paymentStatus = null,
        ?int $personnelId = null,
        ?string $program = null,
        ?string $releaseMethod = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $search = null,
        string $sortField = 'submitted_at',
        string $sortDirection = 'DESC',
        ?string $scope = null
    ): array {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];

        if ($studentId !== null) {
            $conditions[] = "r.student_id = :student_id";
            $params['student_id'] = $studentId;
        }

        if (!empty($status)) {
            $conditions[] = "r.current_status = :status";
            $params['status'] = $status;
        }

        if ($scope === 'active') {
            $conditions[] = "r.completed_at IS NULL AND r.current_status NOT IN ('RELEASED', 'REJECTED/CANCELLED')";
        } elseif ($scope === 'released') {
            $conditions[] = "r.current_status IN ('RELEASED', 'COMPLETED')";
        } elseif ($scope === 'overdue') {
            $conditions[] = "r.completed_at IS NULL AND DATE_ADD(r.submitted_at, INTERVAL d.processing_days DAY) < NOW()";
        }

        if ($documentId !== null) {
            $conditions[] = "r.document_id = :document_id";
            $params['document_id'] = $documentId;
        }

        if (!empty($paymentStatus)) {
            $conditions[] = "r.payment_status = :payment_status";
            $params['payment_status'] = $paymentStatus;
        }

        if ($personnelId !== null) {
            $conditions[] = "r.assigned_personnel_id = :personnel_id";
            $params['personnel_id'] = $personnelId;
        }

        if (!empty($program)) {
            $conditions[] = "e.program = :program";
            $params['program'] = $program;
        }

        if (!empty($releaseMethod)) {
            $conditions[] = "r.release_method = :release_method";
            $params['release_method'] = $releaseMethod;
        }

        if (!empty($startDate)) {
            $conditions[] = "DATE(r.submitted_at) >= :start_date";
            $params['start_date'] = $startDate;
        }

        if (!empty($endDate)) {
            $conditions[] = "DATE(r.submitted_at) <= :end_date";
            $params['end_date'] = $endDate;
        }

        if (!empty($search)) {
            $conditions[] = "(r.tracking_number LIKE :search 
                             OR s.student_number LIKE :search 
                             OR s.last_name LIKE :search 
                             OR s.first_name LIKE :search 
                             OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search 
                             OR d.document_name LIKE :search 
                             OR r.purpose LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Validate sort field
        $allowedSorts = [
            'submitted_at'    => 'r.submitted_at',
            'tracking_number' => 'r.tracking_number',
            'student_number'  => 's.student_number',
            'last_name'       => 's.last_name',
            'current_status'  => 'r.current_status',
            'document_name'   => 'd.document_name'
        ];
        $sortColumn = $allowedSorts[$sortField] ?? 'r.submitted_at';
        $direction = strtoupper($sortDirection) === 'ASC' ? 'ASC' : 'DESC';

        // Count total
        $countSql = "SELECT COUNT(*) as total 
                     FROM document_requests r
                     INNER JOIN students s ON r.student_id = s.student_id
                     INNER JOIN documents d ON r.document_id = d.document_id
                     LEFT JOIN (
                         SELECT e1.* FROM enrollment_records e1
                         INNER JOIN (
                             SELECT student_id, MAX(enrollment_id) as max_id 
                             FROM enrollment_records GROUP BY student_id
                         ) e2 ON e1.enrollment_id = e2.max_id
                     ) e ON s.student_id = e.student_id
                     {$whereClause}";
        $totalRow = $this->fetchOne($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);

        // Fetch records
        $sql = "SELECT r.*, 
                       s.student_number, s.last_name, s.first_name, s.middle_name, s.email,
                       d.document_code, d.document_name, d.processing_days, d.fee_amount, d.is_digital_allowed,
                       u.username as assigned_personnel_name,
                       e.program, e.year_level
                FROM document_requests r
                INNER JOIN students s ON r.student_id = s.student_id
                INNER JOIN documents d ON r.document_id = d.document_id
                LEFT JOIN users u ON r.assigned_personnel_id = u.user_id
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id 
                        FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                {$whereClause}
                ORDER BY {$sortColumn} {$direction}
                LIMIT {$limit} OFFSET {$offset}";

        $records = $this->fetchAll($sql, $params);

        return [
            'records' => $records,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => ceil($total / max(1, $limit))
        ];
    }

    /**
     * Insert new document request
     */
    public function create(array $data): int {
        $sql = "INSERT INTO document_requests (
                    tracking_number, student_id, document_id, copies, purpose, 
                    release_method, preferred_claiming_date, additional_instructions, 
                    current_status, assigned_personnel_id, payment_status, amount_due, amount_paid, 
                    submitted_at, updated_at
                ) VALUES (
                    :tracking_number, :student_id, :document_id, :copies, :purpose, 
                    :release_method, :preferred_claiming_date, :additional_instructions, 
                    :current_status, :assigned_personnel_id, :payment_status, :amount_due, :amount_paid, 
                    NOW(), NOW()
                )";

        $this->execute($sql, [
            'tracking_number'         => $data['tracking_number'],
            'student_id'              => $data['student_id'],
            'document_id'             => $data['document_id'],
            'copies'                  => (int)($data['copies'] ?? 1),
            'purpose'                 => $data['purpose'],
            'release_method'          => $data['release_method'] ?? 'Personal Claiming',
            'preferred_claiming_date' => $data['preferred_claiming_date'] ?? null,
            'additional_instructions' => $data['additional_instructions'] ?? null,
            'current_status'          => $data['current_status'] ?? 'REQUEST SUBMITTED',
            'assigned_personnel_id'   => $data['assigned_personnel_id'] ?? null,
            'payment_status'          => $data['payment_status'] ?? 'Unpaid',
            'amount_due'              => (float)($data['amount_due'] ?? 0.00),
            'amount_paid'             => (float)($data['amount_paid'] ?? 0.00),
        ]);

        return $this->lastInsertId();
    }

    /**
     * Update request status and timestamp
     */
    public function updateStatus(int $requestId, string $newStatus, array $additionalUpdates = []): bool {
        $set = ['current_status = :status'];
        $params = ['id' => $requestId, 'status' => $newStatus];

        if (isset($additionalUpdates['assigned_personnel_id'])) {
            $set[] = 'assigned_personnel_id = :personnel_id';
            $params['personnel_id'] = $additionalUpdates['assigned_personnel_id'];
        }

        if (isset($additionalUpdates['payment_status'])) {
            $set[] = 'payment_status = :payment_status';
            $params['payment_status'] = $additionalUpdates['payment_status'];
        }

        if (isset($additionalUpdates['amount_paid'])) {
            $set[] = 'amount_paid = :amount_paid';
            $params['amount_paid'] = $additionalUpdates['amount_paid'];
        }

        if (isset($additionalUpdates['verified_at'])) {
            $set[] = 'verified_at = :verified_at';
            $params['verified_at'] = $additionalUpdates['verified_at'];
        }

        if (isset($additionalUpdates['processing_started_at'])) {
            $set[] = 'processing_started_at = :processing_started_at';
            $params['processing_started_at'] = $additionalUpdates['processing_started_at'];
        }

        if (isset($additionalUpdates['processing_completed_at'])) {
            $set[] = 'processing_completed_at = :processing_completed_at';
            $params['processing_completed_at'] = $additionalUpdates['processing_completed_at'];
        }

        if (isset($additionalUpdates['completed_at'])) {
            $set[] = 'completed_at = :completed_at';
            $params['completed_at'] = $additionalUpdates['completed_at'];
        }

        if (isset($additionalUpdates['released_at'])) {
            $set[] = 'released_at = :released_at';
            $params['released_at'] = $additionalUpdates['released_at'];
        }

        $sql = "UPDATE document_requests SET " . implode(', ', $set) . ", updated_at = NOW() WHERE request_id = :id";
        return $this->execute($sql, $params);
    }
}
