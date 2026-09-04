-- ==============================================================================
-- COLM REGISTRAR DOCUMENT REQUEST AND TRACKING SYSTEM
-- Seed File: 002_students.sql
-- Sample student records (Fictional / Test Data)
-- ==============================================================================

USE `colm_rdrts_db`;

INSERT INTO `students` (`student_id`, `user_id`, `student_number`, `last_name`, `first_name`, `middle_name`, `sex`, `dob`, `contact_number`, `email`, `address`, `created_at`, `updated_at`) VALUES
(
    1,
    5,
    '2026-00001',
    'DELA CRUZ',
    'JUAN',
    'SANTOS',
    'Male',
    '2005-01-15',
    '0917-123-4567',
    'sample@student.com',
    'Longos, Pulilan, Bulacan',
    NOW(),
    NOW()
),
(
    2,
    6,
    '2026-00002',
    'SANTOS',
    'MARIA',
    'CLARA',
    'Female',
    '2004-08-22',
    '0918-987-6543',
    'maria.santos@student.com',
    'Poblacion, Pulilan, Bulacan',
    NOW(),
    NOW()
),
(
    3,
    7,
    '2026-00003',
    'REYES',
    'MARK',
    'BAUTISTA',
    'Male',
    '2005-03-10',
    '0920-555-1234',
    'mark.reyes@student.com',
    'Paltao, Pulilan, Bulacan',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
