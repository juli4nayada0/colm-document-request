<?php
/**
 * COLM Registrar Document Request and Tracking System
 * User Validator
 */

declare(strict_types=1);

namespace Colm\Validators;

class UserValidator extends Validator {
    public function validateLogin(array $data): bool {
        $this->required($data, 'username', 'Username / Student Number');
        $this->required($data, 'password', 'Password');
        return $this->isValid();
    }

    public function validateCreate(array $data): bool {
        $this->required($data, 'username', 'Username');
        $this->minLength($data, 'username', 3, 'Username');
        $this->maxLength($data, 'username', 60, 'Username');
        
        $this->required($data, 'password', 'Password');
        $this->minLength($data, 'password', 8, 'Password');

        $this->required($data, 'role', 'Role');
        $this->inList($data, 'role', ['Student', 'Personnel', 'Registrar', 'Admin'], 'Role');

        if (isset($data['is_active']) && !in_array((string)$data['is_active'], ['0', '1'], true)) {
            $this->addError('is_active', 'Account status must be active or inactive.');
        }

        return $this->isValid();
    }

    public function validateUpdate(array $data): bool {
        if (isset($data['role'])) {
            $this->inList($data, 'role', ['Student', 'Personnel', 'Registrar', 'Admin'], 'Role');
        }
        if (isset($data['password']) && trim((string)$data['password']) !== '') {
            $this->minLength($data, 'password', 8, 'Password');
        }
        return $this->isValid();
    }
}
