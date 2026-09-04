<?php
/**
 * COLM Registrar Document Request and Tracking System
 * User Management Controller (Admin / Registrar)
 */

declare(strict_types=1);

namespace Colm\Controllers;

use Colm\Middleware\AuthMiddleware;
use Colm\Middleware\RoleMiddleware;
use Colm\Models\User;
use Colm\Services\AuditService;
use Colm\Validators\UserValidator;
use Colm\Helpers\ResponseHelper;
use Colm\Helpers\SecurityHelper;
use PDOException;

class UserController {
    private array $currentUser;
    private User $userModel;
    private AuditService $auditService;
    private UserValidator $validator;

    public function __construct() {
        $authMiddleware = new AuthMiddleware();
        $this->currentUser = $authMiddleware->handle();
        RoleMiddleware::authorize($this->currentUser, ['Admin', 'Registrar']);

        $this->userModel = new User();
        $this->auditService = new AuditService();
        $this->validator = new UserValidator();
    }

    /**
     * GET /api/v1/users
     */
    public function index(): void {
        $page = (int)($_GET['page'] ?? 1);
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $role = !empty($_GET['role']) ? SecurityHelper::sanitizeString($_GET['role']) : null;
        $isActive = isset($_GET['is_active']) && $_GET['is_active'] !== '' ? (int)$_GET['is_active'] : null;
        $search = !empty($_GET['search']) ? SecurityHelper::sanitizeString($_GET['search']) : null;

        $result = $this->userModel->getList($page, $limit, $role, $isActive, $search);
        ResponseHelper::success($result['records'], 'Users retrieved successfully.', 200, [
            'total' => $result['total'],
            'page'  => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages']
        ]);
    }

    /**
     * GET /api/v1/users/{id}
     */
    public function show(int $id): void {
        $user = $this->userModel->findById($id);
        if (!$user) {
            ResponseHelper::error("User #{$id} not found.", 404);
        }
        ResponseHelper::success($user, 'User details retrieved.');
    }

    /**
     * POST /api/v1/users
     */
    public function store(): void {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateCreate($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $username = SecurityHelper::sanitizeString($data['username']);
        
        // Check uniqueness
        if ($this->userModel->findByUsername($username)) {
            ResponseHelper::error("Username '{$username}' is already taken.", 409);
        }

        $passwordHash = password_hash((string)$data['password'], PASSWORD_BCRYPT);
        $role = $data['role'];
        $isActive = (int)($data['is_active'] ?? 1);

        try {
            $newId = $this->userModel->create($username, $passwordHash, $role, $isActive);
        } catch (PDOException $e) {
            if ((string)$e->getCode() === '23000') {
                ResponseHelper::error("Username '{$username}' is already taken.", 409, null, 'USERNAME_EXISTS');
            }
            throw $e;
        }

        $this->auditService->log(
            $this->currentUser['user_id'],
            'USER_CREATED',
            'users',
            (string)$newId,
            null,
            ['username' => $username, 'role' => $role, 'is_active' => $isActive]
        );

        ResponseHelper::success(['user_id' => $newId], 'User account created successfully.', 201);
    }

    /**
     * PUT /api/v1/users/{id}
     */
    public function update(int $id): void {
        $user = $this->userModel->findById($id);
        if (!$user) {
            ResponseHelper::error("User #{$id} not found.", 404);
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (!$this->validator->validateUpdate($data)) {
            ResponseHelper::error('Validation failed.', 422, $this->validator->getErrors());
        }

        $updates = [];
        if (isset($data['role'])) {
            $updates['role'] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = (int)$data['is_active'];
        }
        if (!empty($data['password'])) {
            $updates['password_hash'] = password_hash((string)$data['password'], PASSWORD_BCRYPT);
        }

        $this->userModel->update($id, $updates);

        $this->auditService->log(
            $this->currentUser['user_id'],
            'USER_UPDATED',
            'users',
            (string)$id,
            $user,
            array_diff_key($updates, ['password_hash' => ''])
        );

        ResponseHelper::success(null, 'User account updated successfully.');
    }

    /**
     * PATCH /api/v1/users/{id}/status
     */
    public function toggleStatus(int $id): void {
        $user = $this->userModel->findById($id);
        if (!$user) {
            ResponseHelper::error("User #{$id} not found.", 404);
        }

        // Prevent self-deactivation
        if ($id === $this->currentUser['user_id']) {
            ResponseHelper::error('You cannot deactivate your own active session account.', 400);
        }

        $newStatus = (int)$user['is_active'] === 1 ? 0 : 1;
        $this->userModel->update($id, ['is_active' => $newStatus]);

        $this->auditService->log(
            $this->currentUser['user_id'],
            $newStatus === 1 ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
            'users',
            (string)$id,
            ['is_active' => $user['is_active']],
            ['is_active' => $newStatus]
        );

        ResponseHelper::success(['is_active' => $newStatus], $newStatus === 1 ? 'User activated.' : 'User deactivated.');
    }
}
