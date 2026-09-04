<?php
/**
 * COLM Registrar Document Request and Tracking System
 * AuditLog Model (Append-Only)
 */

declare(strict_types=1);

namespace Colm\Models;

class AuditLog extends BaseModel {
    /**
     * Get paginated audit logs with search and filtering
     */
    public function getList(
        int $page = 1,
        int $limit = 30,
        ?string $action = null,
        ?string $entity = null,
        ?int $userId = null,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $search = null
    ): array {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];

        if (!empty($action)) {
            $conditions[] = "a.action = :action";
            $params['action'] = $action;
        }

        if (!empty($entity)) {
            $conditions[] = "a.entity_affected = :entity";
            $params['entity'] = $entity;
        }

        if ($userId !== null) {
            $conditions[] = "a.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        if (!empty($startDate)) {
            $conditions[] = "DATE(a.created_at) >= :start_date";
            $params['start_date'] = $startDate;
        }

        if (!empty($endDate)) {
            $conditions[] = "DATE(a.created_at) <= :end_date";
            $params['end_date'] = $endDate;
        }

        if (!empty($search)) {
            $conditions[] = "(a.action LIKE :search OR a.entity_affected LIKE :search OR a.entity_id LIKE :search OR u.username LIKE :search OR a.ip_address LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM audit_logs a LEFT JOIN users u ON a.user_id = u.user_id {$whereClause}";
        $totalRow = $this->fetchOne($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);

        // Fetch records
        $sql = "SELECT a.*, u.username, u.role 
                FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.user_id 
                {$whereClause} 
                ORDER BY a.log_id DESC 
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
     * Append an immutable audit record
     */
    public function log(
        ?int $userId,
        string $action,
        string $entityAffected,
        string $entityId,
        mixed $prevState = null,
        mixed $newState = null,
        string $ipAddress = '127.0.0.1',
        string $userAgent = 'Unknown'
    ): int {
        $sql = "INSERT INTO audit_logs (user_id, action, entity_affected, entity_id, previous_state, new_state, ip_address, user_agent, created_at)
                VALUES (:user_id, :action, :entity, :entity_id, :prev_state, :new_state, :ip, :user_agent, NOW())";
        
        $this->execute($sql, [
            'user_id'    => $userId,
            'action'     => $action,
            'entity'     => $entityAffected,
            'entity_id'  => $entityId,
            'prev_state' => $prevState !== null ? json_encode($prevState, JSON_UNESCAPED_UNICODE) : null,
            'new_state'  => $newState !== null ? json_encode($newState, JSON_UNESCAPED_UNICODE) : null,
            'ip'         => $ipAddress,
            'user_agent' => $userAgent
        ]);

        return $this->lastInsertId();
    }
}
