<?php
/**
 * Outbound email (SMTP via PHPMailer). Used for resident-facing links such as account activation.
 */
if (!defined('ACCESS_ALLOWED')) {
    die('Direct access not allowed');
}

require_once __DIR__ . '/../config/email_smtp.php';

/**
 * @return array{sent: bool, skipped: bool, error: ?string}
 */
function sendHtmlMailToResident($toEmail, $subject, $htmlBody, $altBody = '') {
    $toEmail = trim((string)$toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['sent' => false, 'skipped' => true, 'error' => 'invalid_or_missing_email'];
    }

    if (!SMTP_MAIL_ENABLED) {
        return ['sent' => false, 'skipped' => true, 'error' => 'smtp_disabled'];
    }

    $smtpUser = trim((string)SMTP_USERNAME);
    $smtpPass = preg_replace('/\s+/', '', (string)SMTP_PASSWORD);
    if ($smtpUser === '' || $smtpPass === '') {
        return ['sent' => false, 'skipped' => true, 'error' => 'smtp_credentials_incomplete'];
    }

    $from = trim((string)SMTP_FROM_EMAIL);
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('SMTP_FROM_EMAIL is not a valid address; cannot send mail.');
        return ['sent' => false, 'skipped' => false, 'error' => 'from_email_not_configured'];
    }

    require_once __DIR__ . '/lib/PHPMailer/Exception.php';
    require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->Port = (int)SMTP_PORT;
        if (SMTP_DEBUG) {
            $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
        }

        if (defined('SMTP_VERIFY_SSL') && !SMTP_VERIFY_SSL) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $enc = strtolower(trim((string)SMTP_ENCRYPTION));
        if ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = '';
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody !== ''
            ? $altBody
            : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return ['sent' => true, 'skipped' => false, 'error' => null];
    } catch (Throwable $e) {
        error_log('sendHtmlMailToResident: ' . $e->getMessage());
        return ['sent' => false, 'skipped' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Sends the post-approval account activation link to the address the applicant entered on registration.
 * Includes resident code (same value used as login username after activation).
 *
 * @return array{sent: bool, skipped: bool, error: ?string}
 */
function sendResidentRegistrationActivationEmail($toEmail, $residentName, $activationLink, $residentCode = '') {
    $name = trim((string)$residentName);
    $nameEsc = htmlspecialchars($name !== '' ? $name : 'Resident', ENT_QUOTES, 'UTF-8');
    $barangayEsc = htmlspecialchars(BARANGAY_NAME, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($activationLink, ENT_QUOTES, 'UTF-8');
    $code = trim((string)$residentCode);
    $codeEsc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $idBlock = $code !== ''
        ? '<p><strong>Your Resident ID:</strong> <span style="font-size:1.1em;letter-spacing:0.02em;">' . $codeEsc . '</span><br>'
        . '<small>Use this as your <strong>username</strong> when logging in after you set your password.</small></p>'
        : '';

    $subject = APP_NAME . ' — Activate your resident account';
    $html = '<p>Hello ' . $nameEsc . ',</p>'
        . '<p>Your barangay registration has been <strong>approved</strong>.</p>'
        . $idBlock
        . '<p>Use the link below to set your password and activate your online resident account (link expires in 7 days).</p>'
        . '<p><a href="' . $safeLink . '">Activate my account</a></p>'
        . '<p>If the link does not open, copy and paste this URL into your browser:</p>'
        . '<p style="word-break:break-all;">' . $safeLink . '</p>'
        . '<p>— ' . $barangayEsc . '</p>';

    return sendHtmlMailToResident($toEmail, $subject, $html);
}
