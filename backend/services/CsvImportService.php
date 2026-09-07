<?php
/**
 * COLM Registrar Document Request and Tracking System
 * CSV Bulk Student Account Creation & Import Service
 */

declare(strict_types=1);

namespace Colm\Services;

use Colm\Config\Database;
use Colm\Models\User;
use Colm\Models\Student;
use Colm\Models\EnrollmentRecord;
use Colm\Models\CsvImportBatch;
use Colm\Models\CsvImportError;
use InvalidArgumentException;
use RuntimeException;

class CsvImportService {
    private User $userModel;
    private Student $studentModel;
    private EnrollmentRecord $enrollmentModel;
    private CsvImportBatch $batchModel;
    private CsvImportError $errorModel;
    private AuditService $auditService;

    public const EXPECTED_COLUMNS = [
        'student_number',
        'last_name',
        'first_name',
        'middle_name',
        'sex',
        'dob',
        'program',
        'education_level',
        'major',
        'year_level',
        'section',
        'academic_year',
        'semester',
        'email',
        'contact_number',
        'address',
        'enrollment_status',
        'date_enrolled'
    ];

    public function __construct() {
        $this->userModel = new User();
        $this->studentModel = new Student();
        $this->enrollmentModel = new EnrollmentRecord();
        $this->batchModel = new CsvImportBatch();
        $this->errorModel = new CsvImportError();
        $this->auditService = new AuditService();
    }

    /**
     * Phase 1: Parse and validate CSV file without inserting records
     */
    public function validateAndPreview(string $filePath, string $originalFilename, int $userId): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException('CSV file could not be read.', 400);
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException('Unable to open CSV stream.', 500);
        }

        // Read header
        $header = fgetcsv($handle, 4096, ',');
        if (!$header) {
            fclose($handle);
            throw new InvalidArgumentException('Uploaded CSV file is empty.', 400);
        }

        // Clean headers (remove BOM and trim)
        $cleanHeaders = array_map(function($h) {
            $h = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', trim($h));
            return strtolower(str_replace(' ', '_', $h));
        }, $header);

        // Verify required columns exist
        $missingCols = array_diff(['student_number', 'last_name', 'first_name', 'sex', 'dob', 'program', 'year_level', 'academic_year', 'semester', 'email'], $cleanHeaders);
        if (!empty($missingCols)) {
            fclose($handle);
            throw new InvalidArgumentException('CSV missing mandatory columns: ' . implode(', ', $missingCols), 422);
        }

        $rowNumber = 1;
        $validRows = [];
        $invalidRows = [];
        $duplicateRows = [];
        $errors = [];

        $seenInCsv = [];

        while (($row = fgetcsv($handle, 4096, ',')) !== false) {
            $rowNumber++;
            if (empty(array_filter($row))) {
                continue; // Skip blank lines
            }

            $rowData = [];
            foreach ($cleanHeaders as $idx => $colName) {
                $rowData[$colName] = isset($row[$idx]) ? trim($row[$idx]) : '';
            }

            $rowErrors = [];
            $studentNum = $rowData['student_number'] ?? '';
            $email = $rowData['email'] ?? '';

            // Validate student number
            if (empty($studentNum)) {
                $rowErrors[] = ['field' => 'student_number', 'message' => 'Student Number is required.'];
            } elseif (isset($seenInCsv[$studentNum])) {
                $rowErrors[] = ['field' => 'student_number', 'message' => "Duplicate Student Number in CSV (first seen at row {$seenInCsv[$studentNum]})."];
            }

            // Check if already in database
            if (!empty($studentNum) && $this->studentModel->findByStudentNumber($studentNum)) {
                $rowErrors[] = ['field' => 'student_number', 'message' => "Student Number '{$studentNum}' already exists in database."];
            }

            if (!empty($studentNum) && $this->userModel->findByUsername($studentNum)) {
                $rowErrors[] = ['field' => 'student_number', 'message' => "User Account '{$studentNum}' already exists in database."];
            }

            // Validate names
            if (empty($rowData['last_name'])) {
                $rowErrors[] = ['field' => 'last_name', 'message' => 'Last Name is required.'];
            }
            if (empty($rowData['first_name'])) {
                $rowErrors[] = ['field' => 'first_name', 'message' => 'First Name is required.'];
            }

            // Validate Sex
            if (empty($rowData['sex']) || !in_array(ucfirst(strtolower($rowData['sex'])), ['Male', 'Female', 'Other'], true)) {
                $rowErrors[] = ['field' => 'sex', 'message' => 'Sex must be Male, Female, or Other.'];
            } else {
                $rowData['sex'] = ucfirst(strtolower($rowData['sex']));
            }

            // Validate DOB
            if (empty($rowData['dob'])) {
                $rowErrors[] = ['field' => 'dob', 'message' => 'Date of Birth is required.'];
            } else {
                $d = \DateTime::createFromFormat('Y-m-d', $rowData['dob']) ?: \DateTime::createFromFormat('m/d/Y', $rowData['dob']);
                if (!$d) {
                    $rowErrors[] = ['field' => 'dob', 'message' => 'Date of birth must be YYYY-MM-DD or MM/DD/YYYY.'];
                } else {
                    $rowData['dob'] = $d->format('Y-m-d');
                }
            }

            // Validate Email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = ['field' => 'email', 'message' => 'Valid email address is required.'];
            }

            // Validate Program
            if (empty($rowData['program'])) {
                $rowErrors[] = ['field' => 'program', 'message' => 'Program is required.'];
            }

            if (!empty($rowErrors)) {
                $isDuplicate = false;
                foreach ($rowErrors as $err) {
                    if (str_contains($err['message'], 'already exists') || str_contains($err['message'], 'Duplicate')) {
                        $isDuplicate = true;
                    }
                    $errors[] = [
                        'row_number'    => $rowNumber,
                        'field_name'    => $err['field'],
                        'error_message' => $err['message'],
                        'raw_value'     => $rowData[$err['field']] ?? ''
                    ];
                }
                if ($isDuplicate) {
                    $duplicateRows[] = ['row' => $rowNumber, 'data' => $rowData, 'errors' => $rowErrors];
                } else {
                    $invalidRows[] = ['row' => $rowNumber, 'data' => $rowData, 'errors' => $rowErrors];
                }
            } else {
                $validRows[] = array_merge($rowData, ['_row' => $rowNumber]);
                $seenInCsv[$studentNum] = $rowNumber;
            }
        }

        fclose($handle);

        $totalRows = count($validRows) + count($invalidRows) + count($duplicateRows);

        return [
            'total_rows'      => $totalRows,
            'valid_count'     => count($validRows),
            'invalid_count'   => count($invalidRows),
            'duplicate_count' => count($duplicateRows),
            'valid_rows'      => $validRows,
            'errors'          => $errors,
            'filename'        => $originalFilename
        ];
    }

    /**
     * Phase 2: Transactional database import of validated rows
     */
    public function commitImport(array $validRows, array $allErrors, string $filename, int $userId): array {
        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            // 1. Create Import Batch Record
            $batchId = $this->batchModel->create([
                'uploaded_by'     => $userId,
                'filename'        => $filename,
                'total_rows'      => count($validRows) + count($allErrors),
                'successful_rows' => count($validRows),
                'failed_rows'     => count($allErrors),
                'duplicate_rows'  => 0
            ]);

            // 2. Insert error logs if any
            foreach ($allErrors as $err) {
                $this->errorModel->log(
                    $batchId,
                    (int)($err['row_number'] ?? 0),
                    $err['field_name'] ?? 'general',
                    $err['error_message'] ?? 'Validation failure',
                    $err['raw_value'] ?? null
                );
            }

            // 3. Create Accounts, Students, and Enrollment records
            $importedCount = 0;
            // Default initial password hash for imported student accounts: 'Password123!'
            // Users can change their password on first login
            $initialHash = password_hash('Password123!', PASSWORD_BCRYPT);

            foreach ($validRows as $row) {
                $studentNum = $row['student_number'];

                // Create User
                $newUserId = $this->userModel->create(
                    $studentNum,
                    $initialHash,
                    'Student',
                    1
                );

                // Create Student Profile
                $studentId = $this->studentModel->create([
                    'user_id'        => $newUserId,
                    'student_number' => $studentNum,
                    'last_name'      => $row['last_name'],
                    'first_name'     => $row['first_name'],
                    'middle_name'    => $row['middle_name'] ?? null,
                    'sex'            => $row['sex'],
                    'dob'            => $row['dob'],
                    'contact_number' => !empty($row['contact_number']) ? $row['contact_number'] : '0900-000-0000',
                    'email'          => $row['email'],
                    'address'        => !empty($row['address']) ? $row['address'] : 'Pulilan, Bulacan'
                ]);

                // Create Enrollment Record
                $this->enrollmentModel->create([
                    'student_id'        => $studentId,
                    'program'           => $row['program'],
                    'education_level'   => !empty($row['education_level']) ? $row['education_level'] : null,
                    'major'             => !empty($row['major']) ? $row['major'] : 'General',
                    'year_level'        => !empty($row['year_level']) ? $row['year_level'] : '1st Year',
                    'section'           => !empty($row['section']) ? $row['section'] : null,
                    'academic_year'     => !empty($row['academic_year']) ? $row['academic_year'] : '2026-2027',
                    'semester'          => !empty($row['semester']) ? $row['semester'] : 'First Semester',
                    'enrollment_status' => !empty($row['enrollment_status']) ? $row['enrollment_status'] : 'Officially Enrolled',
                    'date_enrolled'     => !empty($row['date_enrolled']) ? $row['date_enrolled'] : date('Y-m-d')
                ]);

                $importedCount++;
            }

            // 4. Audit Log
            $this->auditService->log(
                $userId,
                'CSV_STUDENTS_IMPORTED',
                'csv_import_batches',
                (string)$batchId,
                null,
                ['successful_rows' => $importedCount, 'filename' => $filename]
            );

            $db->commit();

            return [
                'success'         => true,
                'batch_id'        => $batchId,
                'successful_rows' => $importedCount,
                'failed_rows'     => count($allErrors)
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new RuntimeException('CSV Import transaction failed: ' . $e->getMessage(), 500);
        }
    }
}
