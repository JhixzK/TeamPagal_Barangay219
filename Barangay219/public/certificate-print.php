<?php
/**
 * Certificate Print/PDF - Printable certificate view
 * User can Print to PDF from browser
 */
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('certificates');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . 'certificates.php'); exit; }

$db = Database::getInstance();
$cert = $db->fetchOne("SELECT c.*, r.first_name, r.middle_name, r.last_name, r.suffix, r.address, r.birth_date, r.gender, r.civil_status, r.occupation, r.citizenship
    FROM certificate_requests c LEFT JOIN residents r ON c.resident_id = r.id WHERE c.id = ?", [$id]);
if (!$cert) { header('Location: ' . BASE_URL . 'certificates.php'); exit; }

$fullName = trim($cert['first_name'] . ' ' . ($cert['middle_name'] ?? '') . ' ' . $cert['last_name'] . ' ' . ($cert['suffix'] ?? ''));
$certTypeLabels = [
    'barangay_clearance' => 'Barangay Clearance',
    'certificate_residency' => 'Certificate of Residency',
    'certificate_indigency' => 'Certificate of Indigency',
    'certificate_good_moral' => 'Certificate of Good Moral Character',
    'transfer_request' => 'Transfer Request'
];
$certLabel = $certTypeLabels[$cert['certificate_type']] ?? ucfirst(str_replace('_', ' ', $cert['certificate_type']));
$controlNum = $cert['control_number'] ?? 'CTRL-' . $id . '-' . date('Y');
$issuedDate = $cert['issued_date'] ? date('F d, Y', strtotime($cert['issued_date'])) : date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($certLabel); ?> - <?php echo htmlspecialchars($fullName); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; padding: 40px; max-width: 210mm; margin: 0 auto; }
        .cert { border: 3px double #333; padding: 40px; min-height: 280mm; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header .barangay { font-size: 18px; font-weight: bold; }
        .header .republic { font-size: 12px; margin-top: 5px; }
        .title { text-align: center; font-size: 20px; font-weight: bold; margin: 30px 0 25px; text-transform: uppercase; letter-spacing: 2px; }
        .body { font-size: 14px; line-height: 1.8; text-align: justify; margin: 20px 0; }
        .body .name { font-weight: bold; text-decoration: underline; }
        .body .purpose { font-style: italic; }
        .footer { margin-top: 50px; text-align: right; }
        .footer .sig-line { border-top: 1px solid #333; width: 200px; margin-left: auto; padding-top: 5px; font-size: 12px; }
        .control { position: absolute; top: 20px; right: 20px; font-size: 11px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #0d6efd; color: white; border: none; cursor: pointer; border-radius: 5px;">
            Print / Save as PDF
        </button>
        <a href="<?php echo BASE_URL; ?>certificates.php" style="margin-left: 10px;">Back to Certificates</a>
    </div>

    <div class="cert">
        <div class="control">Control No: <?php echo htmlspecialchars($controlNum); ?></div>

        <div class="header">
            <div class="republic">Republic of the Philippines</div>
            <div class="barangay"><?php echo htmlspecialchars(BARANGAY_NAME); ?></div>
            <h1>OFFICE OF THE BARANGAY CAPTAIN</h1>
        </div>

        <div class="title"><?php echo htmlspecialchars($certLabel); ?></div>

        <div class="body">
            <p>To whom it may concern:</p>
            <p style="margin-top: 20px;">
                This is to certify that <span class="name"><?php echo htmlspecialchars($fullName); ?></span>,
                of legal age, <?php echo $cert['civil_status'] ? htmlspecialchars($cert['civil_status']) : 'single'; ?>,
                <?php echo $cert['citizenship'] ? htmlspecialchars($cert['citizenship']) : 'Filipino'; ?> citizen,
                and a resident of <?php echo htmlspecialchars($cert['address'] ?? 'this barangay'); ?>,
                <?php if (!empty($cert['purpose'])): ?>
                has requested this certification <span class="purpose">for <?php echo htmlspecialchars($cert['purpose']); ?></span>.
                <?php else: ?>
                has requested this certification.
                <?php endif; ?>
            </p>
            <p style="margin-top: 20px;">
                This certification is issued upon the request of the above-named person for whatever legal purpose it may serve.
            </p>
        </div>

        <div class="footer">
            <div class="sig-line">Barangay Captain</div>
            <div style="font-size: 11px; margin-top: 5px;"><?php echo $issuedDate; ?></div>
        </div>
    </div>
</body>
</html>
