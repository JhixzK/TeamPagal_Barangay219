<?php
/**
 * Certificate Request Validation Helper
 * 
 * Provides comprehensive validation for certificate workflow fields
 * to ensure data integrity and consistent state transitions.
 *
 * Usage: $validator = new CertificateValidator();
 *        $errors = $validator->validateApproval($certId);
 *        if (!$errors->isEmpty()) { /* handle validation errors */ }
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../config/database.php';

/**
 * Validation result container with error tracking
 */
class ValidationResult {
    private $errors = [];
    private $warnings = [];
    private $isValid = true;

    public function addError(string $field, string $message): self {
        $this->errors[$field][] = $message;
        $this->isValid = false;
        return $this;
    }

    public function addWarning(string $field, string $message): self {
        $this->warnings[$field][] = $message;
        return $this;
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getWarnings(): array {
        return $this->warnings;
    }

    public function isEmpty(): bool {
        return count($this->errors) === 0;
    }

    public function isValid(): bool {
        return $this->isValid;
    }

    public function getFirstError(): ?string {
        foreach ($this->errors as $fieldErrors) {
            return reset($fieldErrors);
        }
        return null;
    }

    public function getAllErrors(): array {
        $all = [];
        foreach ($this->errors as $fieldErrors) {
            $all = array_merge($all, $fieldErrors);
        }
        return $all;
    }

    public function toArray(): array {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
}

/**
 * Certificate Validator - comprehensive validation for workflow
 */
class CertificateValidator {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Validate resident certificate submission
     * 
     * Checks: name, age, address, purpose are non-empty and properly formatted
     *
     * @param int $residentId
     * @param array $data {name, age, address, purpose_option, purpose_other}
     * @return ValidationResult
     */
    public function validateSubmission(int $residentId, array $data): ValidationResult {
        $result = new ValidationResult();

        // Resident must exist
        if ($residentId <= 0) {
            $result->addError('resident_id', 'Invalid resident ID');
            return $result;
        }

        $resident = $this->db->fetchOne("SELECT id FROM residents WHERE id = ?", [$residentId]);
        if (!$resident) {
            $result->addError('resident_id', 'Resident not found in system');
            return $result;
        }

        // Validate name
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            $result->addError('name', 'Name is required');
        } elseif (strlen($name) < 3) {
            $result->addError('name', 'Name must be at least 3 characters long');
        } elseif (strlen($name) > 255) {
            $result->addError('name', 'Name cannot exceed 255 characters');
        }

        // Validate age
        $age = (int)($data['age'] ?? 0);
        if ($age <= 0) {
            $result->addError('age', 'Age is required and must be greater than 0');
        } elseif ($age > 150) {
            $result->addError('age', 'Age appears invalid (> 150 years)');
        }

        // Validate address
        $address = trim((string)($data['address'] ?? ''));
        if ($address === '') {
            $result->addError('address', 'Address is required');
        } elseif (strlen($address) < 5) {
            $result->addError('address', 'Address must be at least 5 characters long');
        }

        // Validate purpose
        $purposeOption = trim((string)($data['purpose_option'] ?? $data['purpose'] ?? ''));
        if ($purposeOption === '') {
            $result->addError('purpose', 'Purpose of certificate is required');
        } else {
            // If "Others" is selected, purpose_other must be provided
            if (strtolower($purposeOption) === 'others') {
                $purposeOther = trim((string)($data['purpose_other'] ?? ''));
                if ($purposeOther === '') {
                    $result->addError('purpose_other', 'Please specify purpose when "Others" is selected');
                } elseif (strlen($purposeOther) < 5) {
                    $result->addError('purpose_other', 'Purpose details must be at least 5 characters long');
                }
            }
        }

        return $result;
    }

    /**
     * Validate certificate before approval
     * 
     * Checks: required fields exist in pending request before moving to approved status
     *
     * @param int $certificateId
     * @return ValidationResult
     */
    public function validateBeforeApproval(int $certificateId): ValidationResult {
        $result = new ValidationResult();

        // Fetch the certificate request
        $cert = $this->db->fetchOne(
            "SELECT id, status, resident_id, cert_name, cert_age, cert_address, cert_purpose, purpose 
             FROM certificate_requests WHERE id = ?",
            [$certificateId]
        );

        if (!$cert) {
            $result->addError('certificate_id', 'Certificate request not found');
            return $result;
        }

        // Must be in pending status
        if ($cert['status'] !== 'pending') {
            $result->addError('status', 'Only pending requests can be approved');
            return $result;
        }

        // Validate resident exists and is valid
        if ((int)$cert['resident_id'] <= 0) {
            $result->addError('resident_id', 'Certificate has no valid resident link');
        } else {
            $resident = $this->db->fetchOne(
                "SELECT id, first_name, last_name FROM residents WHERE id = ?",
                [(int)$cert['resident_id']]
            );
            if (!$resident) {
                $result->addError('resident_id', 'Resident linked to certificate no longer exists');
            }
        }

        // Validate cert_name (will be populated during approval)
        $name = trim((string)($cert['cert_name'] ?? ''));
        if ($name === '') {
            $result->addWarning('cert_name', 'Certificate name is not pre-filled; will auto-populate from resident data');
        }

        // Validate purpose is present
        $purpose = trim((string)($cert['cert_purpose'] ?? $cert['purpose'] ?? ''));
        if ($purpose === '') {
            $result->addError('purpose', 'Certificate purpose is required but missing');
        }

        return $result;
    }

    /**
     * Validate certificate before marking ready for pickup
     * 
     * Checks: required fields are complete before finalization
     *
     * @param int $certificateId
     * @param array $overrideData Optional data to validate against (for preview before finalize)
     * @return ValidationResult
     */
    public function validateBeforeFinalization(int $certificateId, array $overrideData = []): ValidationResult {
        $result = new ValidationResult();

        // Fetch the certificate request
        $cert = $this->db->fetchOne(
            "SELECT c.id, c.status, c.resident_id, c.cert_name, c.cert_age, c.cert_address, 
                    c.cert_purpose, c.purpose, c.control_number, c.date_issued,
                    r.first_name, r.last_name, r.birth_date, r.civil_status
             FROM certificate_requests c
             LEFT JOIN residents r ON c.resident_id = r.id
             WHERE c.id = ?",
            [$certificateId]
        );

        if (!$cert) {
            $result->addError('certificate_id', 'Certificate request not found');
            return $result;
        }

        // Must be in approved status
        if ($cert['status'] !== 'approved') {
            $result->addError('status', 'Only approved requests can be finalized for pickup');
            return $result;
        }

        // Resolve final values from override data or cert data
        $finalName = trim((string)($overrideData['cert_name'] ?? $cert['cert_name'] ?? ''));
        $finalAge = (int)($overrideData['cert_age'] ?? $cert['cert_age'] ?? 0);
        $finalAddress = trim((string)($overrideData['cert_address'] ?? $cert['cert_address'] ?? ''));
        $finalPurpose = trim((string)($overrideData['cert_purpose'] ?? $cert['cert_purpose'] ?? $cert['purpose'] ?? ''));

        // Validate final name
        if ($finalName === '') {
            $result->addError('cert_name', 'Resident name must be provided for certificate issuance');
        } elseif (strlen($finalName) < 3) {
            $result->addError('cert_name', 'Resident name must be at least 3 characters');
        }

        // Validate final age
        if ($finalAge <= 0) {
            // Try to calculate from birth date if available
            $birthDate = trim((string)($cert['birth_date'] ?? ''));
            if ($birthDate === '') {
                $result->addError('cert_age', 'Resident age must be provided or birth date must be in system');
            } else {
                $birthTs = strtotime($birthDate);
                if ($birthTs === false) {
                    $result->addError('cert_age', 'Unable to calculate age from birth date');
                }
            }
        } elseif ($finalAge > 150) {
            $result->addError('cert_age', 'Age value appears invalid (> 150 years)');
        }

        // Validate final address
        if ($finalAddress === '') {
            $result->addError('cert_address', 'Address must be provided on certificate');
        } elseif (strlen($finalAddress) < 5) {
            $result->addError('cert_address', 'Address must be at least 5 characters');
        }

        // Validate final purpose
        if ($finalPurpose === '') {
            $result->addError('cert_purpose', 'Purpose of certificate must be specified');
        }

        return $result;
    }

    /**
     * Validate rejection data
     * 
     * Checks: rejection reason is provided and meaningful
     *
     * @param int $certificateId
     * @param string $reason
     * @return ValidationResult
     */
    public function validateRejection(int $certificateId, string $reason): ValidationResult {
        $result = new ValidationResult();

        // Fetch certificate
        $cert = $this->db->fetchOne(
            "SELECT id, status FROM certificate_requests WHERE id = ?",
            [$certificateId]
        );

        if (!$cert) {
            $result->addError('certificate_id', 'Certificate request not found');
            return $result;
        }

        // Only pending requests can be rejected
        if ($cert['status'] !== 'pending') {
            $result->addError('status', 'Only pending requests can be rejected');
            return $result;
        }

        // Reason must be provided
        $reason = trim($reason);
        if ($reason === '') {
            $result->addError('reason', 'Rejection reason is required');
        } elseif (strlen($reason) < 5) {
            $result->addError('reason', 'Please provide a meaningful rejection reason (at least 5 characters)');
        } elseif (strlen($reason) > 500) {
            $result->addError('reason', 'Rejection reason cannot exceed 500 characters');
        }

        return $result;
    }

    /**
     * Validate state transition
     * 
     * Checks if the requested transition is allowed by workflow rules
     *
     * @param string $fromStatus Current status
     * @param string $toStatus Desired status
     * @return ValidationResult
     */
    public function validateTransition(string $fromStatus, string $toStatus): ValidationResult {
        $result = new ValidationResult();

        // Define allowed transitions
        $allowedTransitions = [
            'pending' => ['approved', 'rejected'],
            'approved' => ['ready_for_pickup', 'pending'],  // Can go back to pending for re-editing
            'ready_for_pickup' => ['released'],
            'rejected' => [],  // Can't transition from rejected
            'released' => []   // Terminal state
        ];

        $fromStatus = strtolower(trim($fromStatus));
        $toStatus = strtolower(trim($toStatus));

        if (!isset($allowedTransitions[$fromStatus])) {
            $result->addError('from_status', "Unknown status: {$fromStatus}");
            return $result;
        }

        if (!in_array($toStatus, array_keys($allowedTransitions))) {
            $result->addError('to_status', "Unknown status: {$toStatus}");
            return $result;
        }

        if (!in_array($toStatus, $allowedTransitions[$fromStatus])) {
            $result->addError('transition', 
                "Cannot transition from '{$fromStatus}' to '{$toStatus}'. Allowed targets: " . 
                implode(', ', $allowedTransitions[$fromStatus])
            );
        }

        return $result;
    }

    /**
     * Quick check: are all required fields present?
     * 
     * @param array $data
     * @return bool
     */
    public function hasAllRequiredFields(array $data): bool {
        $required = ['name', 'age', 'address', 'purpose'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        return true;
    }
}

?>
