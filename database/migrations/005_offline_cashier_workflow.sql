-- Add the exact offline cashier workflow statuses without removing legacy records.
USE `colm_rdrts_db`;

ALTER TABLE `document_requests`
MODIFY `current_status` ENUM(
    'REQUEST SUBMITTED',
    'PENDING PAYMENT',
    'PAID',
    'FOR PROCESSING',
    'FOR VERIFICATION',
    'FOR PAYMENT',
    'PROCESSING',
    'FOR REVIEW/APPROVAL',
    'READY FOR RELEASE',
    'RELEASED',
    'COMPLETED',
    'ON HOLD',
    'INCOMPLETE',
    'REJECTED/CANCELLED',
    'FOR CORRECTION'
) NOT NULL DEFAULT 'PENDING PAYMENT';

-- Normalize legacy unpaid requests so the dashboard reflects the offline process.
UPDATE `document_requests`
SET `current_status` = 'PENDING PAYMENT'
WHERE `payment_status` <> 'Paid'
    AND `current_status` IN ('REQUEST SUBMITTED', 'FOR VERIFICATION', 'FOR PAYMENT', 'PROCESSING');

UPDATE `document_requests`
SET `payment_status` = 'Unpaid', `amount_paid` = 0.00
WHERE `current_status` = 'PENDING PAYMENT' AND `payment_status` <> 'Paid';

UPDATE `payments` p
INNER JOIN `document_requests` r ON r.request_id = p.request_id
SET p.payment_status = 'Rejected', p.verified_at = NOW()
WHERE r.current_status = 'PENDING PAYMENT' AND p.payment_status = 'For Verification';

-- Remove orphaned release records and keep only the first valid release per request.
DELETE dr FROM `document_releases` dr
LEFT JOIN `document_requests` r ON r.request_id = dr.request_id
WHERE r.request_id IS NULL OR r.current_status NOT IN ('READY FOR RELEASE', 'RELEASED', 'COMPLETED');

DELETE duplicate_release FROM `document_releases` duplicate_release
INNER JOIN `document_releases` original_release
    ON original_release.request_id = duplicate_release.request_id
   AND original_release.release_id < duplicate_release.release_id;

UPDATE `document_requests` r
INNER JOIN (
    SELECT request_id, MAX(released_at) AS final_released_at
    FROM `document_releases`
    GROUP BY request_id
) dr ON dr.request_id = r.request_id
SET r.current_status = 'COMPLETED',
    r.released_at = dr.final_released_at,
    r.completed_at = COALESCE(r.completed_at, dr.final_released_at);

UPDATE `document_requests`
SET `completed_at` = NULL
WHERE `current_status` = 'READY FOR RELEASE' AND `released_at` IS NULL;

SET @release_index_exists = (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'document_releases'
      AND index_name = 'uq_releases_request_id'
);
SET @release_index_sql = IF(
    @release_index_exists = 0,
    'ALTER TABLE `document_releases` ADD UNIQUE KEY `uq_releases_request_id` (`request_id`)',
    'SELECT 1'
);
PREPARE release_index_statement FROM @release_index_sql;
EXECUTE release_index_statement;
DEALLOCATE PREPARE release_index_statement;
