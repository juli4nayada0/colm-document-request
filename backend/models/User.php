<?php
/**
 * COLM Registrar Document Request and Tracking System
 * User Model
 */

declare(strict_types=1);

namespace Colm\Models;

class User extends BaseModel {
    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?array {
        $sql = "SELECT * FROM users WHERE username = :username LIMIT 1";
        return $this->fetchOne($sql, ['username' => $username]);
    }

    /**
     * Find user by ID
     */
    public function findById(int $userId): ?array {
        $sql = "SELECT user_id, username, role, is_active, last_login_at, created_at, updated_at 
                FROM users WHERE user_id = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $userId]);
    }

    /**
     * Get paginated users with filter & search
     */
    public function getList(int $page = 1, int $limit = 20, ?string $role = null, ?int $isActive = null, ?string $search = null): array {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];

        if (!empty($role)) {
            $conditions[] = "u.role = :role";
            $params['role'] = $role;
        }

        if ($isActive !== null) {
            $conditions[] = "u.is_active = :is_active";
            $params['is_active'] = $isActive;
        }

        if (!empty($search)) {
            $conditions[] = "(u.username LIKE :search OR s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_number LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count total
        $countSql = "SELECT COUNT(*) as total 
                     FROM users u 
                     LEFT JOIN students s ON u.user_id = s.user_id 
                     {$whereClause}";
        $totalRow = $this->fetchOne($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);

        // Fetch records
        $sql = "SELECT u.user_id, u.username, u.role, u.is_active, u.last_login_at, u.created_at,
                       s.student_id, s.student_number, s.first_name, s.last_name, s.email
                FROM users u 
                LEFT JOIN students s ON u.user_id = s.user_id 
                {$whereClause}
                ORDER BY u.user_id DESC 
                LIMIT {$limit} OFFSET {$offset}";

        $records = $this->fetchAll($sql, $params);

        return [
            'records' => $records,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'pages'   => ceil($total / max(1, $limit))
        ];
    }

    /**
     * Create user account
     */
    public function create(string $username, string $passwordHash, string $role, int $isActive = 1): int {
        $sql = "INSERT INTO users (username, password_hash, role, is_active, created_at, updated_at) 
                VALUES (:username, :password_hash, :role, :is_active, NOW(), NOW())";
        $this->execute($sql, [
            'username'      => $username,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'is_active'     => $isActive
        ]);
        return $this->lastInsertId();
    }

    /**
     * Update user details
     */
    public function update(int $userId, array $fields): bool {
        $allowed = ['role', 'is_active', 'password_hash'];
        $set = [];
        $params = ['user_id' => $userId];

        foreach ($fields as $key => $val) {
            if (in_array($key, $allowed, true)) {
                $set[] = "{$key} = :{$key}";
                $params[$key] = $val;
            }
        }

        if (empty($set)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(', ', $set) . ", updated_at = NOW() WHERE user_id = :user_id";
        return $this->execute($sql, $params);
    }

    /**
     * Record successful login timestamp
     */
    public function updateLastLogin(int $userId): bool {
        $sql = "UPDATE users SET last_login_at = NOW() WHERE user_id = :id";
        return $this->execute($sql, ['id' => $userId]);
    }
}
