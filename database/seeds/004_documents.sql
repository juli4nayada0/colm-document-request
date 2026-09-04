-- ==============================================================================
-- COLM REGISTRAR DOCUMENT REQUEST AND TRACKING SYSTEM
-- Seed File: 004_documents.sql
-- Master catalog of 15 standard COLM documents and required supporting files
-- ==============================================================================

USE `colm_rdrts_db`;

-- 15 Standard Document Types
INSERT INTO `documents` (`document_id`, `document_code`, `document_name`, `description`, `typical_purpose`, `basic_requirements`, `processing_days`, `fee_amount`, `is_digital_allowed`, `requires_approval`, `is_active`, `created_at`, `updated_at`) VALUES
(
    1,
    'DOC-TOR',
    'Transcript of Records (TOR)',
    'Official consolidated academic transcript covering all course units, grades, and graduation credentials.',
    'Employment, Board Examination (PRC), Graduate Studies, Transfer to another institution',
    'Clearance from Finance, Library, and Dean; Valid 2x2 Photo with white background.',
    10,
    350.00,
    0, -- Physical/Security Document
    1,
    1,
    NOW(),
    NOW()
),
(
    2,
    'DOC-COG',
    'Certification of Grades',
    'Official certificate containing semester breakdown of subjects taken and grades obtained.',
    'Scholarship renewal, employment evaluation, prerequisite verification',
    'Valid student ID / School ID or Certificate of Registration',
    3,
    100.00,
    1,
    1,
    1,
    NOW(),
    NOW()
),
(
    3,
    'DOC-COE',
    'Certificate of Enrollment',
    'Formal institutional verification confirming active student status in the current semester.',
    'Scholarship, SSS/GSIS loan, visa application, travel/allowance requirements',
    'Current semester enrollment assessment form / receipt',
    2,
    75.00,
    1,
    0,
    1,
    NOW(),
    NOW()
),
(
    4,
    'DOC-COR',
    'Certificate of Registration',
    'Certified copy of the official enrollment registration and scheduled course matrix.',
    'Proof of student load, discounted fare applications, insurance verification',
    'Assessment form / Student ID',
    2,
    75.00,
    1,
    0,
    1,
    NOW(),
    NOW()
),
(
    5,
    'DOC-COG-GRAD',
    'Certificate of Graduation',
    'Official institutional attestation confirming the degree earned and graduation date.',
    'Local and international employment, PRC exam filing, promotion validation',
    'Registrar and Finance clearance',
    5,
    150.00,
    1,
    1,
    1,
    NOW(),
    NOW()
),
(
    6,
    'DOC-GMC',
    'Certificate of Good Moral Character',
    'Attestation from the Office of Student Affairs and Registrar regarding discipline record.',
    'Transfer, job application, board examination, scholarship endorsement',
    'Clearance from Office of Student Affairs / Prefect of Discipline',
    3,
    100.00,
    1,
    1,
    1,
    NOW(),
    NOW()
),
(
    7,
    'DOC-CUE',
    'Certificate of Units Earned',
    'Statement indicating total credited collegiate units completed to date.',
    'Civil service eligibility, salary upgrade, professional credentialing',
    'Student ID, evaluation form',
    3,
    100.00,
    1,
    1,
    1,
    NOW(),
    NOW()
),
(
    8,
    'DOC-GWA',
    'Certificate of General Weighted Average (GWA)',
    'Certification of overall cumulative grade point average across all completed semesters.',
    'Honors application, Latin honors qualification, graduate school admission, scholarships',
    'Academic evaluation review, Student ID',
    3,
    100.00,
    1,
    1,
    1,
    NOW(),
    NOW()
),
(
    9,
    'DOC-NPR',
    'Certificate of No Pending/Unfinished Academic Requirement',
    'Certification certifying completion of all coursework, prerequisites, and thesis/internship requirements.',
    'Graduation clearance, internship exit clearance',
    'Dean endorsement, program chair clearance',
    4,
    120.00,
    1,
    1,
    1,
    NOW(),
    NOW()
),
(
    10,
    'DOC-HD',
    'Honorable Dismissal',
    'Official document permitting student to transfer out and enroll in another collegiate institution.',
    'Transfer to another college/university',
    'Complete institutional clearance (Library, Guidance, Finance, Property, Registrar), Return of ID',
    7,
    250.00,
    0, -- Security Physical Document
    1,
    1,
    NOW(),
    NOW()
),
(
    11,
    'DOC-CTC',
    'Certified True Copy of Academic Record',
    'Registrar seal and dry stamp verifying authenticity of submitted photocopies of records.',
    'Document authentication, apostille preparation, credential verification',
    'Original copy of document to be authenticated and photocopies',
    3,
    50.00,
    0,
    1,
    1,
    NOW(),
    NOW()
),
(
    12,
    'DOC-F137',
    'Form 137 / Permanent Record',
    'High school transcript / secondary student permanent record copy for school-to-school transfer.',
    'College admissions verification, DepEd / CHED audit',
    'Official Request Letter from requesting institution',
    7,
    150.00,
    0,
    1,
    1,
    NOW(),
    NOW()
),
(
    13,
    'DOC-OJT',
    'Internship/Training Certification',
    'Formal certification of completed on-the-job training (OJT) or practicum hours.',
    'Employment portfolio, licensing board evaluation',
    'Endorsement from Internship Coordinator, Certificate of Completion from Company',
    4,
    100.00,
    1,
    1,
    1,
    NOW(),
    NOW()
),
(
    14,
    'DOC-NSTP',
    'NSTP/CWTS-related Certification',
    'Official certificate and serial number for completed National Service Training Program.',
    'Graduation requirement, cross-enrollment clearance',
    'NSTP Facilitator clearance, Certificate of completion',
    3,
    80.00,
    1,
    0,
    1,
    NOW(),
    NOW()
),
(
    15,
    'DOC-OTH',
    'Other Certifications',
    'Custom registrar certification for special academic or administrative situations.',
    'Custom institutional or legal requirement',
    'Formal written request letter stating purpose and required supporting documents',
    5,
    100.00,
    1,
    1,
    1,
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- Specific Document Requirements
INSERT INTO `document_requirements` (`requirement_id`, `document_id`, `requirement_name`, `description`, `is_required`, `allowed_file_types`, `max_file_size_mb`, `is_active`, `created_at`, `updated_at`) VALUES
-- Requirements for TOR (doc_id: 1)
(1, 1, 'Valid Student/Government ID', 'Clear scanned copy of School ID or government-issued ID (Front and Back)', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),
(2, 1, 'Clearance Certificate', 'Signed Institutional Clearance from Finance, Library, and Dean', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),
(3, 1, '2x2 Recent Photo', 'Formal 2x2 photograph with white background and name tag', 1, 'jpg,jpeg,png', 3, 1, NOW(), NOW()),

-- Requirements for Certification of Grades (doc_id: 2)
(4, 2, 'Student ID / Assessment Form', 'Scanned copy of School ID or current Certificate of Registration', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),

-- Requirements for Certificate of Enrollment (doc_id: 3)
(5, 3, 'Proof of Current Enrollment', 'Scanned Certificate of Registration or Validated Assessment Form', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),

-- Requirements for Certificate of Graduation (doc_id: 5)
(6, 5, 'Valid Government ID', 'Clear scanned copy of government-issued ID', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),
(7, 5, 'Graduation Clearance', 'Signed final graduation clearance form', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),

-- Requirements for Good Moral (doc_id: 6)
(8, 6, 'Student ID', 'Valid Student ID or Recent Identification', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),
(9, 6, 'OSA/Discipline Clearance', 'Clearance slip from Office of Student Affairs / Prefect of Discipline', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),

-- Requirements for Honorable Dismissal (doc_id: 10)
(10, 10, 'Complete Institutional Clearance', 'Fully signed exit clearance across all college departments', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW()),
(11, 10, 'Surrender of Student ID', 'Photo/proof of surrendered physical school ID card', 1, 'jpg,jpeg,png,pdf', 3, 1, NOW(), NOW()),

-- Requirements for Other Certifications (doc_id: 15)
(12, 15, 'Request Letter with Detailed Purpose', 'Formal written letter specifying the exact details needed in the certification', 1, 'pdf,jpg,jpeg,png', 5, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();
