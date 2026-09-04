<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Student Validator
 */

declare(strict_types=1);

namespace Colm\Validators;

class StudentValidator extends Validator {
    public function validateCreate(array $data): bool {
        $this->required($data, 'student_number', 'Student Number');
        $this->maxLength($data, 'student_number', 30, 'Student Number');

        $this->required($data, 'last_name', 'Last Name');
        $this->maxLength($data, 'last_name', 80, 'Last Name');

        $this->required($data, 'first_name', 'First Name');
        $this->maxLength($data, 'first_name', 80, 'First Name');

        $this->required($data, 'sex', 'Sex');
        $this->inList($data, 'sex', ['Male', 'Female', 'Other'], 'Sex');

        $this->required($data, 'dob', 'Date of Birth');
        $this->date($data, 'dob', 'Date of Birth');

        $this->required($data, 'contact_number', 'Contact Number');
        $this->maxLength($data, 'contact_number', 30, 'Contact Number');

        $this->required($data, 'email', 'Email Address');
        $this->email($data, 'email', 'Email Address');

        $this->required($data, 'address', 'Address');

        // Optional Enrollment info
        if (isset($data['program'])) {
            $this->required($data, 'program', 'Program');
            $this->required($data, 'year_level', 'Year Level');
            $this->required($data, 'academic_year', 'Academic Year');
            $this->required($data, 'semester', 'Semester');
        }

        return $this->isValid();
    }

    public function validateUpdate(array $data): bool {
        if (isset($data['last_name'])) {
            $this->required($data, 'last_name', 'Last Name');
        }
        if (isset($data['first_name'])) {
            $this->required($data, 'first_name', 'First Name');
        }
        if (isset($data['sex'])) {
            $this->inList($data, 'sex', ['Male', 'Female', 'Other'], 'Sex');
        }
        if (isset($data['dob'])) {
            $this->date($data, 'dob', 'Date of Birth');
        }
        if (isset($data['email'])) {
            $this->email($data, 'email', 'Email Address');
        }
        return $this->isValid();
    }
}
