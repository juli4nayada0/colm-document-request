<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Document Validator
 */

declare(strict_types=1);

namespace Colm\Validators;

class DocumentValidator extends Validator {
    public function validateSave(array $data): bool {
        $this->required($data, 'document_code', 'Document Code');
        $this->maxLength($data, 'document_code', 30, 'Document Code');

        $this->required($data, 'document_name', 'Document Name');
        $this->maxLength($data, 'document_name', 150, 'Document Name');

        $this->required($data, 'description', 'Description');
        $this->required($data, 'typical_purpose', 'Typical Purpose');
        $this->required($data, 'basic_requirements', 'Basic Requirements');

        $this->required($data, 'processing_days', 'Processing Days');
        $this->numeric($data, 'processing_days', 'Processing Days');
        $this->intRange($data, 'processing_days', 1, 60, 'Processing Days');

        $this->required($data, 'fee_amount', 'Fee Amount');
        $this->numeric($data, 'fee_amount', 'Fee Amount');

        return $this->isValid();
    }
}
