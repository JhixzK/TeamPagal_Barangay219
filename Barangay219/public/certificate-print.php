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
$issuedTs = $cert['issued_date'] ? strtotime((string)$cert['issued_date']) : time();
$issuedDate = date('F d, Y', $issuedTs);
$issuedDay = (int)date('j', $issuedTs);
$issuedMonthYear = date('F Y', $issuedTs);

// Template values (replace with actual officials when available in system settings)
$captainName = 'HON. FERNANDO M. LEGASPI';
$secretaryName = 'ADRIAN M. RINO';
$treasurerName = 'KATRINA C. CHIDIAN';
$skChairName = 'MICO E. SORIA';
$sangguniangBarangay = [
    'Fernando M. Legaspi',
    'Punong Barangay'
];
$barangayKagawad = [
    'Eduardo R. Grande',
    'Ferdinand W. Mejos',
    'Anita B. Carretero',
    'Luzviminda L. Lagman',
    'June F. Bonagua',
    'Joel T. Olitres',
    'Emma T. Borilla'
];

$subjectLine = strtoupper($certLabel);
$purposeText = trim((string)($cert['purpose'] ?? ''));
$residentAddress = trim((string)($cert['address'] ?? '')) ?: 'Barangay 219, Tondo, Manila';
$civilStatus = $cert['civil_status'] ? ucfirst((string)$cert['civil_status']) : 'Single';
$citizenship = $cert['citizenship'] ?: 'Filipino';

switch ($cert['certificate_type']) {
    case 'certificate_indigency':
        $subjectLine = 'BARANGAY INDIGENCY';
        $paragraphs = [
            'TO WHOM IT MAY CONCERN:',
            'This is to certify that <span class="uline strong">' . htmlspecialchars($fullName) . '</span>, of legal age, <span class="uline">' . htmlspecialchars($civilStatus) . '</span>, and a bona fide resident of <span class="uline">' . htmlspecialchars($residentAddress) . '</span>.',
            'This is to further certify that the above-mentioned person belongs to an indigent family of this barangay' . ($purposeText !== '' ? ' and requested this for <span class="uline">' . htmlspecialchars($purposeText) . '</span>' : '') . '.',
            'Issued this <span class="uline">' . $issuedDay . 'th</span> day of <span class="uline">' . htmlspecialchars($issuedMonthYear) . '</span> at Barangay 219 Zone 20 Manila.'
        ];
        break;
    case 'certificate_residency':
        $subjectLine = 'CERTIFICATE OF RESIDENCY';
        $paragraphs = [
            'TO WHOM IT MAY CONCERN:',
            'This is to certify that <strong>' . htmlspecialchars($fullName) . '</strong>, of legal age, ' . htmlspecialchars($civilStatus) . ', ' . htmlspecialchars($citizenship) . ' citizen, is a bona fide resident of <strong>' . htmlspecialchars($residentAddress) . '</strong>, Barangay 219, Tondo, Manila.',
            'This certification is issued upon the request of the above-named person ' . ($purposeText !== '' ? 'for <strong>' . htmlspecialchars($purposeText) . '</strong>' : 'for legal purpose') . '.',
            'Issued this <strong>' . $issuedDay . '</strong> day of <strong>' . htmlspecialchars($issuedMonthYear) . '</strong> at Barangay 219, Tondo, Manila.'
        ];
        break;
    case 'certificate_good_moral':
        $subjectLine = 'CERTIFICATE OF GOOD MORAL CHARACTER';
        $paragraphs = [
            'TO WHOM IT MAY CONCERN:',
            'This is to certify that <strong>' . htmlspecialchars($fullName) . '</strong>, of legal age, ' . htmlspecialchars($civilStatus) . ', ' . htmlspecialchars($citizenship) . ' citizen, and a resident of <strong>' . htmlspecialchars($residentAddress) . '</strong>, is known in this community to be a person of good moral character and has no derogatory record filed in this barangay as of this date.',
            'This certification is issued upon request of the above-named person ' . ($purposeText !== '' ? 'for <strong>' . htmlspecialchars($purposeText) . '</strong>' : 'for legal purpose') . '.',
            'Issued this <strong>' . $issuedDay . '</strong> day of <strong>' . htmlspecialchars($issuedMonthYear) . '</strong> at Barangay 219, Tondo, Manila.'
        ];
        break;
    case 'barangay_clearance':
        $subjectLine = 'BARANGAY CLEARANCE';
        $paragraphs = [
            'TO WHOM IT MAY CONCERN:',
            'This is to certify that <strong>' . htmlspecialchars($fullName) . '</strong>, of legal age, ' . htmlspecialchars($civilStatus) . ', ' . htmlspecialchars($citizenship) . ' citizen, and a resident of <strong>' . htmlspecialchars($residentAddress) . '</strong>, is known to be of good standing in this barangay and has no pending complaint or derogatory record as of this date.',
            'This clearance is issued upon request of the above-named person ' . ($purposeText !== '' ? 'for <strong>' . htmlspecialchars($purposeText) . '</strong>' : 'for legal purpose') . '.',
            'Issued this <strong>' . $issuedDay . '</strong> day of <strong>' . htmlspecialchars($issuedMonthYear) . '</strong> at Barangay 219, Tondo, Manila.'
        ];
        break;
    default:
        $paragraphs = [
            'TO WHOM IT MAY CONCERN:',
            'This is to certify that <strong>' . htmlspecialchars($fullName) . '</strong>, of legal age, ' . htmlspecialchars($civilStatus) . ', ' . htmlspecialchars($citizenship) . ' citizen, and a resident of <strong>' . htmlspecialchars($residentAddress) . '</strong>, has requested this certification.',
            'This certification is issued upon the request of the above-named person ' . ($purposeText !== '' ? 'for <strong>' . htmlspecialchars($purposeText) . '</strong>' : 'for legal purpose') . '.',
            'Issued this <strong>' . $issuedDay . '</strong> day of <strong>' . htmlspecialchars($issuedMonthYear) . '</strong> at Barangay 219, Tondo, Manila.'
        ];
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($certLabel); ?> - <?php echo htmlspecialchars($fullName); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4; margin: 8mm; }
        body { font-family: "Times New Roman", serif; color: #111; background: #fff; }
        .page { max-width: 210mm; margin: 0 auto; }
        .cert {
            border: 1px solid #8a8a8a;
            min-height: 281mm;
            position: relative;
            overflow: hidden;
            background: #fff;
        }
        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            z-index: 0;
        }
        .watermark img {
            width: 340px;
            opacity: 0.09;
        }
        .sheet {
            position: absolute;
            inset: 0;
            padding: 8mm 8mm 8mm 8mm;
            z-index: 1;
        }
        .control {
            font-family: "Times New Roman", serif;
            font-size: 12px;
            text-align: right;
            margin-bottom: 4px;
        }
        .header {
            text-align: center;
            margin-top: 1mm;
        }
        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .header-row .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
        }
        .header-text {
            flex: 1;
            text-align: center;
            line-height: 1.25;
            padding: 0 8px;
        }
        .header-text .line1 { font-size: 16px; text-transform: uppercase; line-height: 1.15; }
        .header-text .line2 { font-size: 15px; text-transform: uppercase; line-height: 1.15; }
        .header-text .line3 { font-size: 17px; font-weight: 700; text-transform: uppercase; line-height: 1.2; }
        .header-text .line4 { font-size: 16px; font-weight: 700; text-transform: uppercase; line-height: 1.2; }
        .header-text .line5 { font-size: 15px; text-transform: uppercase; line-height: 1.2; }
        .header-divider {
            border-top: 1px solid #7d7d7d;
            margin-top: 4px;
        }
        .content {
            display: grid;
            grid-template-columns: 122px 1fr;
            gap: 14px;
            margin-top: 10px;
        }
        .left-panel {
            background: linear-gradient(180deg, #b6e5f0 0%, #b6e5f0 78%, #98d6e6 78%, #98d6e6 100%);
            border-right: 1px solid #8fbac3;
            padding: 10px 8px 10px 8px;
            font-family: Arial, sans-serif;
            font-size: 12px;
            min-height: 208mm;
        }
        .left-title {
            text-align: center;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-bottom: 10px;
        }
        .left-divider { border-top: 1px solid #6e8a90; margin: 10px 10px; }
        .left-name { text-align: center; font-weight: 700; margin-bottom: 4px; line-height: 1.25; }
        .left-role { text-align: center; font-weight: 700; margin-bottom: 10px; line-height: 1.2; }
        .left-list .left-name { font-weight: 700; font-size: 11.5px; }
        .right-panel { padding-right: 6px; }
        .subject {
            text-align: center;
            margin: 12px 0 16px;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-decoration: underline;
        }
        .body {
            font-size: 16.5px;
            line-height: 1.62;
            text-align: justify;
            min-height: 122mm;
        }
        .body p { margin-bottom: 8px; text-indent: 34px; }
        .body p:first-child { text-indent: 0; font-weight: 700; margin-bottom: 10px; }
        .uline {
            display: inline-block;
            border-bottom: 1px solid #111;
            padding: 0 4px 1px;
            font-weight: 700;
        }
        .strong { text-transform: uppercase; }
        .footer {
            margin-top: 14mm;
            display: flex;
            justify-content: flex-end;
            padding-right: 5mm;
        }
        .signature {
            text-align: center;
            width: 265px;
            font-family: "Times New Roman", serif;
        }
        .signature .approved { margin-bottom: 16px; font-size: 16px; text-align: left; }
        .signature .name {
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 2px;
        }
        .signature .label {
            font-size: 16px;
        }
        .prepared {
            margin-top: 22px;
            font-size: 10px;
            text-align: left;
            color: #222;
        }
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
            .page { padding: 0; margin: 0; max-width: none; }
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
            <div class="watermark">
                <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="">
            </div>
            <div class="sheet">
                <div class="control">
                    Control No.: <?php echo htmlspecialchars($controlNum); ?>
                </div>
                <div class="header">
                    <div class="header-row">
                        <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" class="logo" alt="Barangay Logo">
                        <div class="header-text">
                            <div class="line1">Republic of the Philippines</div>
                            <div class="line2">City of Manila</div>
                            <div class="line3">Office of the Punong Barangay</div>
                            <div class="line4">Barangay 219 Zone 20 District II Manila</div>
                            <div class="line5">Tindalo cor. Cavite St, Tondo, Manila</div>
                        </div>
                        <img src="<?php echo ASSETS_URL; ?>img/barangaylogo.png" class="logo" alt="Seal">
                    </div>
                    <div class="header-divider"></div>
                </div>
                <div class="content">
                    <div class="left-panel">
                        <div class="left-title">SANGGUNIANG<br>BARANGAY</div>
                        <div class="left-name"><?php echo htmlspecialchars($sangguniangBarangay[0]); ?></div>
                        <div class="left-role"><?php echo htmlspecialchars($sangguniangBarangay[1]); ?></div>
                        <div class="left-divider"></div>
                        <div class="left-title">BARANGAY<br>KAGAWAD</div>
                        <div class="left-list">
                            <?php foreach ($barangayKagawad as $kagawad): ?>
                            <div class="left-name"><?php echo htmlspecialchars($kagawad); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="left-divider"></div>
                        <div class="left-name"><?php echo htmlspecialchars($skChairName); ?></div>
                        <div class="left-role">SK Chairman</div>
                        <div class="left-name"><?php echo htmlspecialchars($secretaryName); ?></div>
                        <div class="left-role">Barangay Secretary</div>
                        <div class="left-name"><?php echo htmlspecialchars($treasurerName); ?></div>
                        <div class="left-role">Barangay Treasurer</div>
                    </div>
                    <div class="right-panel">
                        <div class="subject"><?php echo htmlspecialchars($subjectLine); ?></div>

                        <div class="body">
                            <?php foreach ($paragraphs as $paragraph): ?>
                            <p><?php echo $paragraph; ?></p>
                            <?php endforeach; ?>
                        </div>

                        <div class="footer">
                            <div class="signature">
                                <div class="approved">Approved by:</div>
                                <div class="name"><?php echo htmlspecialchars($captainName); ?></div>
                                <div class="label">Punong Barangay</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
