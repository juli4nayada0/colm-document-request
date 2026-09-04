-- ==============================================================================
-- COLM REGISTRAR DOCUMENT REQUEST AND TRACKING SYSTEM
-- Seed File: 005_test_requests.sql
-- Sample requests, payments, status logs, sequence seed and settings
-- ==============================================================================

USE `colm_rdrts_db`;

-- Initialize Tracking Sequence for 2026
INSERT INTO `tracking_sequences` (`year_val`, `last_sequence`, `updated_at`) VALUES
(2026, 128, NOW())
ON DUPLICATE KEY UPDATE `last_sequence` = GREATEST(`last_sequence`, 128);

-- System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `updated_by`, `updated_at`) VALUES
('institution_name', 'College of Our Lady of Mercy of Pulilan Foundation, Inc.', 'Official Name of the Institution', 1, NOW()),
('institution_address', 'Longos, Pulilan, Bulacan', 'Campus Address', 1, NOW()),
('registrar_office_email', 'registrar@colm.edu.ph', 'Contact Email for Document Queries', 1, NOW()),
('registrar_office_phone', '(044) 123-4567', 'Registrar Hotline Number', 1, NOW()),
('academic_year_current', '2026-2027', 'Active Academic Year', 1, NOW()),
('semester_current', 'First Semester', 'Active Semester', 1, NOW()),
('allow_public_tracking', '1', 'Enable public tracking page without login (sanitized data)', 1, NOW()),
('max_upload_size_mb', '10', 'Global maximum file upload limit in MB', 1, NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 1. Sample Official Request (Prompt Section 64)
-- Student: Juan Santos Dela Cruz (student_id: 1)
-- Document: Certificate of Enrollment (doc_id: 3, fee: 75.00)
INSERT INTO `document_requests` (
    `request_id`, `tracking_number`, `student_id`, `document_id`, `copies`, `purpose`, 
    `release_method`, `preferred_claiming_date`, `additional_instructions`, `current_status`, 
    `assigned_personnel_id`, `payment_status`, `amount_due`, `amount_paid`, 
    `submitted_at`, `verified_at`, `processing_started_at`, `completed_at`, `released_at`, `updated_at`
) VALUES
(
    1,
    'REG-2026-000125',
    1,
    3,
    1,
    'Scholarship Application',
    'Personal Claiming',
    DATE_ADD(CURDATE(), INTERVAL 3 DAY),
    'Need certificate with official dry seal for Provincial Scholarship Office.',
    'FOR VERIFICATION',
    3, -- Assigned to Personnel 1
    'Unpaid',
    75.00,
    0.00,
    '2026-09-02 08:30:00',
    NULL,
    NULL,
    NULL,
    NULL,
    NOW()
),
(
    2,
    'REG-2026-000126',
    1,
    2, -- Certification of Grades
    2,
    'Job Application / Pre-employment evaluation',
    'Digital Copy',
    DATE_ADD(CURDATE(), INTERVAL 2 DAY),
    'Please send certified digital copy via email portal.',
    'PROCESSING',
    3,
    'Paid',
    200.00,
    200.00,
    '2026-09-01 09:15:00',
    '2026-09-01 11:00:00',
    '2026-09-01 14:00:00',
    NULL,
    NULL,
    NOW()
),
(
    3,
    'REG-2026-000127',
    2, -- Maria Clara Santos
    1, -- TOR
    1,
    'Board Examination (PRC Filing)',
    'Authorized Representative',
    DATE_ADD(CURDATE(), INTERVAL 10 DAY),
    'My mother will claim the document once ready.',
    'FOR PAYMENT',
    4, -- Assigned to Personnel 2
    'For Verification',
    350.00,
    0.00,
    '2026-09-01 14:20:00',
    '2026-09-02 09:00:00',
    NULL,
    NULL,
    NULL,
    NOW()
),
(
    4,
    'REG-2026-000128',
    3, -- Mark Bautista Reyes
    6, -- Good Moral
    1,
    'Transfer to another institution',
    'Personal Claiming',
    CURDATE(),
    'For admissions clearance.',
    'READY FOR RELEASE',
    3,
    'Paid',
    100.00,
    100.00,
    '2026-08-28 10:00:00',
    '2026-08-28 11:30:00',
    '2026-08-29 09:00:00',
    '2026-08-31 15:00:00',
    NULL,
    NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Status Logs
INSERT INTO `request_status_logs` (`status_log_id`, `request_id`, `changed_by`, `previous_status`, `new_status`, `remarks`, `created_at`) VALUES
(1, 1, 5, 'NONE', 'REQUEST SUBMITTED', 'Request submitted by student.', '2026-09-02 08:30:00'),
(2, 1, 3, 'REQUEST SUBMITTED', 'FOR VERIFICATION', 'Request received and subject to verification.', '2026-09-02 09:00:00'),
(3, 2, 5, 'NONE', 'REQUEST SUBMITTED', 'Request submitted by student.', '2026-09-01 09:15:00'),
(4, 2, 3, 'REQUEST SUBMITTED', 'FOR VERIFICATION', 'Initial verification started.', '2026-09-01 10:00:00'),
(5, 2, 3, 'FOR VERIFICATION', 'FOR PAYMENT', 'Requirements verified. Assessment amount PHP 200.00.', '2026-09-01 11:00:00'),
(6, 2, 3, 'FOR PAYMENT', 'PROCESSING', 'GCash payment confirmed. Processing grades certification.', '2026-09-01 14:00:00'),
(7, 3, 6, 'NONE', 'REQUEST SUBMITTED', 'Request submitted by student.', '2026-09-01 14:20:00'),
(8, 3, 4, 'REQUEST SUBMITTED', 'FOR VERIFICATION', 'Checking clearance and 2x2 photo.', '2026-09-01 15:30:00'),
(9, 3, 4, 'FOR VERIFICATION', 'FOR PAYMENT', 'Clearance verified. Awaiting fee settlement of PHP 350.00.', '2026-09-02 09:00:00'),
(10, 4, 7, 'NONE', 'REQUEST SUBMITTED', 'Submitted request for Good Moral.', '2026-08-28 10:00:00'),
(11, 4, 3, 'REQUEST SUBMITTED', 'FOR VERIFICATION', 'Verified discipline status with OSA.', '2026-08-28 11:30:00'),
(12, 4, 3, 'FOR VERIFICATION', 'PROCESSING', 'Payment settled via Cashier. Certificate printed.', '2026-08-29 09:00:00'),
(13, 4, 2, 'PROCESSING', 'FOR REVIEW/APPROVAL', 'Reviewed by Registrar for dry seal.', '2026-08-31 10:00:00'),
(14, 4, 2, 'FOR REVIEW/APPROVAL', 'READY FOR RELEASE', 'Signed, sealed, and ready for claiming at Counter 2.', '2026-08-31 15:00:00')
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);

-- Payments
INSERT INTO `payments` (`payment_id`, `request_id`, `reference_number`, `amount`, `payment_method`, `payment_status`, `proof_file`, `original_filename`, `verified_by`, `verified_at`, `payment_date`, `created_at`, `updated_at`) VALUES
(
    1,
    2,
    'GCASH-20260901-889012',
    200.00,
    'GCash',
    'Paid',
    'sample_receipt_gcash.png',
    'gcash_receipt_sept1.png',
    3,
    '2026-09-01 14:00:00',
    '2026-09-01',
    '2026-09-01 13:45:00',
    NOW()
),
(
    2,
    3,
    'MAYA-20260902-114521',
    350.00,
    'Maya',
    'For Verification',
    'sample_receipt_maya.png',
    'maya_transfer_ref.png',
    NULL,
    NULL,
    '2026-09-02',
    '2026-09-02 09:30:00',
    NOW()
),
(
    3,
    4,
    'OR-2026-08945',
    100.00,
    'Official Receipt (Cashier)',
    'Paid',
    NULL,
    NULL,
    3,
    '2026-08-29 09:00:00',
    '2026-08-29',
    '2026-08-29 08:50:00',
    NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Notifications
INSERT INTO `notifications` (`notification_id`, `user_id`, `request_id`, `title`, `message`, `notification_type`, `is_read`, `created_at`) VALUES
(
    1,
    5, -- Juan Dela Cruz
    1,
    'Request Received',
    'Your request REG-2026-000125 for Certificate of Enrollment has been received and is under verification.',
    'info',
    0,
    '2026-09-02 09:00:00'
),
(
    2,
    5,
    2,
    'Processing Underway',
    'Payment verified for REG-2026-000126. Your Certification of Grades is now being processed.',
    'success',
    1,
    '2026-09-01 14:00:00'
),
(
    3,
    7, -- Mark Reyes
    4,
    'Ready for Claiming',
    'Your Certificate of Good Moral Character (REG-2026-000128) is ready for release at the Registrar Office (Counter 2). Please bring your School ID.',
    'success',
    0,
    '2026-08-31 15:00:00'
)
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);

-- Audit Logs
INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `entity_affected`, `entity_id`, `previous_state`, `new_state`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 5, 'REQUEST_SUBMITTED', 'document_requests', '1', NULL, JSON_OBJECT('tracking_number', 'REG-2026-000125', 'status', 'REQUEST SUBMITTED'), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-02 08:30:00'),
(2, 3, 'STATUS_UPDATE', 'document_requests', '1', JSON_OBJECT('status', 'REQUEST SUBMITTED'), JSON_OBJECT('status', 'FOR VERIFICATION'), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-02 09:00:00'),
(3, 3, 'PAYMENT_VERIFIED', 'payments', '1', JSON_OBJECT('payment_status', 'For Verification'), JSON_OBJECT('payment_status', 'Paid'), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-09-01 14:00:00'),
(4, 2, 'STATUS_UPDATE', 'document_requests', '4', JSON_OBJECT('status', 'FOR REVIEW/APPROVAL'), JSON_OBJECT('status', 'READY FOR RELEASE'), '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', '2026-08-31 15:00:00')
ON DUPLICATE KEY UPDATE `created_at` = VALUES(`created_at`);
