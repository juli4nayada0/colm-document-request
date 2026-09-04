<?php
/**
 * COLM Registrar Document Request and Tracking System
 * RequestRequirement Model
 */

declare(strict_types=1);

namespace Colm\Models;

class RequestRequirement extends BaseModel {
    /**
     * Get uploaded requirements for a specific request
     */
    public function getByRequestId(int $requestId): array {
        $sql = "SELECT rr.*, dr.requirement_name, dr.is_required, dr.allowed_file_types, u.username as verifier_name
                FROM request_requirements rr
                INNER JOIN document_requirements dr ON rr.requirement_id = dr.requirement_id
                LEFT JOIN users u ON rr.verified_by = u.user_id
                WHERE rr.request_id = :id
                ORDER BY dr.is_required DESC, rr.uploaded_at ASC";
        return $this->fetchAll($sql, ['id' => $requestId]);
    }

    /**
     * Determine whether every required uploaded requirement is verified.
     */
    public function hasUnverifiedRequired(int $requestId): bool {
        $sql = "SELECT COUNT(*) AS pending_count
                FROM document_requirements dr
                LEFT JOIN request_requirements rr
                    ON rr.requirement_id = dr.requirement_id
                    AND rr.request_id = :request_id
                WHERE dr.document_id = (
                    SELECT document_id FROM document_requests WHERE request_id = :request_id_doc
                )
                AND dr.is_required = 1
                AND (rr.request_requirement_id IS NULL OR rr.verification_status <> 'Verified')";

        $row = $this->fetchOne($sql, ['request_id' => $requestId, 'request_id_doc' => $requestId]);

        return (int)($row['pending_count'] ?? 0) > 0;
    }

    /**
     * Find uploaded requirement by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM request_requirements WHERE request_requirement_id = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create uploaded requirement record
     */
    public function create(array $data): int {
        $sql = "INSERT INTO request_requirements (
                    request_id, requirement_id, file_path, original_filename, 
                    stored_filename, mime_type, file_size, verification_status, 
                    uploaded_at
                ) VALUES (
                    :request_id, :requirement_id, :file_path, :original_filename, 
                    :stored_filename, :mime_type, :file_size, :verification_status, 
                    NOW()
                )";
        
        $this->execute($sql, [
            'request_id'          => $data['request_id'],
            'requirement_id'      => $data['requirement_id'],
            'file_path'           => $data['file_path'],
            'original_filename'   => $data['original_filename'],
            'stored_filename'     => $data['stored_filename'],
            'mime_type'           => $data['mime_type'],
            'file_size'           => (int)$data['file_size'],
            'verification_status' => $data['verification_status'] ?? 'Pending'
        ]);

        return $this->lastInsertId();
    }

    /**
     * Verify or reject an uploaded requirement
     */
    public function updateVerification(int $id, string $status, int $verifierUserId, ?string $remarks = null): bool {
        $sql = "UPDATE request_requirements SET 
                verification_status = :status, 
                verified_by = :verifier, 
                verified_at = NOW(), 
                remarks = :remarks 
                WHERE request_requirement_id = :id";
        
        return $this->execute($sql, [
            'id'       => $id,
            'status'   => $status,
            'verifier' => $verifierUserId,
            'remarks'  => $remarks
        ]);
    }
}
