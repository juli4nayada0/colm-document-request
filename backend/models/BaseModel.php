<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Base Data Model
 */

declare(strict_types=1);

namespace Colm\Models;

use Colm\Config\Database;
use PDO;

abstract class BaseModel {
    protected PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Helper to fetch all rows
     */
    protected function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Helper to fetch single row
     */
    protected function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Helper to execute insert/update/delete
     */
    protected function execute(string $sql, array $params = []): bool {
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Helper to get last inserted ID
     */
    protected function lastInsertId(): int {
        return (int)$this->db->lastInsertId();
    }
}
