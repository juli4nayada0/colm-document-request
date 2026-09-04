-- Complete document request workflow fields and constraints.
USE `colm_rdrts_db`;

SET @recipient_name_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'document_releases'
      AND column_name = 'recipient_name'
);
SET @recipient_name_sql = IF(
    @recipient_name_exists = 0,
    'ALTER TABLE `document_releases` ADD COLUMN `recipient_name` VARCHAR(150) NULL AFTER `recipient_type`',
    'SELECT 1'
);
PREPARE recipient_name_statement FROM @recipient_name_sql;
EXECUTE recipient_name_statement;
DEALLOCATE PREPARE recipient_name_statement;

SET @processing_completed_exists = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'document_requests'
      AND column_name = 'processing_completed_at'
);
SET @processing_completed_sql = IF(
    @processing_completed_exists = 0,
    'ALTER TABLE `document_requests` ADD COLUMN `processing_completed_at` DATETIME NULL AFTER `processing_started_at`',
    'SELECT 1'
);
PREPARE processing_completed_statement FROM @processing_completed_sql;
EXECUTE processing_completed_statement;
DEALLOCATE PREPARE processing_completed_statement;

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

ALTER TABLE `document_requests`
    MODIFY `copies` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    MODIFY `amount_due` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    MODIFY `amount_paid` DECIMAL(10, 2) NOT NULL DEFAULT 0.00;

ALTER TABLE `payments`
    MODIFY `payment_method` ENUM('Cash', 'Official Receipt (Cashier)') NOT NULL DEFAULT 'Cash';
