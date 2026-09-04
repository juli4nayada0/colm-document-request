<?php
/**
 * COLM Registrar Document Request and Tracking System
 * DocumentRelease Model
 */

declare(strict_types=1);

namespace Colm\Models;

class DocumentRelease extends BaseModel {
    /**
     * Get release record for a request
     */
    public function findByRequestId(int $requestId): ?array {
        $sql = "SELECT dr.*, u.username as released_by_username 
                FROM document_releases dr 
                LEFT JOIN users u ON dr.released_by = u.user_id 
                WHERE dr.request_id = :id 
                ORDER BY dr.release_id DESC 
                LIMIT 1";
        return $this->fetchOne($sql, ['id' => $requestId]);
    }

    /**
     * Record document release
     */
    public function create(array $data): int {
        $sql = "INSERT INTO document_releases (
                    request_id, released_by, release_method, recipient_type, recipient_name,
                    representative_name, representative_relationship, authorization_reference, 
                    identification_verified, claiming_date, remarks, released_at
                ) VALUES (
                    :request_id, :released_by, :release_method, :recipient_type, :recipient_name,
                    :representative_name, :representative_relationship, :authorization_reference, 
                    :identification_verified, :claiming_date, :remarks, NOW()
                )";
        
        $this->execute($sql, [
            'request_id'                  => $data['request_id'],
            'released_by'                 => $data['released_by'],
            'release_method'              => $data['release_method'],
            'recipient_type'              => $data['recipient_type'] ?? 'Student',
            'recipient_name'              => $data['recipient_name'] ?? null,
            'representative_name'         => $data['representative_name'] ?? null,
            'representative_relationship' => $data['representative_relationship'] ?? null,
            'authorization_reference'     => $data['authorization_reference'] ?? null,
            'identification_verified'     => (int)($data['identification_verified'] ?? 1),
            'claiming_date'               => $data['claiming_date'] ?? date('Y-m-d'),
            'remarks'                     => $data['remarks'] ?? null
        ]);

        return $this->lastInsertId();
    }
}
