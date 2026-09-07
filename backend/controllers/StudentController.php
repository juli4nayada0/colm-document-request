<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Student & CSV Bulk Import Controller
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\Student;
use Colm\Models\User;
use Colm\Models\EnrollmentRecord;
use Colm\Models\AcademicRecord;
use Colm\Models\CsvImportBatch;
use Colm\Models\CsvImportError;
use Colm\Services\CsvImportService;
use Colm\Services\AuditService;
use Colm\Validators\StudentValidator;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;

class StudentController {
    private array $currentUser;
    private Student $studentModel;
    private EnrollmentRecord $enrollmentModel;
    private AcademicRecord $academicModel;
    private CsvImportService $csvService;
    private AuditService $auditService;
    private StudentValidator $validator;

    public function __construct() {
        $authMiddleware = new AuthMiddleware();
        $this->currentUser = $authMiddleware->handle();

        $this->studentModel = new Student();
        $this->enrollmentModel = new EnrollmentRecord();
        $this->academicModel = new AcademicRecord();
        $this->csvService = new CsvImportService();
        $this->auditService = new AuditService();
        $this->validator = new StudentValidator();
    }

    /**
     * GET /api/v1/students
     */
    public function index(): void {
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Personnel', 'Admin']);

        $page = (int)($_GET['page'] ?? 1);
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 25)));
        $search = !empty($_GET['search']) ? SecurityHelper::sanitizeString($_GET['search']) : null;
        $program = !empty($_GET['program']) ? SecurityHelper::sanitizeString($_GET['program']) : null;
        $yearLevel = !empty($_GET['year_level']) ? SecurityHelper::sanitizeString($_GET['year_level']) : null;
        $status = !empty($_GET['status']) ? SecurityHelper::sanitizeString($_GET['status']) : null;
        $educationLevel = !empty($_GET['education_level']) ? SecurityHelper::sanitizeString($_GET['education_level']) : null;
        $section = !empty($_GET['section']) ? SecurityHelper::sanitizeString($_GET['section']) : null;

        $result = $this->studentModel->getList($page, $limit, $search, $program, $yearLevel, $status, $educationLevel, $section);

        ResponseHelper::success($result['records'], 'Students retrieved.', 200, [
            'total' => $result['total'],
            'page'  => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages']
        ]);
    }

    /**
     * PATCH /api/v1/students/deactivate-all
     */
    public function deactivateAll(): void {
        RoleMiddleware::authorize($this->currentUser, ['Admin']);

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $filters = [];
        foreach (['program', 'year_level', 'education_level', 'section'] as $field) {
            if (!empty($data[$field])) {
                $filters[$field] = SecurityHelper::sanitizeString($data[$field]);
            }
        }

        $count = $this->studentModel->deactivateAccounts($filters);
        $this->auditService->log(
            $this->currentUser['user_id'],
            'STUDENT_ACCOUNTS_DEACTIVATED',
            'users',
            'bulk',
            null,
            ['filters' => $filters, 'affected_accounts' => $count]
        );

        ResponseHelper::success(['affected_accounts' => $count], "{$count} student account(s) deactivated.");
    }

    /**
     * GET /api/v1/students/me
     */
    public function me(): void {
        RoleMiddleware::authorize($this->currentUser, ['Student']);

        $studentId = $this->currentUser['student_id'];
        if (!$studentId) {
            ResponseHelper::error('No student profile linked to this account.', 404);
        }

        $student = $this->studentModel->findById((int)$studentId);
        $enrollmentHistory = $this->enrollmentModel->getByStudentId((int)$studentId);

        ResponseHelper::success([
            'profile'            => $student,
            'enrollment_history' => $enrollmentHistory
        ], 'Student profile retrieved.');
    }

    /**
     * GET /api/v1/students/{id}
     */
    public function show(int $id): void {
        // Enforce student isolation: Students can only view their own profile
        if ($this->currentUser['role'] === 'Student') {
            RoleMiddleware::authorizeStudentOwnership($this->currentUser, $id);
        } else {
            RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Personnel', 'Admin']);
        }

        $student = $this->studentModel->findById($id);
        if (!$student) {
            ResponseHelper::error("Student #{$id} not found.", 404);
        }

        $enrollmentHistory = $this->enrollmentModel->getByStudentId($id);
        
        // Admin cannot see academic records unless authorized (Separation of duties)
        $academicRecord = null;
        if ($this->currentUser['role'] !== 'Admin') {
            $academicRecord = $this->academicModel->getByStudentId($id);
        }

        ResponseHelper::success([
            'profile'            => $student,
            'enrollment_history' => $enrollmentHistory,
            'academic_record'    => $academicRecord
        ], 'Student profile details retrieved.');
    }

    /**
     * POST /api/v1/students
     */
    public function store(): void {
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Admin']);

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateCreate($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $studentNum = SecurityHelper::sanitizeString($data['student_number']);
        if ($this->studentModel->findByStudentNumber($studentNum)) {
            ResponseHelper::error("Student Number '{$studentNum}' already exists.", 409);
        }

        $userModel = new User();
        // Check if student account exists
        $userId = null;
        if (!empty($data['create_user_account'])) {
            if ($userModel->findByUsername($studentNum)) {
                ResponseHelper::error("User Account '{$studentNum}' already exists.", 409);
            }
            $initPass = !empty($data['initial_password']) ? $data['initial_password'] : 'Password123!';
            $userId = $userModel->create($studentNum, password_hash($initPass, PASSWORD_BCRYPT), 'Student', 1);
        }

        $studentData = array_merge($data, ['user_id' => $userId]);
        $studentId = $this->studentModel->create($studentData);

        // Create initial enrollment record if provided
        if (!empty($data['program'])) {
            $this->enrollmentModel->create([
                'student_id'        => $studentId,
                'program'           => $data['program'],
                'major'             => $data['major'] ?? 'General',
                'year_level'        => $data['year_level'] ?? '1st Year',
                'academic_year'     => $data['academic_year'] ?? '2026-2027',
                'semester'          => $data['semester'] ?? 'First Semester',
                'enrollment_status' => $data['enrollment_status'] ?? 'Officially Enrolled',
                'date_enrolled'     => $data['date_enrolled'] ?? date('Y-m-d')
            ]);
        }

        $this->auditService->log(
            $this->currentUser['user_id'],
            'STUDENT_CREATED',
            'students',
            (string)$studentId,
            null,
            ['student_number' => $studentNum, 'name' => "{$data['first_name']} {$data['last_name']}"]
        );

        ResponseHelper::success(['student_id' => $studentId], 'Student record created successfully.', 201);
    }

    /**
     * PUT /api/v1/students/{id}
     */
    public function update(int $id): void {
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Admin']);

        $student = $this->studentModel->findById($id);
        if (!$student) {
            ResponseHelper::error("Student #{$id} not found.", 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateUpdate($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $this->studentModel->update($id, $data);

        $this->auditService->log(
            $this->currentUser['user_id'],
            'STUDENT_UPDATED',
            'students',
            (string)$id,
            $student,
            $data
        );

        ResponseHelper::success(null, 'Student details updated.');
    }

    /**
     * POST /api/v1/students/import
     * Handles 2 modes: preview (default on file upload) or confirm (commits valid rows)
     */
    public function import(): void {
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Admin']);

        $action = $_POST['action'] ?? 'preview';

        if ($action === 'preview') {
            if (!isset($_FILES['csv_file'])) {
                ResponseHelper::error('No CSV file was uploaded.', 400);
            }

            $file = $_FILES['csv_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') {
                ResponseHelper::error('Uploaded file must have a .csv extension.', 422);
            }

            $previewResult = $this->csvService->validateAndPreview($file['tmp_name'], $file['name'], $this->currentUser['user_id']);
            ResponseHelper::success($previewResult, 'CSV parsed and validated successfully.');
        } elseif ($action === 'commit') {
            $rawPayload = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $validRows = $rawPayload['valid_rows'] ?? [];
            $errors = $rawPayload['errors'] ?? [];
            $filename = $rawPayload['filename'] ?? 'bulk_import.csv';

            if (empty($validRows)) {
                ResponseHelper::error('No valid records to import.', 400);
            }

            $result = $this->csvService->commitImport($validRows, $errors, $filename, $this->currentUser['user_id']);
            ResponseHelper::success($result, "Successfully imported {$result['successful_rows']} student accounts.");
        } else {
            ResponseHelper::error("Unknown action '{$action}'.", 400);
        }
    }

    /**
     * GET /api/v1/students/import/batches
     */
    public function batches(): void {
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Admin']);
        $batchModel = new CsvImportBatch();
        $batches = $batchModel->getList(20);
        ResponseHelper::success($batches, 'Import batches retrieved.');
    }

    /**
     * GET /api/v1/students/import/{batchId}/errors
     */
    public function batchErrors(int $batchId): void {
        RoleMiddleware::authorize($this->currentUser, ['Registrar', 'Admin']);
        $errorModel = new CsvImportError();
        $errors = $errorModel->getByBatchId($batchId);
        ResponseHelper::success($errors, 'Batch import errors retrieved.');
    }
}
