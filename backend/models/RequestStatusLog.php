<?php
/**
 * COLM Registrar Document Request and Tracking System
 * RequestStatusLog Model
 */

declare(strict_types=1);

namespace Colm\Models;

class RequestStatusLog extends BaseModel {
    /**
     * Get lifecycle status timeline for a request
     */
    public function getTimeline(int $requestId): array {
        $sql = "SELECT sl.*, u.username as changer_username, u.role as changer_role
                FROM request_status_logs sl
                LEFT JOIN users u ON sl.changed_by = u.user_id
                WHERE sl.request_id = :id
                ORDER BY sl.created_at ASC, sl.status_log_id ASC";
        return $this->fetchAll($sql, ['id' => $requestId]);
    }

    /**
     * Append a status change log entry
     */
    public function create(int $requestId, int $changedByUserId, string $prevStatus, string $newStatus, ?string $remarks = null): int {
        $sql = "INSERT INTO request_status_logs (request_id, changed_by, previous_status, new_status, remarks, created_at)
                VALUES (:request_id, :changed_by, :prev_status, :new_status, :remarks, NOW())";
        
        $this->execute($sql, [
            'request_id'   => $requestId,
            'changed_by'   => $changedByUserId,
            'prev_status'  => $prevStatus,
            'new_status'   => $newStatus,
            'remarks'      => $remarks
        ]);

        return $this->lastInsertId();
    }
}
