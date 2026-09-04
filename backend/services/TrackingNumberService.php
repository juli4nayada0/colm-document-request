<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Tracking Number Generation Service (Server-Side Sequential REG-YYYY-XXXXXX)
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Config\Database;
use PDO;
use RuntimeException;

class TrackingNumberService {
    /**
     * Generate next sequential tracking number within an active or internal transaction
     * Format: REG-YYYY-XXXXXX (e.g. REG-2026-000125)
     */
    public static function generateNextTrackingNumber(?int $year = null): string {
        $year = $year ?? (int)date('Y');
        $db = Database::getConnection();

        $ownsTransaction = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $ownsTransaction = true;
        }

        try {
            // Lock and fetch current sequence for the year
            $stmt = $db->prepare("SELECT last_sequence FROM tracking_sequences WHERE year_val = :year FOR UPDATE");
            $stmt->execute(['year' => $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $nextSeq = (int)$row['last_sequence'] + 1;
                $updateStmt = $db->prepare("UPDATE tracking_sequences SET last_sequence = :seq, updated_at = NOW() WHERE year_val = :year");
                $updateStmt->execute(['seq' => $nextSeq, 'year' => $year]);
            } else {
                // If sequence not yet initialized for this year, check current max request ID as baseline
                $checkStmt = $db->query("SELECT MAX(request_id) as max_id FROM document_requests");
                $checkRow = $checkStmt->fetch(PDO::FETCH_ASSOC);
                $baseline = (int)($checkRow['max_id'] ?? 0);
                $nextSeq = max(1, $baseline + 1);

                $insertStmt = $db->prepare("INSERT INTO tracking_sequences (year_val, last_sequence, updated_at) VALUES (:year, :seq, NOW())");
                $insertStmt->execute(['year' => $year, 'seq' => $nextSeq]);
            }

            if ($ownsTransaction) {
                $db->commit();
            }

            return sprintf('REG-%04d-%06d', $year, $nextSeq);
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw new RuntimeException('Failed to generate unique sequential tracking number: ' . $e->getMessage(), 500);
        }
    }
}
