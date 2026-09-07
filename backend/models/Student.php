<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Student Model
 */

declare(strict_types=1);

namespace Colm\Models;

class Student extends BaseModel {
    /**
     * Find student by ID with latest enrollment
     */
    public function findById(int $studentId): ?array {
        $sql = "SELECT s.*, 
                       e.program, e.education_level, e.major, e.year_level, e.section, e.academic_year, e.semester, e.enrollment_status, e.date_enrolled,
                       a.graduation_status, a.record_reference, a.verification_status as academic_verification_status
                FROM students s
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id 
                        FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                LEFT JOIN academic_records a ON s.student_id = a.student_id
                WHERE s.student_id = :id 
                LIMIT 1";
        return $this->fetchOne($sql, ['id' => $studentId]);
    }

    /**
     * Find student by linked user_id
     */
    public function findByUserId(int $userId): ?array {
        $sql = "SELECT s.*, 
                       e.program, e.education_level, e.major, e.year_level, e.section, e.academic_year, e.semester, e.enrollment_status, e.date_enrolled,
                       a.graduation_status, a.record_reference, a.verification_status as academic_verification_status
                FROM students s
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id 
                        FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                LEFT JOIN academic_records a ON s.student_id = a.student_id
                WHERE s.user_id = :user_id 
                LIMIT 1";
        return $this->fetchOne($sql, ['user_id' => $userId]);
    }

    /**
     * Find student by student_number
     */
    public function findByStudentNumber(string $studentNumber): ?array {
        $sql = "SELECT * FROM students WHERE student_number = :num LIMIT 1";
        return $this->fetchOne($sql, ['num' => $studentNumber]);
    }

    /**
     * Get paginated student directory with search and multi-filtering
     */
    public function getList(int $page = 1, int $limit = 25, ?string $search = null, ?string $program = null, ?string $yearLevel = null, ?string $status = null, ?string $educationLevel = null, ?string $section = null): array {
        $offset = ($page - 1) * $limit;
        $conditions = [];
        $params = [];

        if (!empty($program)) {
            $conditions[] = "e.program = :program";
            $params['program'] = $program;
        }

        if (!empty($yearLevel)) {
            $conditions[] = "e.year_level = :year_level";
            $params['year_level'] = $yearLevel;
        }

        if (!empty($educationLevel)) {
            $conditions[] = "e.education_level = :education_level";
            $params['education_level'] = $educationLevel;
        }

        if (!empty($section)) {
            $conditions[] = "e.section = :section";
            $params['section'] = $section;
        }

        if (!empty($status)) {
            $conditions[] = "e.enrollment_status = :status";
            $params['status'] = $status;
        }

        if (!empty($search)) {
            $conditions[] = "(s.student_number LIKE :search_student_number
                             OR s.last_name LIKE :search_last_name
                             OR s.first_name LIKE :search_first_name
                             OR CONCAT(s.first_name, ' ', s.last_name) LIKE :search_full_name
                             OR s.email LIKE :search_email
                             OR e.program LIKE :search_program)";
            $searchValue = "%{$search}%";
            $params['search_student_number'] = $searchValue;
            $params['search_last_name'] = $searchValue;
            $params['search_first_name'] = $searchValue;
            $params['search_full_name'] = $searchValue;
            $params['search_email'] = $searchValue;
            $params['search_program'] = $searchValue;
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $countSql = "SELECT COUNT(DISTINCT s.student_id) as total
                     FROM students s
                     LEFT JOIN (
                         SELECT e1.* FROM enrollment_records e1
                         INNER JOIN (
                             SELECT student_id, MAX(enrollment_id) as max_id 
                             FROM enrollment_records GROUP BY student_id
                         ) e2 ON e1.enrollment_id = e2.max_id
                     ) e ON s.student_id = e.student_id
                     {$whereClause}";
        $totalRow = $this->fetchOne($countSql, $params);
        $total = (int)($totalRow['total'] ?? 0);

        $sql = "SELECT s.student_id, s.user_id, s.student_number, s.last_name, s.first_name, s.middle_name,
                       s.sex, s.dob, s.contact_number, s.email, s.address, s.created_at,
                       e.program, e.education_level, e.major, e.year_level, e.section, e.academic_year, e.semester, e.enrollment_status,
                       u.is_active as account_active, u.last_login_at
                FROM students s
                LEFT JOIN users u ON s.user_id = u.user_id
                LEFT JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) as max_id 
                        FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON s.student_id = e.student_id
                {$whereClause}
                ORDER BY s.last_name ASC, s.first_name ASC
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
     * Get section values available for the latest enrollment records.
     */
    public function getSections(?string $educationLevel = null, ?string $yearLevel = null): array {
        $conditions = ["e.section IS NOT NULL", "e.section <> ''"];
        $params = [];

        if (!empty($educationLevel)) {
            $conditions[] = 'e.education_level = :education_level';
            $params['education_level'] = $educationLevel;
        }

        if (!empty($yearLevel)) {
            $conditions[] = 'e.year_level = :year_level';
            $params['year_level'] = $yearLevel;
        }

        $sql = "SELECT DISTINCT e.section
                FROM enrollment_records e
                INNER JOIN (
                    SELECT student_id, MAX(enrollment_id) AS max_id
                    FROM enrollment_records GROUP BY student_id
                ) latest ON latest.max_id = e.enrollment_id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY e.section ASC";

        return array_values(array_filter(array_map(
            static fn(array $row): string => (string)$row['section'],
            $this->fetchAll($sql, $params)
        )));
    }

    /**
     * Deactivate student accounts matching directory filters.
     */
    public function deactivateAccounts(array $filters): int {
        $conditions = ["u.role = 'Student'", 'u.is_active = 1', 's.user_id IS NOT NULL'];
        $params = [];

        foreach (['program', 'year_level', 'education_level', 'section'] as $field) {
            if (!empty($filters[$field])) {
                $conditions[] = "e.{$field} = :deactivate_{$field}";
                $params["deactivate_{$field}"] = $filters[$field];
            }
        }

        $sql = "UPDATE users u
                INNER JOIN students s ON s.user_id = u.user_id
                INNER JOIN (
                    SELECT e1.* FROM enrollment_records e1
                    INNER JOIN (
                        SELECT student_id, MAX(enrollment_id) AS max_id
                        FROM enrollment_records GROUP BY student_id
                    ) e2 ON e1.enrollment_id = e2.max_id
                ) e ON e.student_id = s.student_id
                SET u.is_active = 0, u.updated_at = NOW()
                WHERE " . implode(' AND ', $conditions);

        $this->execute($sql, $params);
        return $this->db->rowCount();
    }

    /**
     * Create student record
     */
    public function create(array $data): int {
        $sql = "INSERT INTO students (user_id, student_number, last_name, first_name, middle_name, sex, dob, contact_number, email, address, created_at, updated_at)
                VALUES (:user_id, :student_number, :last_name, :first_name, :middle_name, :sex, :dob, :contact_number, :email, :address, NOW(), NOW())";
        
        $this->execute($sql, [
            'user_id'        => $data['user_id'] ?? null,
            'student_number' => $data['student_number'],
            'last_name'      => mb_strtoupper($data['last_name']),
            'first_name'     => mb_strtoupper($data['first_name']),
            'middle_name'    => !empty($data['middle_name']) ? mb_strtoupper($data['middle_name']) : null,
            'sex'            => $data['sex'],
            'dob'            => $data['dob'],
            'contact_number' => $data['contact_number'],
            'email'          => $data['email'],
            'address'        => $data['address'],
        ]);

        return $this->lastInsertId();
    }

    /**
     * Update student record
     */
    public function update(int $studentId, array $data): bool {
        $allowed = ['last_name', 'first_name', 'middle_name', 'sex', 'dob', 'contact_number', 'email', 'address'];
        $set = [];
        $params = ['student_id' => $studentId];

        foreach ($data as $key => $val) {
            if (in_array($key, $allowed, true)) {
                $set[] = "{$key} = :{$key}";
                $params[$key] = in_array($key, ['last_name', 'first_name', 'middle_name']) && $val !== null ? mb_strtoupper((string)$val) : $val;
            }
        }

        if (empty($set)) {
            return false;
        }

        $sql = "UPDATE students SET " . implode(', ', $set) . ", updated_at = NOW() WHERE student_id = :student_id";
        return $this->execute($sql, $params);
    }
}
