<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Document Catalog Model
 */

declare(strict_types=1);

namespace Colm\Models;

class Document extends BaseModel {
    /**
     * Get list of all documents (with optional filter for active only)
     */
    public function getList(bool $activeOnly = false): array {
        $where = $activeOnly ? "WHERE is_active = 1" : "";
        $sql = "SELECT * FROM documents {$where} ORDER BY document_name ASC";
        return $this->fetchAll($sql);
    }

    /**
     * Find document by ID
     */
    public function findById(int $documentId): ?array {
        $sql = "SELECT * FROM documents WHERE document_id = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $documentId]);
    }

    /**
     * Find document by document code
     */
    public function findByCode(string $code): ?array {
        $sql = "SELECT * FROM documents WHERE document_code = :code LIMIT 1";
        return $this->fetchOne($sql, ['code' => $code]);
    }

    /**
     * Create document type
     */
    public function create(array $data): int {
        $sql = "INSERT INTO documents (document_code, document_name, description, typical_purpose, basic_requirements, processing_days, fee_amount, is_digital_allowed, requires_approval, is_active, created_at, updated_at)
                VALUES (:document_code, :document_name, :description, :typical_purpose, :basic_requirements, :processing_days, :fee_amount, :is_digital_allowed, :requires_approval, :is_active, NOW(), NOW())";
        
        $this->execute($sql, [
            'document_code'      => $data['document_code'],
            'document_name'      => $data['document_name'],
            'description'        => $data['description'],
            'typical_purpose'    => $data['typical_purpose'],
            'basic_requirements' => $data['basic_requirements'],
            'processing_days'    => (int)($data['processing_days'] ?? 3),
            'fee_amount'         => (float)($data['fee_amount'] ?? 0.00),
            'is_digital_allowed' => (int)($data['is_digital_allowed'] ?? 0),
            'requires_approval'  => (int)($data['requires_approval'] ?? 1),
            'is_active'          => (int)($data['is_active'] ?? 1)
        ]);

        return $this->lastInsertId();
    }

    /**
     * Update document type
     */
    public function update(int $documentId, array $data): bool {
        $allowed = ['document_code', 'document_name', 'description', 'typical_purpose', 'basic_requirements', 'processing_days', 'fee_amount', 'is_digital_allowed', 'requires_approval', 'is_active'];
        $set = [];
        $params = ['document_id' => $documentId];

        foreach ($data as $key => $val) {
            if (in_array($key, $allowed, true)) {
                $set[] = "{$key} = :{$key}";
                $params[$key] = $val;
            }
        }

        if (empty($set)) {
            return false;
        }

        $sql = "UPDATE documents SET " . implode(', ', $set) . ", updated_at = NOW() WHERE document_id = :document_id";
        return $this->execute($sql, $params);
    }
}
