<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Organizational Chart Model
 */

declare(strict_types=1);

namespace Colm\Models;

class OrgChart extends BaseModel {

    /**
     * Get all org chart members ordered by sort_order then id
     */
    public function getAll(): array {
        $sql = "SELECT * FROM org_chart_members ORDER BY sort_order ASC, member_id ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Find a single member by ID
     */
    public function findById(int $id): ?array {
        $sql = "SELECT * FROM org_chart_members WHERE member_id = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Create a new org chart member
     */
    public function create(array $data): int {
        $sql = "INSERT INTO org_chart_members
                    (full_name, position_title, department, role_level, sort_order, is_active, created_at, updated_at)
                VALUES
                    (:full_name, :position_title, :department, :role_level, :sort_order, :is_active, NOW(), NOW())";

        $this->execute($sql, [
            'full_name'      => $data['full_name'],
            'position_title' => $data['position_title'],
            'department'     => $data['department']  ?? null,
            'role_level'     => $data['role_level']  ?? 'staff',
            'sort_order'     => (int)($data['sort_order'] ?? 99),
            'is_active'      => (int)($data['is_active']  ?? 1),
        ]);

        return $this->lastInsertId();
    }

    /**
     * Update an existing org chart member
     */
    public function update(int $id, array $data): bool {
        $allowed = ['full_name', 'position_title', 'department', 'role_level', 'sort_order', 'is_active'];
        $set = [];
        $params = ['member_id' => $id];

        foreach ($data as $key => $val) {
            if (in_array($key, $allowed, true)) {
                $set[] = "{$key} = :{$key}";
                $params[$key] = $val;
            }
        }

        if (empty($set)) {
            return false;
        }

        $sql = "UPDATE org_chart_members SET " . implode(', ', $set) . ", updated_at = NOW() WHERE member_id = :member_id";
        return $this->execute($sql, $params);
    }

    /**
     * Delete an org chart member
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM org_chart_members WHERE member_id = :id";
        return $this->execute($sql, ['id' => $id]);
    }
}
