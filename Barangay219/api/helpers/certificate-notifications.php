<?php
/**
 * Enhanced Certificate Notifications
 * 
 * Provides comprehensive notification system with:
 * - HTML email templates
 * - In-app notifications  
 * - Notification status tracking
 * - Audit logging of all notifications
 *
 * Usage: $notifier = new CertificateNotifier();
 *        $notifier->notifyApproved($certificateId, $adminName);
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../config/database.php';

/**
 * Certificate Notifier - manages resident notifications for certificate workflow
 */
class CertificateNotifier {
    private $db;
    private const NOTIFICATION_TYPES = ['info', 'success', 'warning', 'danger'];
    private const EMAIL_FROM = 'noreply@barangay219.local';
    private const EMAIL_SUBJECT_PREFIX = '[Barangay 219 Certificate]';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Notify resident that certificate was submitted successfully
     *
     * @param int $certificateId
     * @param int $residentId
     * @param string $referenceNumber
     * @return bool Success
     */
    public function notifySubmitted(int $certificateId, int $residentId, string $referenceNumber): bool {
        $resident = $this->getResident($residentId);
        if (!$resident) {
            return false;
        }

        $inAppTitle = 'Certificate Request Submitted';
        $inAppMessage = "Your certificate request has been submitted successfully.\n\nReference Number: {$referenceNumber}\n\nYou will be notified when it is approved.";
        $htmlBody = $this->getTwigTemplate('submitted', [
            'resident_name' => $resident['full_name'],
            'reference_number' => $referenceNumber,
            'certificate_type' => $this->getCertificateType($certificateId),
            'submission_date' => date('F d, Y \a\t g:i A')
        ]);

        return $this->sendNotification(
            $residentId,
            $inAppTitle,
            $inAppMessage,
            'success',
            $referenceNumber,
            'submitted',
            $resident['email'] ?? null,
            $htmlBody,
            $certificateId
        );
    }

    /**
     * Notify resident that certificate was approved
     *
     * @param int $certificateId
     * @param int $residentId
     * @param string $adminName
     * @return bool Success
     */
    public function notifyApproved(int $certificateId, int $residentId, string $adminName = 'Barangay Administrator'): bool {
        $resident = $this->getResident($residentId);
        if (!$resident) {
            return false;
        }

        $inAppTitle = 'Certificate Approved';
        $inAppMessage = "Your certificate request has been approved and is now being prepared for you.\n\nYou will receive another notification when it is ready for pickup.";
        $htmlBody = $this->getTwigTemplate('approved', [
            'resident_name' => $resident['full_name'],
            'admin_name' => $adminName,
            'certificate_type' => $this->getCertificateType($certificateId),
            'next_step' => 'Your certificate is being prepared. We will notify you when it is ready for pickup.',
            'approval_date' => date('F d, Y \a\t g:i A')
        ]);

        return $this->sendNotification(
            $residentId,
            $inAppTitle,
            $inAppMessage,
            'success',
            null,
            'approved',
            $resident['email'] ?? null,
            $htmlBody,
            $certificateId
        );
    }

    /**
     * Notify resident that certificate is ready for pickup
     *
     * @param int $certificateId
     * @param int $residentId
     * @param string $controlNumber
     * @param string $pickupLocation E.g., "Barangay Hall, 2nd Floor"
     * @return bool Success
     */
    public function notifyReadyForPickup(int $certificateId, int $residentId, string $controlNumber, 
                                         string $pickupLocation = 'Barangay Hall'): bool {
        $resident = $this->getResident($residentId);
        if (!$resident) {
            return false;
        }

        $inAppTitle = 'Certificate Ready for Pickup';
        $inAppMessage = "Your certificate is now ready for pickup!\n\nControl Number: {$controlNumber}\nLocation: {$pickupLocation}\nPlease present your ID when picking up.";
        $htmlBody = $this->getTwigTemplate('ready_for_pickup', [
            'resident_name' => $resident['full_name'],
            'control_number' => $controlNumber,
            'certificate_type' => $this->getCertificateType($certificateId),
            'pickup_location' => $pickupLocation,
            'ready_date' => date('F d, Y \a\t g:i A'),
            'reminder_text' => 'Please bring a valid ID when coming to pick up your certificate.'
        ]);

        return $this->sendNotification(
            $residentId,
            $inAppTitle,
            $inAppMessage,
            'success',
            $controlNumber,
            'ready_for_pickup',
            $resident['email'] ?? null,
            $htmlBody,
            $certificateId
        );
    }

    /**
     * Notify resident that certificate was released/issued
     *
     * @param int $certificateId
     * @param int $residentId
     * @param string $controlNumber
     * @param string $adminName
     * @return bool Success
     */
    public function notifyReleased(int $certificateId, int $residentId, string $controlNumber, 
                                   string $adminName = 'Barangay Administrator'): bool {
        $resident = $this->getResident($residentId);
        if (!$resident) {
            return false;
        }

        $inAppTitle = 'Certificate Issued';
        $inAppMessage = "Your certificate has been officially issued.\n\nControl Number: {$controlNumber}\n\nFeel free to download or print it from the system.";
        $htmlBody = $this->getTwigTemplate('released', [
            'resident_name' => $resident['full_name'],
            'control_number' => $controlNumber,
            'certificate_type' => $this->getCertificateType($certificateId),
            'issued_by' => $adminName,
            'issuance_date' => date('F d, Y \a\t g:i A'),
            'system_url' => BASE_URL
        ]);

        return $this->sendNotification(
            $residentId,
            $inAppTitle,
            $inAppMessage,
            'success',
            $controlNumber,
            'released',
            $resident['email'] ?? null,
            $htmlBody,
            $certificateId
        );
    }

    /**
     * Notify resident that certificate was rejected
     *
     * @param int $certificateId
     * @param int $residentId
     * @param string $reason Rejection reason
     * @param string $adminName
     * @return bool Success
     */
    public function notifyRejected(int $certificateId, int $residentId, string $reason, 
                                   string $adminName = 'Barangay Administrator'): bool {
        $resident = $this->getResident($residentId);
        if (!$resident) {
            return false;
        }

        $inAppTitle = 'Certificate Request Rejected';
        $inAppMessage = "Unfortunately, your certificate request was rejected.\n\nReason: {$reason}\n\nYou can submit a new request with correct information.";
        $htmlBody = $this->getTwigTemplate('rejected', [
            'resident_name' => $resident['full_name'],
            'rejection_reason' => $reason,
            'certificate_type' => $this->getCertificateType($certificateId),
            'admin_name' => $adminName,
            'rejection_date' => date('F d, Y \a\t g:i A'),
            'next_step' => 'Please correct any issues and submit a new request. If you have questions, please visit the barangay office.'
        ]);

        return $this->sendNotification(
            $residentId,
            $inAppTitle,
            $inAppMessage,
            'danger',
            null,
            'rejected',
            $resident['email'] ?? null,
            $htmlBody,
            $certificateId
        );
    }

    /**
     * Core notification sender - creates both in-app and email notifications
     *
     * @param int $residentId
     * @param string $title
     * @param string $message
     * @param string $type info|success|warning|danger
     * @param string|null $reference Reference number (for tracking)
     * @param string $eventType Event that triggered notification
     * @param string|null $toEmail Email address (if null, no email is sent)
     * @param string|null $htmlTemplate HTML template for email
     * @param int|null $certificateId
     * @return bool Success
     */
    private function sendNotification(int $residentId, string $title, string $message, string $type = 'info',
                                     ?string $reference = null, string $eventType = 'general',
                                     ?string $toEmail = null, ?string $htmlTemplate = null,
                                     ?int $certificateId = null): bool {
        if ($residentId <= 0) {
            return false;
        }

        try {
            // 1. Create in-app notification
            $this->ensureNotificationsSchema();
            $notificationId = $this->db->lastInsertId(
                "INSERT INTO notifications 
                 (resident_id, title, message, type, is_read, created_at) 
                 VALUES (?, ?, ?, ?, 0, NOW())",
                [$residentId, $title, $message, $type]
            );

            // 2. Create email notification if email and template provided
            if ($toEmail && $htmlTemplate) {
                $this->sendEmailNotification(
                    $toEmail,
                    $title,
                    $htmlTemplate,
                    $reference,
                    $certificateId
                );
            }

            // 3. Log the notification event for audit trail
            $this->logNotificationEvent(
                $residentId,
                $eventType,
                $certificateId,
                $reference,
                $toEmail ? 'email_sent' : 'in_app_only'
            );

            return true;
        } catch (Exception $e) {
            error_log("Certificate notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email notification using configured mail system
     *
     * @param string $toEmail
     * @param string $subject
     * @param string $htmlBody
     * @param string|null $reference
     * @param int|null $certificateId
     * @return bool
     */
    private function sendEmailNotification(string $toEmail, string $subject, string $htmlBody,
                                           ?string $reference = null, ?int $certificateId = null): bool {
        try {
            // Prepare email headers
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . self::EMAIL_FROM,
                'X-Mailer: Barangay219-CertificateSystem/1.0'
            ];

            if ($reference) {
                $headers[] = 'X-Certificate-Reference: ' . $reference;
            }

            $emailSubject = self::EMAIL_SUBJECT_PREFIX . ' ' . $subject;

            // PHP mail() function - replace with proper mail service (SendGrid, etc.) in production
            $success = mail(
                $toEmail,
                $emailSubject,
                $htmlBody,
                implode("\r\n", $headers)
            );

            if ($success) {
                // Log successful email
                error_log("Email sent to {$toEmail} for certificate {$certificateId}");
            } else {
                error_log("Failed to send email to {$toEmail} for certificate {$certificateId}");
            }

            return $success;
        } catch (Exception $e) {
            error_log("Email notification error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get resident information for notifications
     *
     * @param int $residentId
     * @return array|null {full_name, email}
     */
    private function getResident(int $residentId): ?array {
        $row = $this->db->fetchOne(
            "SELECT CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name,
                    email FROM residents WHERE id = ? LIMIT 1",
            [$residentId]
        );
        return $row;
    }

    /**
     * Get certificate type label for notifications
     *
     * @param int $certificateId
     * @return string
     */
    private function getCertificateType(int $certificateId): string {
        $cert = $this->db->fetchOne(
            "SELECT certificate_type FROM certificate_requests WHERE id = ? LIMIT 1",
            [$certificateId]
        );
        
        if (!$cert) {
            return 'Certificate';
        }

        $typeMap = [
            'barangay_clearance' => 'Barangay Clearance',
            'certificate_indigency' => 'Certificate of Indigency',
            'certificate_residency' => 'Certificate of Residency',
            'certificate_good_moral' => 'Certificate of Good Moral Character',
            'transfer_request' => 'Transfer Request'
        ];

        return $typeMap[$cert['certificate_type']] ?? ucfirst(str_replace('_', ' ', $cert['certificate_type']));
    }

    /**
     * Get HTML email template (simplified version - replace with Twig/Blade in production)
     *
     * @param string $templateName
     * @param array $data
     * @return string HTML body
     */
    private function getTwigTemplate(string $templateName, array $data = []): string {
        $escape = function($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); };
        
        $baseTemplate = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: #1a4d2e; color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; }
        .section { margin-bottom: 20px; }
        .section h2 { color: #1a4d2e; font-size: 16px; margin-top: 0; }
        .info-box { background: #f0f8f4; border-left: 4px solid #1a4d2e; padding: 15px; margin: 15px 0; }
        .info-box strong { color: #1a4d2e; }
        .button { display: inline-block; background: #1a4d2e; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; }
        .success { color: #0b7a32; }
        .warning { color: #b3261e; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Barangay 219 Certificate System</h1>
        </div>
        <div class="content">
            {CONTENT}
        </div>
        <div class="footer">
            <p>This is an automated notification from Barangay 219 E-Management System.<br>
            Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

        // Template rendering logic
        switch ($templateName) {
            case 'submitted':
                $content = <<<HTML
<h2>Certificate Request Submitted</h2>
<p>Dear {$escape($data['resident_name'])},</p>
<p>Your certificate request has been submitted successfully.</p>
<div class="info-box">
    <strong>Reference Number:</strong> {$escape($data['reference_number'])}<br>
    <strong>Type:</strong> {$escape($data['certificate_type'])}<br>
    <strong>Submission Date:</strong> {$escape($data['submission_date'])}
</div>
<p>Your request is now under review. You will receive a notification once your certificate has been approved.</p>
<p>If you have any questions, please visit the Barangay Hall.</p>
HTML;
                break;

            case 'approved':
                $content = <<<HTML
<h2 class="success">Certificate Approved ✓</h2>
<p>Dear {$escape($data['resident_name'])},</p>
<p>Great news! Your certificate request has been <strong>approved</strong>.</p>
<div class="info-box">
    <strong>Certificate Type:</strong> {$escape($data['certificate_type'])}<br>
    <strong>Approved By:</strong> {$escape($data['admin_name'])}<br>
    <strong>Approval Date:</strong> {$escape($data['approval_date'])}
</div>
<p><strong>Next Step:</strong> {$escape($data['next_step'])}</p>
<p>We will notify you again when your certificate is ready for pickup.</p>
HTML;
                break;

            case 'ready_for_pickup':
                $content = <<<HTML
<h2 class="success">Certificate Ready for Pickup ✓</h2>
<p>Dear {$escape($data['resident_name'])},</p>
<p>Your certificate is now <strong>ready for pickup</strong>!</p>
<div class="info-box">
    <strong>Control Number:</strong> {$escape($data['control_number'])}<br>
    <strong>Certificate Type:</strong> {$escape($data['certificate_type'])}<br>
    <strong>Pickup Location:</strong> {$escape($data['pickup_location'])}<br>
    <strong>Ready Since:</strong> {$escape($data['ready_date'])}
</div>
<p><strong>Important:</strong> {$escape($data['reminder_text'])}</p>
<p>Office Hours: Monday - Friday, 8:00 AM - 5:00 PM</p>
HTML;
                break;

            case 'released':
                $content = <<<HTML
<h2 class="success">Certificate Issued ✓</h2>
<p>Dear {$escape($data['resident_name'])},</p>
<p>Your certificate has been officially <strong>issued</strong>.</p>
<div class="info-box">
    <strong>Certificate Type:</strong> {$escape($data['certificate_type'])}<br>
    <strong>Control Number:</strong> {$escape($data['control_number'])}<br>
    <strong>Issued By:</strong> {$escape($data['issued_by'])}<br>
    <strong>Issuance Date:</strong> {$escape($data['issuance_date'])}
</div>
<p>You can now download or print your certificate from the system.</p>
<a href="{$escape($data['system_url'])}dashboard.php" class="button">View Certificate</a>
HTML;
                break;

            case 'rejected':
                $content = <<<HTML
<h2 class="warning">Certificate Request Rejected</h2>
<p>Dear {$escape($data['resident_name'])},</p>
<p>Unfortunately, your certificate request was <strong>rejected</strong>.</p>
<div class="info-box">
    <strong>Certificate Type:</strong> {$escape($data['certificate_type'])}<br>
    <strong>Rejection Reason:</strong> {$escape($data['rejection_reason'])}<br>
    <strong>Reviewed By:</strong> {$escape($data['admin_name'])}<br>
    <strong>Rejection Date:</strong> {$escape($data['rejection_date'])}
</div>
<p><strong>What's Next?</strong> {$escape($data['next_step'])}</p>
<p>If you believe this is an error, please contact the Barangay Hall directly.</p>
HTML;
                break;

            default:
                $content = '<p>Certificate Notification</p>';
        }

        return str_replace('{CONTENT}', $content, $baseTemplate);
    }

    /**
     * Ensure notifications table exists
     */
    private function ensureNotificationsSchema(): void {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS notifications (
                id INT(11) NOT NULL AUTO_INCREMENT,
                resident_id INT(11) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(20) DEFAULT 'info',
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_resident (resident_id),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Log notification event for audit trail
     *
     * @param int $residentId
     * @param string $eventType
     * @param int|null $certificateId
     * @param string|null $reference
     * @param string $deliveryMethod
     */
    private function logNotificationEvent(int $residentId, string $eventType, ?int $certificateId = null,
                                         ?string $reference = null, string $deliveryMethod = 'in_app_only'): void {
        try {
            $currentUserId = function_exists('getCurrentUserId') ? (int)(getCurrentUserId() ?? 0) : 0;
            $this->db->query(
                "INSERT INTO notification_log (resident_id, event_type, certificate_id, reference, delivery_method, 
                  created_by, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW())",
                [$residentId, $eventType, $certificateId, $reference, $deliveryMethod, 
                 $currentUserId > 0 ? $currentUserId : null]
            );
        } catch (Exception $e) {
            error_log("Notification log error: " . $e->getMessage());
        }
    }
}

?>
