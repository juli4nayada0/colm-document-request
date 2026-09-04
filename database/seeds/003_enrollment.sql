-- ==============================================================================
-- COLM REGISTRAR DOCUMENT REQUEST AND TRACKING SYSTEM
-- Seed File: 003_enrollment.sql
-- Sample enrollment and academic records (Fictional / Test Data)
-- ==============================================================================

USE `colm_rdrts_db`;

-- Enrollment Records
INSERT INTO `enrollment_records` (`enrollment_id`, `student_id`, `program`, `major`, `year_level`, `academic_year`, `semester`, `enrollment_status`, `date_enrolled`, `created_at`, `updated_at`) VALUES
(
    1,
    1,
    'Bachelor of Science in Information Technology',
    'N/A',
    '2nd Year',
    '2026-2027',
    'First Semester',
    'Officially Enrolled',
    '2026-06-05',
    NOW(),
    NOW()
),
(
    2,
    1,
    'Bachelor of Science in Information Technology',
    'N/A',
    '1st Year',
    '2025-2026',
    'Second Semester',
    'Officially Enrolled',
    '2026-01-10',
    NOW(),
    NOW()
),
(
    3,
    2,
    'Bachelor of Science in Business Administration',
    'Financial Management',
    '3rd Year',
    '2026-2027',
    'First Semester',
    'Officially Enrolled',
    '2026-06-08',
    NOW(),
    NOW()
),
(
    4,
    3,
    'Bachelor of Elementary Education',
    'General',
    '2nd Year',
    '2026-2027',
    'First Semester',
    'Officially Enrolled',
    '2026-06-10',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Academic Records (Metadata & Verification)
INSERT INTO `academic_records` (`record_id`, `student_id`, `graduation_status`, `record_reference`, `verification_status`, `remarks`, `created_at`, `updated_at`) VALUES
(
    1,
    1,
    'Undergraduate',
    'BOOK-2025-IT-0042',
    'Verified',
    'Complete secondary credentials and Form 137 on file.',
    NOW(),
    NOW()
),
(
    2,
    2,
    'Undergraduate',
    'BOOK-2024-BA-0118',
    'Verified',
    'Official records up to 2nd year certified.',
    NOW(),
    NOW()
),
(
    3,
    3,
    'Undergraduate',
    'BOOK-2025-ED-0095',
    'Verified',
    'Standard verification cleared.',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
