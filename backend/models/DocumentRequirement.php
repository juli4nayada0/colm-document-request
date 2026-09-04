<?php
/**
 * COLM Registrar Document Request and Tracking System
 * DocumentRequirement Model
 */

declare(strict_types=1);

namespace Colm\Models;

class DocumentRequirement extends BaseModel {
    /**
     * Get requirements for a specific document
     */
    public function getByDocumentId(int $documentId, bool $activeOnly = false): array {
        $where = $activeOnly ? "AND is_active = 1" : "";
        $sql = "SELECT * FROM document_requirements WHERE document_id = :id {$where} ORDER BY is_required DESC, requirement_name ASC";
        return $this->fetchAll($sql, ['id' => $documentId]);
    }

    /**
     * Find requirement by ID
     */
    public function findById(int $requirementId): ?array {
        $sql = "SELECT * FROM document_requirements WHERE requirement_id = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $requirementId]);
    }

    /**
     * Create requirement
     */
    public function create(array $data): int {
        $sql = "INSERT INTO document_requirements (document_id, requirement_name, description, is_required, allowed_file_types, max_file_size_mb, is_active, created_at, updated_at)
                VALUES (:document_id, :requirement_name, :description, :is_required, :allowed_file_types, :max_file_size_mb, :is_active, NOW(), NOW())";
        
        $this->execute($sql, [
            'document_id'        => $data['document_id'],
            'requirement_name'   => $data['requirement_name'],
            'description'        => $data['description'] ?? null,
            'is_required'        => (int)($data['is_required'] ?? 1),
            'allowed_file_types' => $data['allowed_file_types'] ?? 'pdf,jpg,jpeg,png',
            'max_file_size_mb'   => (int)($data['max_file_size_mb'] ?? 5),
            'is_active'          => (int)($data['is_active'] ?? 1)
        ]);

        return $this->lastInsertId();
    }

    /**
     * Update requirement
     */
    public function update(int $requirementId, array $data): bool {
        $allowed = ['requirement_name', 'description', 'is_required', 'allowed_file_types', 'max_file_size_mb', 'is_active'];
        $set = [];
        $params = ['requirement_id' => $requirementId];

        foreach ($data as $key => $val) {
            if (in_array($key, $allowed, true)) {
                $set[] = "{$key} = :{$key}";
                $params[$key] = $val;
            }
        }

        if (empty($set)) {
            return false;
        }

        $sql = "UPDATE document_requirements SET " . implode(', ', $set) . ", updated_at = NOW() WHERE requirement_id = :requirement_id";
        return $this->execute($sql, $params);
    }
}
