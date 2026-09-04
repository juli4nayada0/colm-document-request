<?php
/**
 * COLM Registrar Document Request and Tracking System
 * CsvImportError Model
 */

declare(strict_types=1);

namespace Colm\Models;

class CsvImportError extends BaseModel {
    /**
     * Get error rows for a batch
     */
    public function getByBatchId(int $batchId): array {
        $sql = "SELECT * FROM csv_import_errors WHERE batch_id = :id ORDER BY row_number ASC";
        return $this->fetchAll($sql, ['id' => $batchId]);
    }

    /**
     * Log an import row error
     */
    public function log(int $batchId, int $rowNumber, string $fieldName, string $errorMessage, ?string $rawValue = null): int {
        $sql = "INSERT INTO csv_import_errors (batch_id, row_number, field_name, error_message, raw_value)
                VALUES (:batch_id, :row_number, :field_name, :error_message, :raw_value)";
        
        $this->execute($sql, [
            'batch_id'      => $batchId,
            'row_number'    => $rowNumber,
            'field_name'    => $fieldName,
            'error_message' => $errorMessage,
            'raw_value'     => $rawValue
        ]);

        return $this->lastInsertId();
    }
}
