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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Source+Sans+3:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4; margin: 18mm; }
        body { font-family: 'Source Sans 3', sans-serif; color: #1d1f23; background: #fff; }
        .page { max-width: 210mm; margin: 0 auto; padding: 10mm 8mm; }
        .cert {
            border: 1.5px solid #2d3548;
            padding: 18mm 16mm;
            min-height: 260mm;
            position: relative;
            background: linear-gradient(180deg, #ffffff 0%, #fbfcff 100%);
        }
        .control {
            position: absolute;
            top: 10mm;
            right: 12mm;
            font-size: 11px;
            letter-spacing: 0.6px;
        }
        .seal {
            position: absolute;
            top: 14mm;
            left: 12mm;
            width: 56px;
            height: 56px;
            border: 2px solid #2d3548;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .header { text-align: center; margin-bottom: 18mm; }
        .header .republic { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
        .header .barangay { font-size: 18px; font-weight: 600; margin-top: 4px; }
        .header .office { font-family: 'Cormorant Garamond', serif; font-size: 26px; margin-top: 6px; letter-spacing: 1px; }
        .header .divider {
            width: 80%;
            height: 1px;
            background: #2d3548;
            margin: 10px auto 0;
        }
        .title {
            text-align: center;
            font-family: 'Cormorant Garamond', serif;
            font-size: 24px;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin: 18mm 0 8mm;
        }
        .body { font-size: 14.5px; line-height: 1.85; text-align: justify; }
        .body .name { font-weight: 600; text-decoration: underline; }
        .body .purpose { font-style: italic; }
        .footer { margin-top: 22mm; display: flex; justify-content: flex-end; }
        .signature {
            text-align: center;
            width: 220px;
        }
        .signature .line { border-top: 1px solid #2d3548; margin-top: 36px; }
        .signature .label { font-size: 12px; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px; }
        .signature .date { font-size: 11px; margin-top: 4px; color: #444; }
        .no-print { margin-bottom: 16px; }
        .no-print button {
            padding: 8px 16px;
            background: #0d6efd;
            color: #fff;
            border: none;
            cursor: pointer;
            border-radius: 999px;
        }
        .no-print a { margin-left: 10px; color: #0d6efd; text-decoration: none; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .page { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">
            Print / Save as PDF
        </button>
        <a href="<?php echo BASE_URL; ?>certificates.php" style="margin-left: 10px;">Back to Certificates</a>
    </div>

    <div class="page">
        <div class="cert">
            <div class="control">Control No: <?php echo htmlspecialchars($controlNum); ?></div>
            <div class="seal">Barangay 219</div>

            <div class="header">
                <div class="republic">Republic of the Philippines</div>
                <div class="barangay"><?php echo htmlspecialchars(BARANGAY_NAME); ?></div>
                <div class="office">Office of the Barangay Captain</div>
                <div class="divider"></div>
            </div>

            <div class="title"><?php echo htmlspecialchars($certLabel); ?></div>

            <div class="body">
                <p>To whom it may concern:</p>
                <p style="margin-top: 14px;">
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
                <p style="margin-top: 14px;">
                    This certification is issued upon the request of the above-named person for whatever legal purpose it may serve.
                </p>
            </div>

            <div class="footer">
                <div class="signature">
                    <div class="line"></div>
                    <div class="label">Barangay Captain</div>
                    <div class="date">Issued on <?php echo $issuedDate; ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
