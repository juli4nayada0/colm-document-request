<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Core Validation Engine
 */

declare(strict_types=1);

namespace Colm\Validators;

class Validator {
    protected array $errors = [];

    /**
     * Get list of validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Check if validation passed
     */
    public function isValid(): bool {
        return empty($this->errors);
    }

    /**
     * Add an error
     */
    public function addError(string $field, string $message): void {
        $this->errors[$field][] = $message;
    }

    /**
     * Check required field
     */
    public function required(array $data, string $field, string $label): void {
        if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
            $this->addError($field, "{$label} is required.");
        }
    }

    /**
     * Check valid email
     */
    public function email(array $data, string $field, string $label = 'Email'): void {
        if (!empty($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, "{$label} must be a valid email address.");
        }
    }

    /**
     * Check string length
     */
    public function minLength(array $data, string $field, int $min, string $label): void {
        if (!empty($data[$field]) && mb_strlen(trim((string)$data[$field])) < $min) {
            $this->addError($field, "{$label} must be at least {$min} characters.");
        }
    }

    /**
     * Check string max length
     */
    public function maxLength(array $data, string $field, int $max, string $label): void {
        if (!empty($data[$field]) && mb_strlen(trim((string)$data[$field])) > $max) {
            $this->addError($field, "{$label} cannot exceed {$max} characters.");
        }
    }

    /**
     * Check numeric value
     */
    public function numeric(array $data, string $field, string $label): void {
        if (isset($data[$field]) && !is_numeric($data[$field])) {
            $this->addError($field, "{$label} must be a numeric value.");
        }
    }

    /**
     * Check integer min/max range
     */
    public function intRange(array $data, string $field, int $min, int $max, string $label): void {
        if (isset($data[$field])) {
            $val = (int)$data[$field];
            if ($val < $min || $val > $max) {
                $this->addError($field, "{$label} must be between {$min} and {$max}.");
            }
        }
    }

    /**
     * Check valid date format YYYY-MM-DD
     */
    public function date(array $data, string $field, string $label = 'Date'): void {
        if (!empty($data[$field])) {
            $d = \DateTime::createFromFormat('Y-m-d', $data[$field]);
            if (!$d || $d->format('Y-m-d') !== $data[$field]) {
                $this->addError($field, "{$label} must be a valid date in YYYY-MM-DD format.");
            }
        }
    }

    /**
     * Check in list of allowed values
     */
    public function inList(array $data, string $field, array $allowed, string $label): void {
        if (isset($data[$field]) && !in_array($data[$field], $allowed, true)) {
            $this->addError($field, "{$label} must be one of: " . implode(', ', $allowed));
        }
    }
}
