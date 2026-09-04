<?php
/**
 * COLM Registrar Document Request and Tracking System
 * AcademicRecord Model
 */

declare(strict_types=1);

namespace Colm\Models;

class AcademicRecord extends BaseModel {
    /**
     * Get academic verification metadata for a student
     */
    public function getByStudentId(int $studentId): ?array {
        $sql = "SELECT * FROM academic_records WHERE student_id = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $studentId]);
    }

    /**
     * Create or update academic record
     */
    public function saveRecord(int $studentId, array $data): bool {
        $existing = $this->getByStudentId($studentId);

        if ($existing) {
            $sql = "UPDATE academic_records SET 
                    graduation_status = :grad_status, 
                    record_reference = :ref, 
                    verification_status = :verif_status, 
                    remarks = :remarks, 
                    updated_at = NOW() 
                    WHERE student_id = :student_id";
            return $this->execute($sql, [
                'grad_status'  => $data['graduation_status'] ?? $existing['graduation_status'],
                'ref'          => $data['record_reference'] ?? $existing['record_reference'],
                'verif_status' => $data['verification_status'] ?? $existing['verification_status'],
                'remarks'      => $data['remarks'] ?? $existing['remarks'],
                'student_id'   => $studentId
            ]);
        }

        $sql = "INSERT INTO academic_records (student_id, graduation_status, record_reference, verification_status, remarks, created_at, updated_at)
                VALUES (:student_id, :grad_status, :ref, :verif_status, :remarks, NOW(), NOW())";
        return $this->execute($sql, [
            'student_id'   => $studentId,
            'grad_status'  => $data['graduation_status'] ?? 'Undergraduate',
            'ref'          => $data['record_reference'] ?? null,
            'verif_status' => $data['verification_status'] ?? 'Pending Verification',
            'remarks'      => $data['remarks'] ?? null
        ]);
    }
}
