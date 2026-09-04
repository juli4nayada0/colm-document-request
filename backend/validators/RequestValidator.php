<?php
/**
 * COLM Registrar Document Request and Tracking System
 * Request Validator
 */

declare(strict_types=1);

namespace Colm\Validators;

class RequestValidator extends Validator {
    public function validateSubmission(array $data): bool {
        $this->required($data, 'document_id', 'Document');
        $this->numeric($data, 'document_id', 'Document');

        $this->required($data, 'copies', 'Number of Copies');
        $this->numeric($data, 'copies', 'Number of Copies');
        $this->intRange($data, 'copies', 1, 10, 'Number of Copies');

        $this->required($data, 'purpose', 'Purpose of Request');
        $this->minLength($data, 'purpose', 3, 'Purpose of Request');
        $this->maxLength($data, 'purpose', 255, 'Purpose of Request');

        $this->required($data, 'release_method', 'Release Method');
        $this->inList($data, 'release_method', [
            'Personal Claiming',
            'Authorized Representative',
            'Digital Copy',
            'Other Approved Delivery'
        ], 'Release Method');

        if (!empty($data['preferred_claiming_date'])) {
            $this->date($data, 'preferred_claiming_date', 'Preferred Claiming Date');
        }

        return $this->isValid();
    }

    public function validateStatusUpdate(array $data): bool {
        $this->required($data, 'new_status', 'New Status');
        $this->inList($data, 'new_status', [
            'REQUEST SUBMITTED',
            'PENDING PAYMENT',
            'PAID',
            'FOR PROCESSING',
            'FOR VERIFICATION',
            'FOR PAYMENT',
            'PROCESSING',
            'FOR REVIEW/APPROVAL',
            'READY FOR RELEASE',
            'RELEASED',
            'COMPLETED',
            'ON HOLD',
            'INCOMPLETE',
            'REJECTED/CANCELLED',
            'FOR CORRECTION'
        ], 'New Status');

        return $this->isValid();
    }
}
