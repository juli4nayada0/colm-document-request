<?php
/**
 * COLM Registrar Document Request and Tracking System
 * EnrollmentRecord Model
 */

declare(strict_types=1);

namespace Colm\Models;

class EnrollmentRecord extends BaseModel {
    /**
     * Get historical enrollment records for a student
     */
    public function getByStudentId(int $studentId): array {
        $sql = "SELECT * FROM enrollment_records WHERE student_id = :id ORDER BY academic_year DESC, semester DESC, enrollment_id DESC";
        return $this->fetchAll($sql, ['id' => $studentId]);
    }

    /**
     * Create enrollment record
     */
    public function create(array $data): int {
        $sql = "INSERT INTO enrollment_records (student_id, program, major, year_level, academic_year, semester, enrollment_status, date_enrolled, created_at, updated_at)
                VALUES (:student_id, :program, :major, :year_level, :academic_year, :semester, :enrollment_status, :date_enrolled, NOW(), NOW())";
        
        $this->execute($sql, [
            'student_id'        => $data['student_id'],
            'program'           => $data['program'],
            'major'             => $data['major'] ?? 'General',
            'year_level'        => $data['year_level'],
            'academic_year'     => $data['academic_year'],
            'semester'          => $data['semester'],
            'enrollment_status' => $data['enrollment_status'] ?? 'Officially Enrolled',
            'date_enrolled'     => $data['date_enrolled'] ?? date('Y-m-d'),
        ]);

        return $this->lastInsertId();
    }
}
