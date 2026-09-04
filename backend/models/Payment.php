<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Payment Model
 */

declare(strict_types=1);

namespace Colm\Models;

class Payment extends BaseModel {
    /**
     * Find payment by request ID
     */
    public function findByRequestId(int $requestId): ?array {
        $sql = "SELECT p.*, u.username as verifier_username 
                FROM payments p 
                LEFT JOIN users u ON p.verified_by = u.user_id 
                WHERE p.request_id = :id 
                ORDER BY p.payment_id DESC 
                LIMIT 1";
        return $this->fetchOne($sql, ['id' => $requestId]);
    }

    /**
     * Find payment by ID
     */
    public function findById(int $paymentId): ?array {
        $sql = "SELECT p.*, u.username as verifier_username 
                FROM payments p 
                LEFT JOIN users u ON p.verified_by = u.user_id 
                WHERE p.payment_id = :id 
                LIMIT 1";
        return $this->fetchOne($sql, ['id' => $paymentId]);
    }

    /**
     * Create payment record
     */
    public function create(array $data): int {
        $sql = "INSERT INTO payments (
                    request_id, reference_number, amount, payment_method, 
                    payment_status, proof_file, original_filename, payment_date, 
                    created_at, updated_at
                ) VALUES (
                    :request_id, :reference_number, :amount, :payment_method, 
                    :payment_status, :proof_file, :original_filename, :payment_date, 
                    NOW(), NOW()
                )";
        
        $this->execute($sql, [
            'request_id'        => $data['request_id'],
            'reference_number'  => $data['reference_number'],
            'amount'            => (float)$data['amount'],
            'payment_method'    => $data['payment_method'] ?? 'Cash',
            'payment_status'    => $data['payment_status'] ?? 'For Verification',
            'proof_file'        => $data['proof_file'] ?? null,
            'original_filename' => $data['original_filename'] ?? null,
            'payment_date'      => $data['payment_date'] ?? date('Y-m-d'),
        ]);

        return $this->lastInsertId();
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(int $paymentId, string $status, int $verifierUserId): bool {
        $sql = "UPDATE payments SET 
                payment_status = :status, 
                verified_by = :verifier, 
                verified_at = NOW(), 
                updated_at = NOW() 
                WHERE payment_id = :id";
        
        return $this->execute($sql, [
            'id'       => $paymentId,
            'status'   => $status,
            'verifier' => $verifierUserId
        ]);
    }
}
