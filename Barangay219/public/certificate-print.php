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
$cert = $db->fetchOne("SELECT * FROM certificate_requests WHERE id = ?", [$id]);
if (!$cert) { header('Location: ' . BASE_URL . 'certificates.php'); exit; }

$fullName = trim((string)($cert['cert_name'] ?? ''));
$certAddress = trim((string)($cert['cert_address'] ?? ''));
$purposeText = trim((string)($cert['cert_purpose'] ?? $cert['purpose'] ?? ''));
$certBody = trim((string)($cert['cert_body'] ?? ''));

if ($fullName === '') {
    $fullName = 'N/A';
}

if ($certAddress === '') {
    $certAddress = 'Barangay 219, Tondo, Manila';
}

// Certificate format requirement: display postal address up to "Tondo, Manila" only.
$certAddress = preg_replace('/\s+/', ' ', $certAddress) ?? $certAddress;
if (preg_match('/^(.*?\bTondo),/i', $certAddress, $matches)) {
    $certAddress = trim($matches[1]) . ', Manila';
}

$certTypeLabels = [
    'barangay_clearance' => 'Barangay Clearance',
    'certificate_residency' => 'Certificate of Residency',
    'certificate_indigency' => 'Certificate of Indigency',
    'certificate_good_moral' => 'Certificate of Good Moral Character',
    'transfer_request' => 'Transfer Request'
];
$certLabel = $certTypeLabels[$cert['certificate_type']] ?? ucfirst(str_replace('_', ' ', $cert['certificate_type']));
$normalizedCertType = strtolower(trim(str_replace('_', ' ', (string)($cert['certificate_type'] ?? ''))));
$isBarangayCertificate = in_array($normalizedCertType, ['barangay clearance', 'barangay certificate'], true);
$controlNum = $cert['control_number'] ?? 'BRGY219-' . date('Y') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
$issuedBase = $cert['date_issued'] ?? $cert['issued_date'] ?? null;
$issuedTs = $issuedBase ? strtotime((string)$issuedBase) : time();
$issuedDate = date('F d, Y', $issuedTs);
$issuedDay = (int)date('j', $issuedTs);
$issuedMonthYear = date('F Y', $issuedTs);

$certificateBackgroundUrl = null;
$backgroundCandidates = [
    __DIR__ . '/assets/img/cert-template/certificate-request-bg.jpg' => ASSETS_URL . 'img/cert-template/certificate-request-bg.jpg',
    __DIR__ . '/assets/img/cert-template/certificate-request-bg.png' => ASSETS_URL . 'img/cert-template/certificate-request-bg.png',
    __DIR__ . '/assets/img/cert-template/certificate-request-background.jpg' => ASSETS_URL . 'img/cert-template/certificate-request-background.jpg',
    __DIR__ . '/assets/img/cert-template/certificate-request-background.png' => ASSETS_URL . 'img/cert-template/certificate-request-background.png'
];

foreach ($backgroundCandidates as $fsPath => $publicUrl) {
    if (is_file($fsPath)) {
        $certificateBackgroundUrl = $publicUrl;
        break;
    }
}

$hasCustomBackground = ($certificateBackgroundUrl !== null);

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

$placeholderValues = [
    '[NAME]' => $fullName,
    '[ADDRESS]' => $certAddress,
    '[PURPOSE]' => ($purposeText !== '' ? $purposeText : 'legal purpose'),
    '[DATE_ISSUED]' => $issuedDate,
    '[CONTROL_NUMBER]' => $controlNum
];

$residentAge = '';
$residentCivilStatus = '';
$residentId = (int)($cert['resident_id'] ?? 0);
if ($residentId > 0) {
    $residentRow = $db->fetchOne("SELECT birth_date, civil_status FROM residents WHERE id = ? LIMIT 1", [$residentId]);
    if ($residentRow) {
        $residentCivilStatus = trim((string)($residentRow['civil_status'] ?? ''));
        $birthDateRaw = trim((string)($residentRow['birth_date'] ?? ''));
        if ($birthDateRaw !== '') {
            $birthTs = strtotime($birthDateRaw);
            if ($birthTs !== false) {
                $birthDate = new DateTime(date('Y-m-d', $birthTs));
                $today = new DateTime('today');
                $residentAge = (string)$birthDate->diff($today)->y;
            }
        }
    }
}

function styleResidentParagraph(string $paragraphHtml, string $fullName, string $certAddress): string {
    $styled = $paragraphHtml;

    // Ensure this key phrase remains bold regardless of source template/body.
    $styled = preg_replace('/\bbonafide resident\b/i', '<strong>$0</strong>', $styled) ?? $styled;

    // Name formatting
    if ($fullName !== '' && $fullName !== 'N/A') {
        $namePattern = '/' . preg_quote($fullName, '/') . '/i';
        $styled = preg_replace($namePattern, '<span class="resident-field">$0</span>', $styled) ?? $styled;
    }

    // Address/postal address formatting
    if ($certAddress !== '') {
        $addressPattern = '/' . preg_quote($certAddress, '/') . '/i';
        $styled = preg_replace($addressPattern, '<span class="resident-field">$0</span>', $styled) ?? $styled;
    }

    // Age formatting (e.g., 25 years old)
    $styled = preg_replace('/\b\d{1,3}\s*years?\s*old\b/i', '<span class="resident-field">$0</span>', $styled) ?? $styled;

    // Civil/relationship status formatting
    $styled = preg_replace('/\b(single|married|widowed|separated|divorced|annulled)\b/i', '<span class="resident-field">$0</span>', $styled) ?? $styled;

    // Bold and underline the date in "IN WITNESS WHEREOF" paragraph
    if (stripos($styled, 'IN WITNESS WHEREOF') !== false) {
        // Find the LAST "this " naturally to avoid catching "this office."
        $styled = preg_replace_callback('/(this\s+)((?:(?!\bthis\b).)*)$/i', function ($m) {
            $datePart = $m[2];
            $trailing = '';
            // Strip trailing periods, spaces, or <br> tags so they aren't underlined
            while (preg_match('/(\.|\s|<br\s*\/?>)$/i', $datePart, $tm)) {
                $trailing = $tm[1] . $trailing;
                $datePart = substr($datePart, 0, -strlen($tm[1]));
            }
            return $m[1] . '<strong><u>' . $datePart . '</u></strong>' . $trailing;
        }, $styled) ?? $styled;
    }

    return $styled;
}

function buildPurposeChecklistHtml(string $selectedPurpose): string {
    $leftItems = [
        'Application for Employment',
        'Hospital Purpose',
        'Medical Purpose',
        'Bank Transaction',
        'Organized Vending Permit',
        'For Travel Abroad',
    ];
    $rightItems = [
        'School Admission/Requirement',
        'Processing of Calamity',
        'For Livelihood Loan',
        'Indigent Family',
        'DSWD Requirement',
        'Transfer of Residence',
    ];

    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $selectedPurpose)));
    $knownItems = array_map('strtolower', array_merge($leftItems, $rightItems));
    $isOther = $selectedPurpose === '' || !in_array($normalized, $knownItems, true);

    $mark = fn(string $item): string =>
        strtolower($item) === $normalized ? '(&#10003;)' : '(&nbsp;)';

    $colgroup = '<colgroup>'
        . '<col style="width:46%">'
        . '<col style="width:8%">'
        . '<col style="width:46%">'
        . '</colgroup>';

    $rows = '';
    for ($i = 0; $i < 6; $i++) {
        $l = htmlspecialchars($leftItems[$i]);
        $r = htmlspecialchars($rightItems[$i]);
        $ml = $mark($leftItems[$i]);
        $mr = $mark($rightItems[$i]);
        $rows .= "<tr>"
            . "<td style=\"white-space:nowrap;\">{$ml} {$l}</td>"
            . "<td></td>"
            . "<td style=\"white-space:nowrap;\">{$mr} {$r}</td>"
            . "</tr>\n";
    }

    $othersCheck = $isOther ? '(&#10003;)' : '(&nbsp;)';
    $othersValue = $isOther && $selectedPurpose !== ''
        ? ': <strong>' . htmlspecialchars($selectedPurpose) . '</strong>'
        : ': ______________________________';
    $rows .= "<tr><td colspan=\"3\" style=\"padding-top:6px;white-space:nowrap;\">{$othersCheck} Others{$othersValue}</td></tr>\n";

    return '<table class="purpose-table">' . $colgroup . '<tbody>' . $rows . '</tbody></table>';
}

$defaultParagraphsGeneric = [
    '<strong>TO WHOM IT MAY CONCERN:</strong>',
    'This is to certify that <strong>' . htmlspecialchars($fullName) . '</strong> is a resident of <strong>' . htmlspecialchars($certAddress) . '</strong>.',
    'This certification is issued upon the request of the above-named person ' . ($purposeText !== '' ? 'for <strong>' . htmlspecialchars($purposeText) . '</strong>' : 'for legal purpose') . '.',
    'Issued this <strong>' . $issuedDay . '</strong> day of <strong>' . htmlspecialchars($issuedMonthYear) . '</strong> at Barangay 219, Tondo, Manila.'
];

$defaultParagraphsBarangay = [
    '<strong>TO WHOM IT MAY CONCERN:</strong>',
    'This is to certify that <strong>' . htmlspecialchars($fullName) . '</strong>, is a bonafide resident of this Barangay 219, Zone 20, District II, Tondo, Manila with his/her postal address at <strong>' . htmlspecialchars($certAddress) . '</strong>',
    'This certification was issued upon the request of the above mentioned name for whatever legal purpose that may serve him/her best.',
    '<strong>AS PER REQUIREMENT IN SUPPORTING HIS/HER DOCUMENT</strong>' . buildPurposeChecklistHtml($purposeText),
    'IN WITNESS WHEREOF, I have hereunto set my hand and affixed the official seal of this office. Done in the Barangay Hall Barangay 219, Zone 20, District II, City of Manila this <strong>' . $issuedDay . '</strong> day of <strong>' . htmlspecialchars($issuedMonthYear) . '</strong>.'
];

$defaultParagraphsIndigency = [
    '<strong>TO WHOM IT MAY CONCERN:</strong>',
    'This is to certify that <strong>' . htmlspecialchars($fullName) . '</strong>, <strong>' . htmlspecialchars($residentAge !== '' ? $residentAge : 'N/A') . '</strong> years of age, <strong>' . htmlspecialchars($residentCivilStatus !== '' ? ucfirst(strtolower($residentCivilStatus)) : 'N/A') . '</strong>, is a bonafide resident of BARANGAY 219 Zone 20 with postal address at <strong>' . htmlspecialchars($certAddress) . '</strong>.',
    'This is to further certify that the above mentioned name belongs to an indigent family of this barangay.',
    'Issued this <strong>' . htmlspecialchars($issuedDate) . '</strong> at Barangay 219 Zone 20 Manila.'
];

$currentCertType = (string)($cert['certificate_type'] ?? '');
$defaultParagraphs = ($currentCertType === 'barangay_clearance')
    ? $defaultParagraphsBarangay
    : (($currentCertType === 'certificate_indigency') ? $defaultParagraphsIndigency : $defaultParagraphsGeneric);

if ($certBody !== '') {
    $resolvedBody = strtr($certBody, $placeholderValues);
    // Normalize legacy saved bodies that still include extended Manila suffix.
    $resolvedBody = preg_replace('/\bTondo,\s*Manila,\s*Metro Manila,\s*Manila\b/i', 'Tondo, Manila', $resolvedBody) ?? $resolvedBody;
    // Treat saved body as final snapshot text. Split by blank lines into printable paragraphs.
    $blocks = preg_split('/\R{2,}/', trim($resolvedBody));
    $paragraphs = [];
    foreach ($blocks as $block) {
        $cleanBlock = trim((string)$block);
        if ($cleanBlock === '') {
            continue;
        }
        // Filter each individual line within the block to catch multi-line footer groups.
        $lines = preg_split('/\R/', $cleanBlock);
        $filteredLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // Remove fixed-title line
            if (preg_match('/^C\s*E\s*R\s*T\s*I\s*F\s*I\s*C\s*A\s*T\s*I\s*O\s*N\s*:?$/i', $line)) continue;
            // Remove footer lines that are rendered separately
            if (preg_match('/^Approved by\s*:?$/i', $line)) continue;
            if (preg_match('/^Punong Barangay\s*$/i', $line)) continue;
            if (preg_match('/^Control No[:\s]/i', $line)) continue;
            if (preg_match('/LEGASPI/i', $line)) continue;
            $filteredLines[] = $line;
        }
        if (empty($filteredLines)) {
            continue;
        }
        // Force key heading lines into their own paragraph even if saved body used single newlines.
        $lineParagraphs = [];
        $currentLines = [];
        foreach ($filteredLines as $line) {
            $normalizedLine = strtoupper(preg_replace('/\s+/', ' ', trim($line)));
            $isHeadingLine = in_array($normalizedLine, [
                'TO WHOM IT MAY CONCERN:',
                'AS PER REQUIREMENT IN SUPPORTING HIS/HER DOCUMENT'
            ], true);

            if ($isHeadingLine) {
                if (!empty($currentLines)) {
                    $lineParagraphs[] = implode("\n", $currentLines);
                    $currentLines = [];
                }
                $lineParagraphs[] = $line;
                continue;
            }

            $currentLines[] = $line;
        }
        if (!empty($currentLines)) {
            $lineParagraphs[] = implode("\n", $currentLines);
        }

        foreach ($lineParagraphs as $paragraphBlock) {
            // Detect if this block is the raw text checklist from the database, and replace it purely with our HTML table
            if (preg_match('/^\(?\s*(?:✓|x|\s)?\s*\)?\s*Application for Employment/i', trim($paragraphBlock))) {
                $paragraphs[] = buildPurposeChecklistHtml($purposeText);
                continue;
            }

            $escaped = nl2br(htmlspecialchars($paragraphBlock));
            $escaped = str_replace('TO WHOM IT MAY CONCERN:', '<strong>TO WHOM IT MAY CONCERN:</strong>', $escaped);
            $escaped = str_replace('AS PER REQUIREMENT IN SUPPORTING HIS/HER DOCUMENT', '<strong>AS PER REQUIREMENT IN SUPPORTING HIS/HER DOCUMENT</strong>', $escaped);
            $paragraphs[] = $escaped;
        }
    }

    if (empty($paragraphs)) {
        $paragraphs = $defaultParagraphs;
    }
} else {
    $paragraphs = $defaultParagraphs;
}

$paragraphs = array_map(static function ($paragraph) use ($fullName, $certAddress) {
    return styleResidentParagraph((string)$paragraph, $fullName, $certAddress);
}, $paragraphs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($certLabel); ?> - <?php echo htmlspecialchars($fullName); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: 8.5in 11in; margin: 0; }
        html, body {
            margin: 0;
            padding: 0;
            width: 8.5in;
            height: 11in;
        }
        body {
            font-family: "Times New Roman", serif;
            color: #111;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page {
            width: 8.5in;
            height: 11in;
            margin: 0 auto;
            overflow: hidden;
        }
        .cert {
            width: 100%;
            height: 100%;
            border: 0;
            position: relative;
            overflow: hidden;
            background: #fff;
        }
        .template-bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: center;
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
        .has-template-bg .control {
            position: absolute;
            top: 4mm;
            right: 8mm;
            margin: 0;
            z-index: 2;
            font-size: 10.5px;
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
        .has-template-bg .header,
        .has-template-bg .left-panel {
            display: none;
        }
        .has-template-bg .content {
            display: block;
            margin-top: 56mm;
        }
        .has-template-bg .right-panel {
            padding-left: 36mm;
            padding-right: 20mm;
        }
        .has-template-bg .subject {
            display: none;
        }
        .has-template-bg .body {
            font-family: "Times New Roman", serif;
            font-size: 12px;
            line-height: 1.85;
            text-align: justify;
        }
        .has-template-bg .body p {
            margin-bottom: 6px;
            text-indent: 34px;
        }
        .has-template-bg .body p:first-of-type {
            margin-top: 0;
            margin-bottom: 10px;
            font-weight: 700;
            text-indent: 0;
        }
        .has-template-bg .footer {
            margin-top: 8mm;
            padding-right: 0;
        }
        .has-template-bg .signature {
            width: 250px;
        }
        .has-template-bg .signature .approved {
            margin-bottom: 52px;
            font-size: 12px;
            text-align: center;
        }
        .has-template-bg .signature .name {
            font-size: 12px;
        }
        .has-template-bg .signature .label {
            font-size: 12px;
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
        .certification-title {
            text-align: center;
            margin: 12px 0 16px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 7px;
            text-decoration: underline;
            text-underline-offset: 5px;
            text-transform: uppercase;
        }
        .body {
            font-family: "Times New Roman", serif;
            font-size: 12px;
            line-height: 1.62;
            text-align: justify;
        }
        .body p { margin-bottom: 8px; text-indent: 34px; }
        .body p:first-of-type { text-indent: 0; font-weight: 700; margin-top: 0; margin-bottom: 10px; }
        .body p.no-indent { text-indent: 0; }
        .purpose-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 4px;
            font-size: 12px;
            font-family: "Times New Roman", serif;
            table-layout: fixed;
        }
        .resident-field {
            font-family: Calibri, "Segoe UI", Tahoma, sans-serif;
            font-size: 14px;
            font-weight: 700;
            font-style: italic;
            text-decoration: underline;
        }
        .uline {
            display: inline-block;
            border-bottom: 1px solid #111;
            padding: 0 4px 1px;
            font-weight: 700;
        }
        .strong { text-transform: uppercase; }
        .footer {
            font-family: "Times New Roman", serif;
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
        .signature .approved { margin-bottom: 52px; font-size: 12px; text-align: center; }
        .signature .name {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            text-decoration: underline;
            margin-bottom: 2px;
        }
        .signature .label {
            font-size: 12px;
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
            html, body {
                width: 8.5in;
                height: 11in;
                overflow: hidden;
            }
            body { background: #fff; }
            .page {
                width: 8.5in;
                height: 11in;
                padding: 0;
                margin: 0;
                break-after: avoid;
                page-break-after: avoid;
            }
            .cert {
                width: 100%;
                height: 100%;
            }
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
        <div class="cert<?php echo $hasCustomBackground ? ' has-template-bg' : ''; ?>">
            <?php if ($certificateBackgroundUrl !== null): ?>
            <div class="template-bg" style="background-image: url('<?php echo htmlspecialchars($certificateBackgroundUrl); ?>');" aria-hidden="true"></div>
            <?php else: ?>
            <div class="watermark">
                <img src="<?php echo ASSETS_URL; ?>img/barangay_logo2.png" alt="">
            </div>
            <?php endif; ?>
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
                        <?php if ($isBarangayCertificate): ?>
                        <div class="certification-title">C E R T I F I C A T I O N</div>
                        <?php else: ?>
                        <div class="subject"><?php echo htmlspecialchars($subjectLine); ?></div>
                        <?php endif; ?>

                        <div class="body">
                            <?php if ($isBarangayCertificate): ?>
                            <br><br>
                            <?php endif; ?>
                            <?php foreach ($paragraphs as $paragraph): ?>
                            <?php
                                $plainParagraph = trim(strip_tags($paragraph));
                                $isHeadingParagraph = str_starts_with($paragraph, '<strong>');
                                $isChecklistParagraph = (bool) preg_match('/^\((?:\s|✓)\)/u', $plainParagraph);
                                $hasTable = str_contains($paragraph, '<table');
                                $paragraphClass = ($isHeadingParagraph || $isChecklistParagraph || $hasTable) ? ' class="no-indent"' : '';
                            ?>
                            <?php if ($hasTable): ?>
                            <div<?php echo $paragraphClass; ?>><?php echo $paragraph; ?></div>
                            <?php else: ?>
                            <p<?php echo $paragraphClass; ?>><?php echo $paragraph; ?></p>
                            <?php endif; ?>
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
