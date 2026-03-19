<?php
/**
 * Enhanced Certificate Workflow Logging
 * 
 * Provides detailed audit logging for certificate workflow with:
 * - Field change tracking
 * - Admin action logging
 * - State transition history
 * - Resident access logs
 *
 * Usage: $logger = new CertificateWorkflowLogger();
 *        $logger->logApproval($certificateId, $changedFields);
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../config/database.php';

/**
 * Enhanced Certificate Workflow Logger
 */
class CertificateWorkflowLogger {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Log certificate submission
     *
     * @param int $certificateId
     * @param int $residentId
     * @param int $userId User who submitted
     * @param array $data Submission data
     */
    public function logSubmission(int $certificateId, int $residentId, int $userId, array $data): void {
        $this->ensureLogTables();
        
        $dataToLog = [
            'resident_id' => $residentId,
            'certificate_type' => $data['certificate_type'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'submitted_by' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->insertWorkflowLog($certificateId, 'SUBMITTED', $dataToLog, $userId);
    }

    /**
     * Log certificate approval with field validation
     *
     * @param int $certificateId
     * @param int $adminId
     * @param array $beforeData Current data before approval
     * @param array $afterData Data post-approval
     */
    public function logApproval(int $certificateId, int $adminId, array $beforeData, array $afterData): void {
        $this->ensureLogTables();
        
        $changes = $this->computeFieldChanges($beforeData, $afterData);
        
        $dataToLog = [
            'admin_id' => $adminId,
            'changes' => $changes,
            'before_state' => [
                'status' => $beforeData['status'] ?? null,
                'cert_name' => $beforeData['cert_name'] ?? null,
                'cert_age' => $beforeData['cert_age'] ?? null,
                'cert_address' => $beforeData['cert_address'] ?? null,
                'cert_purpose' => $beforeData['cert_purpose'] ?? null
            ],
            'after_state' => [
                'status' => $afterData['status'] ?? 'approved'
            ],
            'validation_passed' => $afterData['validation_passed'] ?? true,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->insertWorkflowLog($certificateId, 'APPROVED', $dataToLog, $adminId);
    }

    /**
     * Log certificate finalization (ready for pickup)
     *
     * @param int $certificateId
     * @param int $adminId
     * @param array $finalData Final certificate data with control number
     */
    public function logFinalization(int $certificateId, int $adminId, array $finalData): void {
        $this->ensureLogTables();
        
        $dataToLog = [
            'admin_id' => $adminId,
            'control_number' => $finalData['control_number'] ?? null,
            'date_issued' => $finalData['date_issued'] ?? null,
            'cert_name' => $finalData['cert_name'] ?? null,
            'cert_age' => $finalData['cert_age'] ?? null,
            'cert_address' => $finalData['cert_address'] ?? null,
            'cert_purpose' => $finalData['cert_purpose'] ?? null,
            'reason' => $finalData['reason'] ?? 'Finalized and prepared for pickup',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->insertWorkflowLog($certificateId, 'FINALIZED', $dataToLog, $adminId);
    }

    /**
     * Log certificate rejection
     *
     * @param int $certificateId
     * @param int $adminId
     * @param string $reason Rejection reason
     * @param array $metadata Additional metadata
     */
    public function logRejection(int $certificateId, int $adminId, string $reason, array $metadata = []): void {
        $this->ensureLogTables();
        
        $dataToLog = array_merge([
            'admin_id' => $adminId,
            'rejection_reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s')
        ], $metadata);

        $this->insertWorkflowLog($certificateId, 'REJECTED', $dataToLog, $adminId);
    }

    /**
     * Log certificate release
     *
     * @param int $certificateId
     * @param int $adminId
     * @param string $controlNumber
     */
    public function logRelease(int $certificateId, int $adminId, string $controlNumber): void {
        $this->ensureLogTables();
        
        $dataToLog = [
            'admin_id' => $adminId,
            'control_number' => $controlNumber,
            'released_by' => $adminId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->insertWorkflowLog($certificateId, 'RELEASED', $dataToLog, $adminId);
    }

    /**
     * Log draft save (edits to pending/approved cert)
     *
     * @param int $certificateId
     * @param int $adminId
     * @param array $beforeData
     * @param array $afterData
     */
    public function logDraftSave(int $certificateId, int $adminId, array $beforeData, array $afterData): void {
        $this->ensureLogTables();
        
        $changes = $this->computeFieldChanges($beforeData, $afterData);
        
        if (empty($changes)) {
            return; // Don't log if no actual changes
        }
        
        $dataToLog = [
            'admin_id' => $adminId,
            'changes' => $changes,
            'edited_fields' => array_keys($changes),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->insertWorkflowLog($certificateId, 'DRAFT_SAVED', $dataToLog, $adminId);
    }

    /**
     * Log field validation failure
     *
     * @param int $certificateId
     * @param int $userId
     * @param array $validationErrors
     */
    public function logValidationFailure(int $certificateId, int $userId, array $validationErrors): void {
        $this->ensureLogTables();
        
        $dataToLog = [
            'user_id' => $userId,
            'validation_errors' => $validationErrors,
            'error_count' => count($validationErrors),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->insertWorkflowLog($certificateId, 'VALIDATION_FAILED', $dataToLog, $userId);
    }

    /**
     * Log resident view/access of certificate
     *
     * @param int $certificateId
     * @param int $residentId
     */
    public function logResidentAccess(int $certificateId, int $residentId): void {
        $this->ensureLogTables();
        
        $dataToLog = [
            'resident_id' => $residentId,
            'action' => 'viewed_certificate',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        try {
            $this->db->query(
                "INSERT INTO certificate_access_log (certificate_id, resident_id, action, ip_address, accessed_at) 
                 VALUES (?, ?, ?, ?, NOW())",
                [$certificateId, $residentId, 'view', $_SERVER['REMOTE_ADDR'] ?? null]
            );
        } catch (Exception $e) {
            error_log("Access log error: " . $e->getMessage());
        }
    }

    /**
     * Log PDF generation
     *
     * @param int $certificateId
     * @param int $userId
     * @param string $reason Purpose of PDF (print, download, archive)
     */
    public function logPDFGeneration(int $certificateId, int $userId, string $reason = 'print'): void {
        $this->ensureLogTables();
        
        $dataToLog = [
            'user_id' => $userId,
            'reason' => $reason,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->insertWorkflowLog($certificateId, 'PDF_GENERATED', $dataToLog, $userId);
    }

    /**
     * Get complete audit trail for a certificate
     *
     * @param int $certificateId
     * @return array Array of audit log entries
     */
    public function getAuditTrail(int $certificateId): array {
        $this->ensureLogTables();
        
        $logs = $this->db->fetchAll(
            "SELECT id, action, details, created_by, created_at FROM certificate_workflow_log 
             WHERE certificate_id = ? ORDER BY created_at DESC",
            [$certificateId]
        );

        // Decode JSON details
        foreach ($logs as &$log) {
            if (!empty($log['details'])) {
                $log['details'] = json_decode($log['details'], true);
            }
        }

        return $logs;
    }

    /**
     * Get state transition history for a certificate
     *
     * @param int $certificateId
     * @return array Array of status transitions with times
     */
    public function getStateTransitionHistory(int $certificateId): array {
        $this->ensureLogTables();
        
        return $this->db->fetchAll(
            "SELECT action, created_at, created_by FROM certificate_workflow_log 
             WHERE certificate_id = ? AND action IN ('SUBMITTED', 'APPROVED', 'FINALIZED', 'REJECTED', 'RELEASED')
             ORDER BY created_at ASC",
            [$certificateId]
        );
    }

    /**
     * Compute field changes between before and after states
     *
     * @param array $before
     * @param array $after
     * @return array Map of field => [old => value, new => value]
     */
    private function computeFieldChanges(array $before, array $after): array {
        $changes = [];
        $trackingFields = ['cert_name', 'cert_age', 'cert_address', 'cert_purpose', 'cert_body', 
                          'purpose', 'remarks', 'admin_id', 'control_number', 'date_issued'];

        foreach ($trackingFields as $field) {
            $beforeVal = $before[$field] ?? null;
            $afterVal = $after[$field] ?? null;
            
            if ($beforeVal !== $afterVal) {
                $changes[$field] = [
                    'old' => $beforeVal,
                    'new' => $afterVal
                ];
            }
        }

        return $changes;
    }

    /**
     * Create workflow log entry
     *
     * @param int $certificateId
     * @param string $action
     * @param array $details
     * @param int $userId
     */
    private function insertWorkflowLog(int $certificateId, string $action, array $details, int $userId): void {
        try {
            $detailsJson = json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            
            $this->db->query(
                "INSERT INTO certificate_workflow_log (certificate_id, action, details, created_by, created_at) 
                 VALUES (?, ?, ?, ?, NOW())",
                [$certificateId, $action, $detailsJson, $userId]
            );
        } catch (Exception $e) {
            error_log("Workflow log error: " . $e->getMessage());
        }
    }

    /**
     * Ensure logging tables exist
     */
    private function ensureLogTables(): void {
        // Certificate workflow log
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS certificate_workflow_log (
                id INT(11) NOT NULL AUTO_INCREMENT,
                certificate_id INT(11) NOT NULL,
                action VARCHAR(50) NOT NULL,
                details JSON DEFAULT NULL,
                created_by INT(11) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_certificate (certificate_id),
                KEY idx_created_at (created_at),
                KEY idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Certificate access log
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS certificate_access_log (
                id INT(11) NOT NULL AUTO_INCREMENT,
                certificate_id INT(11) NOT NULL,
                resident_id INT(11) NOT NULL,
                action VARCHAR(50) DEFAULT 'view',
                ip_address VARCHAR(45) DEFAULT NULL,
                accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_certificate (certificate_id),
                KEY idx_resident (resident_id),
                KEY idx_accessed_at (accessed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

?>
