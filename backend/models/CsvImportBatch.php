<?php
/**
 * COLM Registrar Document Request and Tracking System
 * CsvImportBatch Model
 */

declare(strict_types=1);

namespace Colm\Models;

class CsvImportBatch extends BaseModel {
    /**
     * Get import batch by ID
     */
    public function findById(int $batchId): ?array {
        $sql = "SELECT b.*, u.username as uploader_username 
                FROM csv_import_batches b 
                LEFT JOIN users u ON b.uploaded_by = u.user_id 
                WHERE b.batch_id = :id 
                LIMIT 1";
        return $this->fetchOne($sql, ['id' => $batchId]);
    }

    /**
     * Get recent import batches
     */
    public function getList(int $limit = 20): array {
        $sql = "SELECT b.*, u.username as uploader_username 
                FROM csv_import_batches b 
                LEFT JOIN users u ON b.uploaded_by = u.user_id 
                ORDER BY b.imported_at DESC 
                LIMIT {$limit}";
        return $this->fetchAll($sql);
    }

    /**
     * Create import batch record
     */
    public function create(array $data): int {
        $sql = "INSERT INTO csv_import_batches (uploaded_by, filename, total_rows, successful_rows, failed_rows, duplicate_rows, imported_at)
                VALUES (:uploaded_by, :filename, :total_rows, :successful_rows, :failed_rows, :duplicate_rows, NOW())";
        
        $this->execute($sql, [
            'uploaded_by'     => $data['uploaded_by'],
            'filename'        => $data['filename'],
            'total_rows'      => (int)($data['total_rows'] ?? 0),
            'successful_rows' => (int)($data['successful_rows'] ?? 0),
            'failed_rows'     => (int)($data['failed_rows'] ?? 0),
            'duplicate_rows'  => (int)($data['duplicate_rows'] ?? 0),
        ]);

        return $this->lastInsertId();
    }
}
