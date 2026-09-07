-- ==============================================================================
-- COLM REGISTRAR DOCUMENT REQUEST AND TRACKING SYSTEM
-- Master Database Schema: schema.sql
-- Target Database: colm_rdrts_db
-- Target DBMS: MySQL 8.0+ / MariaDB 10.4+
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `colm_rdrts_db` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `colm_rdrts_db`;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('Student', 'Personnel', 'Registrar', 'Admin') NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_is_active` (`is_active`),
    INDEX `idx_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. STUDENTS TABLE
CREATE TABLE IF NOT EXISTS `students` (
    `student_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL UNIQUE,
    `student_number` VARCHAR(30) NOT NULL UNIQUE,
    `last_name` VARCHAR(80) NOT NULL,
    `first_name` VARCHAR(80) NOT NULL,
    `middle_name` VARCHAR(80) NULL,
    `sex` ENUM('Male', 'Female', 'Other') NOT NULL,
    `dob` DATE NOT NULL,
    `contact_number` VARCHAR(30) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `address` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_students_student_number` (`student_number`),
    INDEX `idx_students_last_first` (`last_name`, `first_name`),
    INDEX `idx_students_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ENROLLMENT RECORDS TABLE
CREATE TABLE IF NOT EXISTS `enrollment_records` (
    `enrollment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `program` VARCHAR(150) NOT NULL,
    `education_level` VARCHAR(30) NULL,
    `major` VARCHAR(100) NULL DEFAULT 'General',
    `year_level` VARCHAR(30) NOT NULL,
    `section` VARCHAR(80) NULL,
    `academic_year` VARCHAR(20) NOT NULL,
    `semester` VARCHAR(30) NOT NULL,
    `enrollment_status` ENUM('Officially Enrolled', 'Temporarily Enrolled', 'Withdrawn', 'Graduated', 'On Leave') NOT NULL DEFAULT 'Officially Enrolled',
    `date_enrolled` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_enrollment_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_enrollment_student_id` (`student_id`),
    INDEX `idx_enrollment_program` (`program`),
    INDEX `idx_enrollment_acad_year` (`academic_year`),
    INDEX `idx_enrollment_year_level` (`year_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ACADEMIC RECORDS TABLE
CREATE TABLE IF NOT EXISTS `academic_records` (
    `record_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `graduation_status` ENUM('Undergraduate', 'Candidate for Graduation', 'Graduated', 'Dismissed', 'Transferred') NOT NULL DEFAULT 'Undergraduate',
    `record_reference` VARCHAR(100) NULL,
    `verification_status` ENUM('Verified', 'Pending Verification', 'Flagged', 'Incomplete') NOT NULL DEFAULT 'Pending Verification',
    `remarks` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_academic_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_academic_student_id` (`student_id`),
    INDEX `idx_academic_verif_status` (`verification_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. DOCUMENTS CATALOG TABLE
CREATE TABLE IF NOT EXISTS `documents` (
    `document_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `document_code` VARCHAR(30) NOT NULL UNIQUE,
    `document_name` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `typical_purpose` TEXT NOT NULL,
    `basic_requirements` TEXT NOT NULL,
    `processing_days` SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    `fee_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `is_digital_allowed` TINYINT(1) NOT NULL DEFAULT 0,
    `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_documents_code` (`document_code`),
    INDEX `idx_documents_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. DOCUMENT REQUIREMENTS TABLE
CREATE TABLE IF NOT EXISTS `document_requirements` (
    `requirement_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `document_id` INT UNSIGNED NOT NULL,
    `requirement_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_required` TINYINT(1) NOT NULL DEFAULT 1,
    `allowed_file_types` VARCHAR(100) NOT NULL DEFAULT 'pdf,jpg,jpeg,png',
    `max_file_size_mb` SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_requirements_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_requirements_document_id` (`document_id`),
    INDEX `idx_requirements_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. DOCUMENT REQUESTS TABLE
CREATE TABLE IF NOT EXISTS `document_requests` (
    `request_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tracking_number` VARCHAR(30) NOT NULL UNIQUE,
    `student_id` INT UNSIGNED NOT NULL,
    `document_id` INT UNSIGNED NOT NULL,
    `copies` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `purpose` VARCHAR(255) NOT NULL,
    `release_method` ENUM('Personal Claiming', 'Authorized Representative', 'Digital Copy', 'Other Approved Delivery') NOT NULL DEFAULT 'Personal Claiming',
    `preferred_claiming_date` DATE NULL,
    `additional_instructions` TEXT NULL,
    `current_status` ENUM(
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
    ) NOT NULL DEFAULT 'PENDING PAYMENT',
    `assigned_personnel_id` INT UNSIGNED NULL,
    `payment_status` ENUM('Unpaid', 'For Verification', 'Paid') NOT NULL DEFAULT 'Unpaid',
    `amount_due` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `amount_paid` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `verified_at` DATETIME NULL,
    `processing_started_at` DATETIME NULL,
    `processing_completed_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `released_at` DATETIME NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_requests_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_requests_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`document_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_requests_personnel` FOREIGN KEY (`assigned_personnel_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_requests_tracking` (`tracking_number`),
    INDEX `idx_requests_student_id` (`student_id`),
    INDEX `idx_requests_document_id` (`document_id`),
    INDEX `idx_requests_status` (`current_status`),
    INDEX `idx_requests_payment_status` (`payment_status`),
    INDEX `idx_requests_assigned_personnel` (`assigned_personnel_id`),
    INDEX `idx_requests_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. REQUEST REQUIREMENTS TABLE
CREATE TABLE IF NOT EXISTS `request_requirements` (
    `request_requirement_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT UNSIGNED NOT NULL,
    `requirement_id` INT UNSIGNED NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `verification_status` ENUM('Pending', 'Verified', 'Rejected', 'Resubmission Requested') NOT NULL DEFAULT 'Pending',
    `verified_by` INT UNSIGNED NULL,
    `verified_at` DATETIME NULL,
    `remarks` TEXT NULL,
    `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_req_req_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_req_req_requirement` FOREIGN KEY (`requirement_id`) REFERENCES `document_requirements` (`requirement_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_req_req_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_req_req_request_id` (`request_id`),
    INDEX `idx_req_req_status` (`verification_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. REQUEST STATUS LOGS TABLE
CREATE TABLE IF NOT EXISTS `request_status_logs` (
    `status_log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT UNSIGNED NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `previous_status` VARCHAR(50) NOT NULL,
    `new_status` VARCHAR(50) NOT NULL,
    `remarks` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_status_logs_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_status_logs_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_status_logs_request_id` (`request_id`),
    INDEX `idx_status_logs_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. PAYMENTS TABLE
CREATE TABLE IF NOT EXISTS `payments` (
    `payment_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT UNSIGNED NOT NULL,
    `reference_number` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('Cash', 'Official Receipt (Cashier)') NOT NULL DEFAULT 'Cash',
    `payment_status` ENUM('Unpaid', 'For Verification', 'Paid', 'Rejected') NOT NULL DEFAULT 'For Verification',
    `proof_file` VARCHAR(255) NULL,
    `original_filename` VARCHAR(255) NULL,
    `verified_by` INT UNSIGNED NULL,
    `verified_at` DATETIME NULL,
    `payment_date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_payments_request_id` (`request_id`),
    INDEX `idx_payments_reference` (`reference_number`),
    INDEX `idx_payments_status` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. DOCUMENT RELEASES TABLE
CREATE TABLE IF NOT EXISTS `document_releases` (
    `release_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `request_id` INT UNSIGNED NOT NULL,
    `released_by` INT UNSIGNED NOT NULL,
    `release_method` ENUM('Personal Claiming', 'Authorized Representative', 'Digital Copy', 'Other Approved Delivery') NOT NULL,
    `recipient_type` ENUM('Student', 'Representative') NOT NULL DEFAULT 'Student',
    `recipient_name` VARCHAR(150) NULL,
    `representative_name` VARCHAR(120) NULL,
    `representative_relationship` VARCHAR(80) NULL,
    `authorization_reference` VARCHAR(120) NULL,
    `identification_verified` TINYINT(1) NOT NULL DEFAULT 1,
    `claiming_date` DATE NOT NULL,
    `remarks` TEXT NULL,
    `released_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_releases_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_releases_issuer` FOREIGN KEY (`released_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_releases_request_id` (`request_id`),
    INDEX `idx_releases_released_at` (`released_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `notifications` (
    `notification_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `request_id` INT UNSIGNED NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `notification_type` ENUM('info', 'success', 'warning', 'error') NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_notifications_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`request_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_notifications_user_id` (`user_id`),
    INDEX `idx_notifications_is_read` (`is_read`),
    INDEX `idx_notifications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. AUDIT LOGS TABLE
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `log_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_affected` VARCHAR(80) NOT NULL,
    `entity_id` VARCHAR(50) NOT NULL,
    `previous_state` JSON NULL,
    `new_state` JSON NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
    INDEX `idx_audit_user_id` (`user_id`),
    INDEX `idx_audit_entity` (`entity_affected`, `entity_id`),
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. CSV IMPORT BATCHES TABLE
CREATE TABLE IF NOT EXISTS `csv_import_batches` (
    `batch_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `uploaded_by` INT UNSIGNED NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `successful_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `failed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `duplicate_rows` INT UNSIGNED NOT NULL DEFAULT 0,
    `imported_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_import_batches_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_import_batches_user` (`uploaded_by`),
    INDEX `idx_import_batches_date` (`imported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. CSV IMPORT ERRORS TABLE
CREATE TABLE IF NOT EXISTS `csv_import_errors` (
    `error_id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `batch_id` INT UNSIGNED NOT NULL,
    `row_number` INT UNSIGNED NOT NULL,
    `field_name` VARCHAR(80) NOT NULL,
    `error_message` TEXT NOT NULL,
    `raw_value` TEXT NULL,
    CONSTRAINT `fk_import_errors_batch` FOREIGN KEY (`batch_id`) REFERENCES `csv_import_batches` (`batch_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_import_errors_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. TRACKING NUMBER SEQUENCE TABLE
CREATE TABLE IF NOT EXISTS `tracking_sequences` (
    `year_val` SMALLINT UNSIGNED NOT NULL PRIMARY KEY,
    `last_sequence` INT UNSIGNED NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. SYSTEM SETTINGS TABLE
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(80) NOT NULL PRIMARY KEY,
    `setting_value` TEXT NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `updated_by` INT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
