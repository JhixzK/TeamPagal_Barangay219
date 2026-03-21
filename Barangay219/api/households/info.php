<?php
require_once __DIR__ . '/_common.php';

try {
    $residentId = requireResidentHouseholdSession();
    ensureResidentHouseholdSchema();
    ensureResidentHouseholdContractColumns();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetHouseholdInfo($residentId);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handleResidentHouseholdAction($residentId, getRequestBodyData());
    }

    householdJsonResponse(false, null, 'Method not allowed', 405);
} catch (Exception $e) {
    error_log('Household info endpoint error: ' . $e->getMessage());
    $msg = 'Unable to process household request';
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $msg .= ': ' . $e->getMessage();
    }
    householdJsonResponse(false, null, $msg, 500);
}

function ensureResidentHouseholdContractColumns() {
    $db = Database::getInstance();

    $houseCols = getColumnsMap('households');
    addColumnIfMissing('households', $houseCols, 'family_head_id', 'INT(11) NULL');
    addColumnIfMissing('households', $houseCols, 'family_code', 'VARCHAR(30) NULL');
    addColumnIfMissing('households', $houseCols, 'household_id_code', 'VARCHAR(10) NULL');
    addColumnIfMissing('households', $houseCols, 'family_head_code', 'VARCHAR(9) NULL');
    addColumnIfMissing('households', $houseCols, 'household_type', "VARCHAR(80) NULL DEFAULT NULL");
    addColumnIfMissing('households', $houseCols, 'housing_status', "VARCHAR(40) NULL DEFAULT 'owned'");
    addColumnIfMissing('households', $houseCols, 'years_of_residency', 'INT(11) NULL DEFAULT 0');
    addColumnIfMissing('households', $houseCols, 'indigent_household', 'TINYINT(1) NOT NULL DEFAULT 0');
    addColumnIfMissing('households', $houseCols, 'emergency_contact_name', 'VARCHAR(150) NULL');
    addColumnIfMissing('households', $houseCols, 'emergency_contact_relationship', 'VARCHAR(80) NULL');
    addColumnIfMissing('households', $houseCols, 'emergency_contact_number', 'VARCHAR(30) NULL');

    $houseCols = getColumnsMap('households');
    if (isset($houseCols['head_id']) && isset($houseCols['family_head_id'])) {
        // Only sync values that actually exist in residents to avoid FK violations.
        $db->query(
            "UPDATE households h
             JOIN residents r ON r.id = h.head_id
             SET h.family_head_id = h.head_id
             WHERE (h.family_head_id IS NULL OR h.family_head_id = 0)
               AND h.head_id IS NOT NULL"
        );
        $db->query(
            "UPDATE households h
             JOIN residents r ON r.id = h.family_head_id
             SET h.head_id = h.family_head_id
             WHERE (h.head_id IS NULL OR h.head_id = 0)
               AND h.family_head_id IS NOT NULL"
        );
    }

    if (columnExists($db, 'households', 'family_code')) {
        $familyCodeIdx = $db->fetchOne("SHOW INDEX FROM households WHERE Key_name = 'idx_households_family_code'");
        if (!$familyCodeIdx) {
            $db->query('ALTER TABLE households ADD KEY idx_households_family_code (family_code)');
        }
    }

    $resCols = getColumnsMap('residents');
    addColumnIfMissing('residents', $resCols, 'family_code', 'VARCHAR(30) NULL');
    addColumnIfMissing('residents', $resCols, 'family_head_code', 'VARCHAR(9) NULL');
    addColumnIfMissing('residents', $resCols, 'relationship_to_head', 'VARCHAR(100) NULL');
    addColumnIfMissing('residents', $resCols, 'household_id', 'INT(11) NULL');
    addColumnIfMissing('residents', $resCols, 'household_role', 'VARCHAR(80) NULL');
    addColumnIfMissing('residents', $resCols, 'family_head_resident_id', 'INT(11) NULL');

    $memberCols = getColumnsMap('household_members');
    addColumnIfMissing('household_members', $memberCols, 'date_of_birth', 'DATE NULL');
    $memberCols = getColumnsMap('household_members');
    if (isset($memberCols['dob']) && isset($memberCols['date_of_birth'])) {
        $db->query("UPDATE household_members SET date_of_birth = dob WHERE (date_of_birth IS NULL OR date_of_birth = '0000-00-00') AND dob IS NOT NULL");
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS household_history_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            household_id INT(11) NOT NULL,
            action VARCHAR(80) NOT NULL,
            performed_by INT(11) NULL,
            target_resident_id INT(11) NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_household_created (household_id, created_at),
            KEY idx_performed_by (performed_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function householdMemberDateColumn() {
    $memberCols = getColumnsMap('household_members');
    return isset($memberCols['date_of_birth']) ? 'date_of_birth' : 'dob';
}

function householdHeadColumn() {
    $houseCols = getColumnsMap('households');
    return isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';
}

function buildResidentContext($residentId, $context) {
    if (!$context) {
        return [
            'resident_id' => (int)$residentId,
            'household_id' => null,
            'is_head' => false,
            'member_row_id' => null,
            'relationship_to_head' => null
        ];
    }

    return [
        'resident_id' => (int)$residentId,
        'household_id' => (int)$context['household_id'],
        'is_head' => (bool)$context['is_head'],
        'member_row_id' => isset($context['member_row_id']) ? (int)$context['member_row_id'] : null,
        'relationship_to_head' => (string)($context['relationship_to_head'] ?? ((bool)$context['is_head'] ? 'Head' : 'Member'))
    ];
}

/**
 * Normalize Family Head Code for matching (handles FH-03975, FH03975, fh 03975, etc.)
 * Returns uppercase alphanumeric only, e.g. "FH03975".
 */
function normalizeFamilyHeadCodeForMatch($code) {
    return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)$code)));
}

/**
 * SQL expression to normalize family_head_code for comparison (MySQL).
 * Produces same result as normalizeFamilyHeadCodeForMatch.
 */
function familyHeadCodeMatchExpr() {
    return "UPPER(REPLACE(REPLACE(TRIM(COALESCE(family_head_code,'')), ' ', ''), '-', ''))";
}

function generateFamilyCode() {
    $db = Database::getInstance();
    $year = date('Y');

    for ($i = 0; $i < 30; $i++) {
        $suffix = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $familyCode = 'BR219-' . $year . '-' . $suffix;
        if (columnExists($db, 'households', 'family_code')) {
            $exists = $db->fetchOne('SELECT id FROM households WHERE family_code = ? LIMIT 1', [$familyCode]);
            if (!$exists) {
                return $familyCode;
            }
        } else {
            return $familyCode;
        }
    }

    throw new Exception('Unable to generate unique family code');
}

function computeAge($dateOfBirth) {
    if (!$dateOfBirth) {
        return null;
    }

    $ts = strtotime((string)$dateOfBirth);
    if (!$ts) {
        return null;
    }

    $birth = new DateTime(date('Y-m-d', $ts));
    $today = new DateTime('today');
    return max(0, (int)$birth->diff($today)->y);
}

function normalizeMemberStatus($residentStatus, $verificationStatus) {
    $residentStatus = strtolower(trim((string)$residentStatus));
    $verificationStatus = strtolower(trim((string)$verificationStatus));

    if (in_array($residentStatus, ['inactive', 'deceased', 'transferred', 'suspended'], true)) {
        return 'Inactive';
    }

    if (in_array($verificationStatus, ['pending', 'rejected', 'declined', 'denied'], true)) {
        return 'Pending Verification';
    }

    return 'Active';
}

function householdTypeForResponse($val) {
    $v = trim((string)($val ?? ''));
    if ($v === '') return null;
    $legacy = ['nuclear', 'extended', 'single_parent', 'others'];
    if (in_array(strtolower($v), $legacy, true)) return null;
    $register = ['Family Household', 'Couple Only', 'Single Inhabitant', 'Non-Relative Household (Shared / Boarders)', 'Other (Specify)'];
    return in_array($v, $register, true) ? $v : null;
}

function resolveHouseholdTypeForDisplay($db, $household) {
    // Always compute from actual member relationships so it stays in sync
    $householdId = (int)($household['id'] ?? 0);
    if ($householdId > 0) {
        $computed = computeHouseholdTypeFromMembers($db, $householdId);
        if ($computed !== null) return $computed;
    }

    // Fallback to stored value
    return householdTypeForResponse($household['household_type'] ?? null);
}

function computeHouseholdTypeFromMembers($db, $householdId) {
    $headColumn = householdHeadColumn();
    $hh = $db->fetchOne("SELECT `{$headColumn}` AS family_head_id FROM households WHERE id = ? LIMIT 1", [$householdId]);
    if (!$hh) return null;
    $headId = (int)($hh['family_head_id'] ?? 0);

    $rels = [];

    // Get relationships from household_members
    try {
        $hmRows = $db->fetchAll(
            "SELECT resident_id, relationship_to_head FROM household_members WHERE household_id = ?",
            [$householdId]
        );
        foreach ($hmRows as $hm) {
            $rid = (int)($hm['resident_id'] ?? 0);
            if ($rid > 0 && $rid !== $headId) {
                $r = strtolower(trim((string)($hm['relationship_to_head'] ?? '')));
                if ($r !== '' && $r !== 'member' && strpos($r, 'head') === false) {
                    $rels[] = $r;
                }
            }
        }
    } catch (Exception $ignore) {}

    // Fallback: also check residents.relationship_to_head
    if (empty($rels) && columnExists($db, 'residents', 'relationship_to_head')) {
        $resRows = $db->fetchAll(
            "SELECT id, relationship_to_head FROM residents WHERE household_id = ? AND id <> ?",
            [$householdId, $headId]
        );
        foreach ($resRows as $m) {
            $r = strtolower(trim((string)($m['relationship_to_head'] ?? '')));
            if ($r !== '' && $r !== 'member' && strpos($r, 'head') === false) {
                $rels[] = $r;
            }
        }
    }

    if (empty($rels)) {
        $type = 'Single Inhabitant';
    } else {
        $hasSpouse = false;
        $hasFamily = false;
        $hasNonRelative = false;
        foreach ($rels as $r) {
            if (strpos($r, 'spouse') !== false || strpos($r, 'wife') !== false || strpos($r, 'husband') !== false) $hasSpouse = true;
            if (strpos($r, 'son') !== false || strpos($r, 'daughter') !== false || strpos($r, 'child') !== false ||
                strpos($r, 'parent') !== false || strpos($r, 'mother') !== false || strpos($r, 'father') !== false ||
                strpos($r, 'sibling') !== false || strpos($r, 'brother') !== false || strpos($r, 'sister') !== false ||
                strpos($r, 'grandchild') !== false || strpos($r, 'grandparent') !== false ||
                strpos($r, 'nephew') !== false || strpos($r, 'niece') !== false ||
                strpos($r, 'uncle') !== false || strpos($r, 'aunt') !== false || strpos($r, 'cousin') !== false ||
                strpos($r, 'in-law') !== false) $hasFamily = true;
            if (strpos($r, 'boarder') !== false || strpos($r, 'helper') !== false || strpos($r, 'non-relative') !== false ||
                strpos($r, 'tenant') !== false || strpos($r, 'shared') !== false) $hasNonRelative = true;
        }

        if ($hasFamily || ($hasSpouse && count($rels) > 1)) {
            $type = 'Family Household';
        } elseif ($hasSpouse && count($rels) === 1) {
            $type = 'Couple Only';
        } elseif ($hasNonRelative && !$hasFamily && !$hasSpouse) {
            $type = 'Non-Relative Household (Shared / Boarders)';
        } else {
            $type = 'Family Household';
        }
    }

    // Persist for future reads
    if (columnExists($db, 'households', 'household_type')) {
        try {
            $db->query("UPDATE households SET household_type = ? WHERE id = ?", [$type, $householdId]);
        } catch (Exception $ignore) {}
    }

    return $type;
}

function householdAddressLabel($household) {
    $parts = array_filter([
        $household['house_number'] ?? null,
        $household['street'] ?? null,
        $household['barangay'] ?? null,
        $household['city'] ?? null,
        $household['province'] ?? null
    ]);

    return implode(', ', $parts);
}

function logHouseholdHistory($householdId, $action, $details = null, $targetResidentId = null) {
    if (!$householdId || !$action) {
        return;
    }

    try {
        $db = Database::getInstance();
        $performedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $detailsText = null;
        if ($details !== null) {
            $detailsText = is_string($details) ? $details : json_encode($details);
        }

        $db->query(
            'INSERT INTO household_history_logs (household_id, action, performed_by, target_resident_id, details) VALUES (?, ?, ?, ?, ?)',
            [(int)$householdId, (string)$action, $performedBy, $targetResidentId ? (int)$targetResidentId : null, $detailsText]
        );
    } catch (Exception $e) {
        error_log('Household history log error: ' . $e->getMessage());
    }
}

function fetchHouseholdHistory($householdId) {
    $db = Database::getInstance();
    return $db->fetchAll(
        "SELECT h.id, h.action, h.details, h.created_at, u.username AS performed_by
         FROM household_history_logs h
         LEFT JOIN users u ON u.id = h.performed_by
         WHERE h.household_id = ?
         ORDER BY h.created_at DESC, h.id DESC
         LIMIT 20",
        [(int)$householdId]
    );
}

function buildProgramTags($household, $members) {
    $tags = [];

    $hasSenior = false;
    $hasPwd = false;
    $has4ps = false;
    $hasSoloParent = false;

    foreach ($members as $m) {
        if (($m['age'] ?? 0) >= 60) {
            $hasSenior = true;
        }
        if (!empty($m['is_pwd'])) {
            $hasPwd = true;
        }
        if (!empty($m['is_4ps_beneficiary'])) {
            $has4ps = true;
        }
        if (!empty($m['is_solo_parent'])) {
            $hasSoloParent = true;
        }
    }

    if ($hasSenior) {
        $tags[] = ['key' => 'senior_household', 'label' => 'Senior Citizen Household', 'class' => 'tag-senior'];
    }
    if ($hasPwd) {
        $tags[] = ['key' => 'pwd_household', 'label' => 'PWD Household', 'class' => 'tag-pwd'];
    }
    if ($has4ps) {
        $tags[] = ['key' => '4ps_household', 'label' => '4Ps Beneficiary', 'class' => 'tag-4ps'];
    }
    if ($hasSoloParent || strtolower((string)($household['household_type'] ?? '')) === 'single_parent') {
        $tags[] = ['key' => 'solo_parent_household', 'label' => 'Solo Parent Household', 'class' => 'tag-solo'];
    }
    if (!empty($household['indigent_household'])) {
        $tags[] = ['key' => 'indigent_household', 'label' => 'Indigent Household', 'class' => 'tag-indigent'];
    }

    return $tags;
}

function fetchAvailableResidentsForHead($householdId) {
    $db = Database::getInstance();

    $resCols = getColumnsMap('residents');
    $statusCol = isset($resCols['status']) ? 'status' : null;

    $where = '(r.household_id IS NULL OR r.household_id = 0 OR r.household_id = ?)';
    $params = [(int)$householdId];
    if ($statusCol) {
        $where .= " AND LOWER(COALESCE(r.status, 'active')) NOT IN ('deceased', 'transferred')";
    }

    return $db->fetchAll(
        "SELECT r.id AS resident_id, r.resident_code, r.first_name, r.middle_name, r.last_name,
                r.gender, r.birth_date
         FROM residents r
         WHERE {$where}
         ORDER BY r.last_name ASC, r.first_name ASC
         LIMIT 300",
        $params
    );
}

function handleGetHouseholdInfo($residentId) {
    $db = Database::getInstance();
    $context = getResidentHouseholdContext($residentId);
    $contextPayload = buildResidentContext($residentId, $context);

    $base = [
        'context' => $contextPayload,
        'resident_id' => $residentId,
        'role' => null,
        'can_manage_members' => false,
        'household' => null,
        'members' => [],
        'member_stats' => [
            'total' => 0,
            'children' => 0,
            'adults' => 0,
            'seniors' => 0
        ],
        'program_tags' => [],
        'emergency_contact' => null,
        'history_logs' => [],
        'available_households' => [],
        'available_residents' => []
    ];

    if (!$context) {
        $base['available_households'] = getAvailableHouseholdsForJoin();
        householdJsonResponse(true, $base, 'No household assigned yet');
    }

    $householdId = (int)$context['household_id'];
    $headColumn = householdHeadColumn();
    $dateColumn = householdMemberDateColumn();

    $hhExtra = [];
    if (columnExists($db, 'households', 'family_code')) $hhExtra[] = 'h.family_code';
    if (columnExists($db, 'households', 'household_id_code')) $hhExtra[] = 'h.household_id_code';
    if (columnExists($db, 'households', 'family_head_code')) $hhExtra[] = 'h.family_head_code';
    $hhExtraSql = empty($hhExtra) ? '' : ', ' . implode(', ', $hhExtra);

    $household = $db->fetchOne(
        "SELECT h.id, h.`{$headColumn}` AS family_head_id,
                h.house_number, h.street, h.barangay, h.city, h.province,
                h.household_type, h.housing_status, h.years_of_residency,
                h.indigent_household, h.emergency_contact_name,
                h.emergency_contact_relationship, h.emergency_contact_number,
                h.created_at, h.updated_at,
                r.first_name, r.middle_name, r.last_name{$hhExtraSql}
         FROM households h
         LEFT JOIN residents r ON r.id = h.`{$headColumn}`
         WHERE h.id = ?
         LIMIT 1",
        [$householdId]
    );

    if (!$household) {
        $base['available_households'] = getAvailableHouseholdsForJoin();
        householdJsonResponse(true, $base, 'Household link is invalid. Please select role again.');
    }

    $residentCols = getColumnsMap('residents');
    $memberStatusExpr = isset($residentCols['status']) ? 'COALESCE(r.status, "active") AS resident_status' : '"active" AS resident_status';
    $verificationExpr = isset($residentCols['verification_status']) ? 'COALESCE(r.verification_status, "") AS verification_status' : '"" AS verification_status';
    $pwdExpr = isset($residentCols['is_pwd']) ? 'COALESCE(r.is_pwd, 0) AS is_pwd' : '0 AS is_pwd';
    $psExpr = isset($residentCols['is_4ps_beneficiary']) ? 'COALESCE(r.is_4ps_beneficiary, 0) AS is_4ps_beneficiary' : '0 AS is_4ps_beneficiary';
    $soloExpr = isset($residentCols['is_solo_parent']) ? 'COALESCE(r.is_solo_parent, 0) AS is_solo_parent' : '0 AS is_solo_parent';

    $resFhcExpr = columnExists($db, 'residents', 'family_head_code') ? 'r.family_head_code,' : '';
    $resFcExpr = columnExists($db, 'residents', 'family_code') ? 'r.family_code,' : '';
    $resRoleExpr = columnExists($db, 'residents', 'household_role') ? 'r.household_role,' : '';
    $members = $db->fetchAll(
        "SELECT hm.id, hm.household_id, hm.resident_id, hm.relationship_to_head,
                hm.`{$dateColumn}` AS date_of_birth, hm.gender, hm.civil_status,
                hm.created_at, hm.updated_at,
                r.first_name, r.middle_name, r.last_name, r.resident_code,
                {$resFhcExpr}
                {$resFcExpr}
                {$resRoleExpr}
                {$memberStatusExpr}, {$verificationExpr}, {$pwdExpr}, {$psExpr}, {$soloExpr}
         FROM household_members hm
         INNER JOIN residents r ON r.id = hm.resident_id
         WHERE hm.household_id = ?
         ORDER BY CASE WHEN hm.resident_id = ? THEN 0 ELSE 1 END, hm.created_at ASC",
        [$householdId, $household['family_head_id']]
    );

    $designatedHeadId = (int)($household['family_head_id'] ?? 0);
    $mappedMembers = [];
    foreach ($members as $member) {
        $age = computeAge($member['date_of_birth'] ?? null);
        $memberName = formatResidentName($member);
        $rel = (string)($member['relationship_to_head'] ?? '');
        $fhc = trim((string)($member['family_head_code'] ?? ''));
        $isHeadByRole = $rel !== '' && stripos($rel, 'head') !== false;
        $isHeadByFhc = $fhc !== '' && $fhc !== '-';
        $isDesignatedHead = (int)($member['resident_id'] ?? 0) === $designatedHeadId;
        $resHouseholdRole = trim((string)($member['household_role'] ?? ''));
        $displayRel = ($isDesignatedHead || $isHeadByRole || $isHeadByFhc)
            ? 'Head'
            : ($rel !== '' ? $rel : 'Member');
        $mappedMembers[] = [
            'id' => (int)$member['id'],
            'resident_id' => (int)$member['resident_id'],
            'resident_code' => (string)($member['resident_code'] ?? ''),
            'name' => $memberName,
            'relationship_to_head' => $displayRel,
            'household_role' => $resHouseholdRole !== '' ? $resHouseholdRole : null,
            'sex' => ucfirst(strtolower((string)($member['gender'] ?? ''))),
            'date_of_birth' => $member['date_of_birth'],
            'age' => $age,
            'status' => normalizeMemberStatus($member['resident_status'] ?? '', $member['verification_status'] ?? ''),
            'is_self' => (int)$member['resident_id'] === (int)$residentId,
            'is_pwd' => (int)($member['is_pwd'] ?? 0),
            'is_4ps_beneficiary' => (int)($member['is_4ps_beneficiary'] ?? 0),
            'is_solo_parent' => (int)($member['is_solo_parent'] ?? 0),
            'family_code' => (string)($member['family_code'] ?? ''),
            'family_head_code' => (string)($member['family_head_code'] ?? '')
        ];
    }

    // Always include residents linked via residents.household_id even if household_members rows are missing.
    // This keeps the resident view consistent with official assignments.
    $existingResidentIds = [];
    foreach ($mappedMembers as $m) {
        $existingResidentIds[(int)$m['resident_id']] = true;
    }

    $excludeClause = '';
    $excludeParams = [];
    if (!empty($existingResidentIds)) {
        $placeholders = implode(',', array_fill(0, count($existingResidentIds), '?'));
        $excludeClause = " AND r.id NOT IN ($placeholders)";
        $excludeParams = array_keys($existingResidentIds);
    }

    $linkedResFhc = columnExists($db, 'residents', 'family_head_code') ? 'r.family_head_code,' : '';
    $linkedResFc = columnExists($db, 'residents', 'family_code') ? 'r.family_code,' : '';
    $linkedResRole = columnExists($db, 'residents', 'household_role') ? 'r.household_role,' : '';
    $linked = $db->fetchAll(
        "SELECT r.id AS resident_id, r.resident_code, r.first_name, r.middle_name, r.last_name,
                r.birth_date AS date_of_birth, r.gender, {$linkedResFhc}
                {$linkedResFc}
                {$linkedResRole}
                {$memberStatusExpr}, {$verificationExpr}, {$pwdExpr}, {$psExpr}, {$soloExpr}
         FROM residents r
         WHERE r.household_id = ? {$excludeClause}
         ORDER BY CASE WHEN r.id = ? THEN 0 ELSE 1 END, r.id ASC",
        array_merge([(int)$householdId], $excludeParams, [(int)$household['family_head_id']])
    );

    foreach ($linked as $residentRow) {
        $age = computeAge($residentRow['date_of_birth'] ?? null);
        $fhcLinked = trim((string)($residentRow['family_head_code'] ?? ''));
        $isDesignatedLinked = (int)$residentRow['resident_id'] === $designatedHeadId;
        $isHeadLinked = $isDesignatedLinked || ($fhcLinked !== '' && $fhcLinked !== '-');
        $linkedRole = trim((string)($residentRow['household_role'] ?? ''));
        $linkedDisplayRel = $isHeadLinked
            ? 'Head'
            : 'Member';
        $mappedMembers[] = [
            'id' => 0,
            'resident_id' => (int)$residentRow['resident_id'],
            'resident_code' => (string)($residentRow['resident_code'] ?? ''),
            'name' => formatResidentName($residentRow),
            'relationship_to_head' => $linkedDisplayRel,
            'household_role' => $linkedRole !== '' ? $linkedRole : null,
            'sex' => ucfirst(strtolower((string)($residentRow['gender'] ?? ''))),
            'date_of_birth' => $residentRow['date_of_birth'] ?? null,
            'age' => $age,
            'status' => normalizeMemberStatus($residentRow['resident_status'] ?? '', $residentRow['verification_status'] ?? ''),
            'is_self' => (int)$residentRow['resident_id'] === (int)$residentId,
            'is_pwd' => (int)($residentRow['is_pwd'] ?? 0),
            'is_4ps_beneficiary' => (int)($residentRow['is_4ps_beneficiary'] ?? 0),
            'is_solo_parent' => (int)($residentRow['is_solo_parent'] ?? 0),
            'family_code' => (string)($residentRow['family_code'] ?? ''),
            'family_head_code' => (string)($residentRow['family_head_code'] ?? ''),
            'readonly' => true
        ];
    }

    $stats = ['total' => 0, 'children' => 0, 'adults' => 0, 'seniors' => 0];
    foreach ($mappedMembers as $m) {
        $stats['total']++;
        $age = (int)($m['age'] ?? 0);
        if ($age >= 60) {
            $stats['seniors']++;
            $stats['adults']++;
        } elseif ($age >= 18) {
            $stats['adults']++;
        } else {
            $stats['children']++;
        }
    }

    $historyRows = fetchHouseholdHistory($householdId);
    $historyLogs = [];
    foreach ($historyRows as $row) {
        $historyLogs[] = [
            'id' => (int)$row['id'],
            'action' => (string)$row['action'],
            'details' => (string)($row['details'] ?? ''),
            'performed_by' => (string)($row['performed_by'] ?: 'System'),
            'created_at' => (string)$row['created_at']
        ];
    }

    // Resolve the correct Family Head Code and Head Name for the current resident:
    // - Heads: show their own residents.family_head_code and name
    // - Members: show their head's code and name (head = same family_code as member, has family_head_code)
    $displayFamilyHeadCode = (string)($household['family_head_code'] ?? '');
    $displayHeadName = ($household['first_name'] ?? null) ? formatResidentName($household) : '';
    $memberFamilyCode = '';
    $myHeadResidentId = 0;
    if (columnExists($db, 'residents', 'family_head_code')) {
        if ($context['is_head']) {
            $me = $db->fetchOne("SELECT family_head_code FROM residents WHERE id = ? LIMIT 1", [$residentId]);
            $displayFamilyHeadCode = trim((string)($me['family_head_code'] ?? ''));
            if ($displayFamilyHeadCode === '' || $displayFamilyHeadCode === '-') {
                $displayFamilyHeadCode = (string)($household['family_head_code'] ?? '');
            }
            $displayHeadName = $displayHeadName ?: formatResidentName($household);
        } else {
            // Use family_head_resident_id first (stored when member joined via a specific head's code)
            if (columnExists($db, 'residents', 'family_head_resident_id')) {
                $myHead = $db->fetchOne("SELECT family_head_resident_id FROM residents WHERE id = ? LIMIT 1", [$residentId]);
                $storedHeadId = (int)($myHead['family_head_resident_id'] ?? 0);
                if ($storedHeadId > 0) {
                    $headRow = $db->fetchOne(
                        "SELECT id, first_name, middle_name, last_name, family_head_code, family_code FROM residents
                         WHERE id = ? AND household_id = ? LIMIT 1",
                        [$storedHeadId, $householdId]
                    );
                    if ($headRow && !empty(trim((string)($headRow['family_head_code'] ?? '')))) {
                        $myHeadResidentId = (int)$headRow['id'];
                        $displayFamilyHeadCode = trim((string)$headRow['family_head_code']);
                        $displayHeadName = formatResidentName($headRow);
                        $memberFamilyCode = trim((string)($headRow['family_code'] ?? ''));
                    }
                }
            }
            if ($myHeadResidentId <= 0 && columnExists($db, 'residents', 'family_code')) {
                $myFc = $db->fetchOne("SELECT family_code FROM residents WHERE id = ? LIMIT 1", [$residentId]);
                $memberFamilyCode = trim((string)($myFc['family_code'] ?? ''));
                if ($memberFamilyCode !== '') {
                    $designatedHeadId = (int)($household['family_head_id'] ?? 0);
                    // When multiple heads share family_code (e.g. couple household), prefer the non-designated head
                    // so members under the spouse (Head Cruz) see that head instead of the designated one (Head Sad)
                    $headRow = $db->fetchOne(
                        "SELECT id, first_name, middle_name, last_name, family_head_code FROM residents
                         WHERE household_id = ? AND family_code = ? AND family_head_code IS NOT NULL
                           AND TRIM(family_head_code) <> '' AND family_head_code <> '-'
                         ORDER BY CASE WHEN id = ? THEN 1 ELSE 0 END, id
                         LIMIT 1",
                        [$householdId, $memberFamilyCode, $designatedHeadId]
                    );
                    if ($headRow && !empty(trim((string)($headRow['family_head_code'] ?? '')))) {
                        $myHeadResidentId = (int)$headRow['id'];
                        $displayFamilyHeadCode = trim((string)$headRow['family_head_code']);
                        $displayHeadName = formatResidentName($headRow);
                    }
                }
                if ($myHeadResidentId <= 0 && columnExists($db, 'residents', 'family_head_code')) {
                    $hhFhc = trim((string)($household['family_head_code'] ?? ''));
                    if ($hhFhc !== '' && $hhFhc !== '-') {
                        $hhFhcNorm = normalizeFamilyHeadCodeForMatch($hhFhc);
                        $headByCode = $hhFhcNorm !== '' ? $db->fetchOne(
                            "SELECT id, first_name, middle_name, last_name, family_head_code, family_code FROM residents
                             WHERE household_id = ? AND " . familyHeadCodeMatchExpr() . " = ?
                             LIMIT 1",
                            [$householdId, $hhFhcNorm]
                        ) : null;
                        if ($headByCode) {
                            $myHeadResidentId = (int)$headByCode['id'];
                            $displayFamilyHeadCode = trim((string)($headByCode['family_head_code'] ?? ''));
                            $displayHeadName = formatResidentName($headByCode);
                            $memberFamilyCode = trim((string)($headByCode['family_code'] ?? ''));
                        }
                    }
                }
            }
        }
    }

    // For members: show only their family group (their head + themselves + members under that head)
    // Filter by our head's id OR ourselves OR same family_code members—exclude other heads in the household
    if (!$context['is_head'] && ($myHeadResidentId > 0 || $memberFamilyCode !== '')) {
        $mappedMembers = array_filter($mappedMembers, function ($m) use ($residentId, $myHeadResidentId, $memberFamilyCode) {
            $mid = (int)($m['resident_id'] ?? 0);
            $fc = trim((string)($m['family_code'] ?? ''));
            $rel = strtolower((string)($m['relationship_to_head'] ?? ''));
            $fhc = trim((string)($m['family_head_code'] ?? ''));
            $isHead = ($rel !== '' && strpos($rel, 'head') !== false) || ($fhc !== '' && $fhc !== '-');
            if ($mid === $residentId) return true;
            if ($myHeadResidentId > 0 && $mid === $myHeadResidentId) return true;
            if ($memberFamilyCode !== '' && $fc === $memberFamilyCode && !$isHead) return true;
            return false;
        });
        $mappedMembers = array_values($mappedMembers);
        $stats = ['total' => 0, 'children' => 0, 'adults' => 0, 'seniors' => 0];
        foreach ($mappedMembers as $m) {
            $stats['total']++;
            $age = (int)($m['age'] ?? 0);
            if ($age >= 60) {
                $stats['seniors']++;
                $stats['adults']++;
            } elseif ($age >= 18) {
                $stats['adults']++;
            } else {
                $stats['children']++;
            }
        }
    }

    $payload = [
        'context' => $contextPayload,
        'resident_id' => $residentId,
        'role' => $context['is_head'] ? 'head' : 'member',
        'can_manage_members' => (bool)$context['is_head'],
        'household' => [
            'id' => (int)$household['id'],
            'household_code' => (string)($household['household_id_code'] ?? ''),
            'household_id_code' => (string)($household['household_id_code'] ?? ''),
            'family_head_code' => $displayFamilyHeadCode,
            'family_code' => (string)($household['family_code'] ?? ''),
            'family_head_id' => (int)($household['family_head_id'] ?? 0),
            'head_name' => $displayHeadName,
            'address' => householdAddressLabel($household),
            'house_number' => $household['house_number'],
            'street' => $household['street'],
            'barangay' => $household['barangay'],
            'city' => $household['city'],
            'province' => $household['province'],
            'household_type' => resolveHouseholdTypeForDisplay($db, $household),
            'housing_status' => (string)($household['housing_status'] ?? 'owned'),
            'years_of_residency' => (int)($household['years_of_residency'] ?? 0),
            'total_members' => $stats['total'],
            'created_at' => $household['created_at'],
            'updated_at' => $household['updated_at'],
            'indigent_household' => (int)($household['indigent_household'] ?? 0)
        ],
        'member_stats' => $stats,
        'members' => $mappedMembers,
        'program_tags' => buildProgramTags($household, $mappedMembers),
        'emergency_contact' => [
            'name' => (string)($household['emergency_contact_name'] ?? ''),
            'relationship' => (string)($household['emergency_contact_relationship'] ?? ''),
            'contact_number' => (string)($household['emergency_contact_number'] ?? '')
        ],
        'history_logs' => $historyLogs,
        'available_households' => [],
        'available_residents' => $context['is_head'] ? fetchAvailableResidentsForHead($householdId) : []
    ];

    householdJsonResponse(true, $payload, 'Household information fetched');
}

function handleResidentHouseholdAction($residentId, $data) {
    $action = strtolower(sanitizeInput((string)($data['action'] ?? '')));

    if ($action === 'create_household') {
        createHeadHousehold($residentId, $data);
        return;
    }

    if ($action === 'join_household') {
        joinAsMember($residentId, $data);
        return;
    }

    if ($action === 'leave_household') {
        leaveHousehold($residentId);
        return;
    }

    if ($action === 'switch_head') {
        switchHead($residentId, $data);
        return;
    }

    if ($action === 'update_household_meta') {
        updateHouseholdMeta($residentId, $data);
        return;
    }

    $role = strtolower(sanitizeInput((string)($data['role'] ?? '')));
    if ($role === 'head') {
        createHeadHousehold($residentId, $data);
        return;
    }
    if ($role === 'member') {
        joinAsMember($residentId, $data);
        return;
    }

    householdJsonResponse(false, null, 'Invalid household action', 400);
}

function createHeadHousehold($residentId, $data) {
    $houseNumber = sanitizeInput((string)($data['house_number'] ?? $data['address'] ?? ''));
    $street = sanitizeInput((string)($data['street'] ?? ''));
    $barangay = sanitizeInput((string)($data['barangay'] ?? 'Barangay 219'));
    $city = sanitizeInput((string)($data['city'] ?? 'Manila'));
    $province = sanitizeInput((string)($data['province'] ?? 'Metro Manila'));
    $householdType = trim(sanitizeInput((string)($data['household_type'] ?? '')));
    $housingStatus = strtolower(sanitizeInput((string)($data['housing_status'] ?? 'owned')));
    $yearsResidency = max(0, (int)($data['years_of_residency'] ?? 0));

    if ($street === '' && $houseNumber !== '') {
        $street = $houseNumber;
    }
    if ($street === '' || $barangay === '' || $city === '' || $province === '') {
        householdJsonResponse(false, null, 'Address fields are required for head of household', 400);
    }

    $allowedHouseholdType = ['Family Household', 'Couple Only', 'Single Inhabitant', 'Non-Relative Household (Shared / Boarders)', 'Other (Specify)'];
    if ($householdType !== '' && !in_array($householdType, $allowedHouseholdType, true)) {
        $householdType = null;
    }
    if ($householdType === '') {
        $householdType = null;
    }

    $allowedHousing = ['owned', 'renting', 'informal_settler', 'government_housing'];
    if (!in_array($housingStatus, $allowedHousing, true)) {
        $housingStatus = 'owned';
    }

    $db = Database::getInstance();
    $resident = getResidentProfileForHousehold($residentId);
    if (!$resident) {
        householdJsonResponse(false, null, 'Resident not found', 404);
    }

    $headColumn = householdHeadColumn();
    $dateColumn = householdMemberDateColumn();
    $familyCode = generateFamilyCode();

    $db->beginTransaction();
    try {
        $db->query('DELETE FROM household_members WHERE resident_id = ?', [$residentId]);

        $houseCols = getColumnsMap('households');
        $insertCols = [$headColumn, 'house_number', 'street', 'barangay', 'city', 'province', 'household_type', 'housing_status', 'years_of_residency'];
        $insertVals = [$residentId, $houseNumber ?: null, $street, $barangay, $city, $province, $householdType ?: null, $housingStatus, $yearsResidency];
        if (isset($houseCols['family_code'])) {
            $insertCols[] = 'family_code';
            $insertVals[] = $familyCode;
        }

        if (isset($houseCols['head_id']) && $headColumn !== 'head_id') {
            $insertCols[] = 'head_id';
            $insertVals[] = $residentId;
        }
        if (isset($houseCols['family_head_id']) && $headColumn !== 'family_head_id') {
            $insertCols[] = 'family_head_id';
            $insertVals[] = $residentId;
        }

        $colSql = '`' . implode('`,`', $insertCols) . '`';
        $placeholders = implode(', ', array_fill(0, count($insertVals), '?'));
        $db->query("INSERT INTO households ({$colSql}) VALUES ({$placeholders})", $insertVals);
        $householdId = (int)$db->lastInsertId();

        $db->query(
            "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$dateColumn}, gender, civil_status)
             VALUES (?, ?, 'Head', ?, ?, ?)",
            [
                $householdId,
                $residentId,
                $resident['birth_date'] ?: '1990-01-01',
                strtolower((string)($resident['gender'] ?: 'other')),
                strtolower((string)($resident['civil_status'] ?: 'single'))
            ]
        );

        $db->query('UPDATE residents SET household_id = ? WHERE id = ?', [$householdId, $residentId]);
        logHouseholdHistory($householdId, 'Head Changed', 'Household created with resident as head', $residentId);

        $db->commit();
        householdJsonResponse(true, ['household_id' => $householdId, 'family_code' => $familyCode], 'Household created successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function joinAsMember($residentId, $data) {
    $familyHeadCodeRaw = trim((string)($data['family_head_code'] ?? ''));
    $familyHeadCode = preg_replace('/[^A-Z0-9]/', '', strtoupper($familyHeadCodeRaw));
    $familyCode = strtoupper(trim((string)($data['family_code'] ?? ''))); // legacy fallback
    $relationship = sanitizeInput((string)($data['relationship_to_head'] ?? 'Member'));

    if ($familyHeadCode === '' && $familyCode === '') {
        householdJsonResponse(false, null, 'Family head code is required', 400);
    }

    $db = Database::getInstance();
    $headColumn = householdHeadColumn();
    $dateColumn = householdMemberDateColumn();

    // Match by family_head_code: PRIORITIZE resident-level (exact head who owns this FH code).
    // Normalize for matching: FH-03975, FH03975, fh 03975 all match.
    $household = null;
    $selectedHeadResidentId = 0;
    $selectedHeadFamilyCode = null;
    $fhNorm = normalizeFamilyHeadCodeForMatch($familyHeadCodeRaw ?: $familyHeadCode);
    $fhMatchExpr = familyHeadCodeMatchExpr();
    if ($familyHeadCode !== '' || $familyHeadCodeRaw !== '') {
        $hasHouseholdFamilyCode = columnExists($db, 'households', 'family_code');
        $hasHouseholdFhCode = columnExists($db, 'households', 'family_head_code');
        $hasResidentFamilyCode = columnExists($db, 'residents', 'family_code');

        $hhSelect = "id, `{$headColumn}` AS family_head_id";
        if ($hasHouseholdFamilyCode) $hhSelect .= ", family_code";
        if ($hasHouseholdFhCode) $hhSelect .= ", family_head_code";

        // 1. Resident-level FIRST: find the specific head who owns this FH code (correct for multi-head)
        if (columnExists($db, 'residents', 'family_head_code') && $fhNorm !== '') {
            $resSelect = "id, household_id";
            if ($hasResidentFamilyCode) $resSelect .= ", family_code";
            $selectedHead = $db->fetchOne(
                "SELECT {$resSelect} FROM residents
                 WHERE {$fhMatchExpr} = ?
                 AND family_head_code IS NOT NULL AND TRIM(family_head_code) <> '' AND family_head_code <> '-'
                 LIMIT 1",
                [$fhNorm]
            );
            if ($selectedHead && (int)($selectedHead['household_id'] ?? 0) > 0) {
                $selectedHeadResidentId = (int)($selectedHead['id'] ?? 0);
                $selectedHeadFamilyCode = $hasResidentFamilyCode ? trim((string)($selectedHead['family_code'] ?? '')) : '';
                $household = $db->fetchOne(
                    "SELECT {$hhSelect} FROM households WHERE id = ? LIMIT 1",
                    [(int)$selectedHead['household_id']]
                );
            }
        }

        // 2. Fallback: household-level match (when resident doesn't have family_head_code column or no resident match)
        if (!$household && $hasHouseholdFhCode && $fhNorm !== '') {
            $household = $db->fetchOne(
                "SELECT {$hhSelect} FROM households
                 WHERE UPPER(REPLACE(REPLACE(TRIM(COALESCE(family_head_code,'')), ' ', ''), '-', '')) = ?
                 LIMIT 1",
                [$fhNorm]
            );
            if ($household) {
                $selectedHeadResidentId = (int)($household['family_head_id'] ?? 0);
                $selectedHeadFamilyCode = $hasHouseholdFamilyCode ? trim((string)($household['family_code'] ?? '')) : '';
                if ($selectedHeadFamilyCode === '' && $selectedHeadResidentId > 0 && $hasResidentFamilyCode) {
                    $headFcRow = $db->fetchOne("SELECT family_code FROM residents WHERE id = ? LIMIT 1", [$selectedHeadResidentId]);
                    $selectedHeadFamilyCode = trim((string)($headFcRow['family_code'] ?? ''));
                }
            }
        }
    }
    if (!$household && $familyCode !== '' && columnExists($db, 'households', 'family_code')) {
        $hhSelectFc = "id, `{$headColumn}` AS family_head_id";
        if (columnExists($db, 'households', 'family_code')) $hhSelectFc .= ", family_code";
        if (columnExists($db, 'households', 'family_head_code')) $hhSelectFc .= ", family_head_code";
        $household = $db->fetchOne(
            "SELECT {$hhSelectFc} FROM households
             WHERE UPPER(COALESCE(family_code,'')) = ?
             LIMIT 1",
            [$familyCode]
        );
    }

    if (!$household) {
        householdJsonResponse(false, null, 'Invalid family head code', 404);
    }

    $householdId = (int)$household['id'];
    if ((int)$household['family_head_id'] === (int)$residentId) {
        householdJsonResponse(false, null, 'You are already the head of this household', 400);
    }

    $existingMembership = $db->fetchOne('SELECT id, household_id FROM household_members WHERE resident_id = ? LIMIT 1', [$residentId]);
    if ($existingMembership && (int)$existingMembership['household_id'] !== $householdId) {
        householdJsonResponse(false, null, 'Resident already belongs to another household', 409);
    }

    $resident = getResidentProfileForHousehold($residentId);
    if (!$resident) {
        householdJsonResponse(false, null, 'Resident not found', 404);
    }

    $db->beginTransaction();
    try {
        if ($existingMembership) {
            $db->query(
                "UPDATE household_members
                 SET household_id = ?, relationship_to_head = ?, {$dateColumn} = ?, gender = ?, civil_status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE resident_id = ?",
                [
                    $householdId,
                    $relationship,
                    $resident['birth_date'] ?: '1990-01-01',
                    strtolower((string)($resident['gender'] ?: 'other')),
                    strtolower((string)($resident['civil_status'] ?: 'single')),
                    $residentId
                ]
            );
        } else {
            $db->query(
                "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$dateColumn}, gender, civil_status)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $householdId,
                    $residentId,
                    $relationship,
                    $resident['birth_date'] ?: '1990-01-01',
                    strtolower((string)($resident['gender'] ?: 'other')),
                    strtolower((string)($resident['civil_status'] ?: 'single'))
                ]
            );
        }

        $db->query('UPDATE residents SET household_id = ? WHERE id = ?', [$householdId, $residentId]);

        if (columnExists($db, 'residents', 'family_head_code')) {
            // Members should not hold a head code; keep this null to avoid being treated as a head account.
            $db->query('UPDATE residents SET family_head_code = NULL WHERE id = ?', [$residentId]);
        }
        $memberFamilyCode = $selectedHeadFamilyCode;
        if (($memberFamilyCode ?? '') === '' && $selectedHeadResidentId > 0 && columnExists($db, 'residents', 'family_code')) {
            $headRow = $db->fetchOne("SELECT family_code FROM residents WHERE id = ?", [$selectedHeadResidentId]);
            $memberFamilyCode = trim((string)($headRow['family_code'] ?? ''));
            // If head has no family_code, generate one for the head first (so member gets correct group)
            if ($memberFamilyCode === '') {
                try {
                    $memberFamilyCode = generateFamilyCode();
                } catch (Exception $e) {
                    $memberFamilyCode = 'BR219-' . date('Y') . '-' . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
                }
                $db->query('UPDATE residents SET family_code = ? WHERE id = ?', [$memberFamilyCode, $selectedHeadResidentId]);
            }
        }
        if (($memberFamilyCode ?? '') === '') {
            $memberFamilyCode = trim((string)($household['family_code'] ?? ''));
        }
        if (columnExists($db, 'residents', 'family_code') && $memberFamilyCode !== '') {
            $db->query('UPDATE residents SET family_code = ? WHERE id = ?', [$memberFamilyCode, $residentId]);
        }
        if ($selectedHeadResidentId > 0 && columnExists($db, 'residents', 'family_head_resident_id')) {
            $db->query('UPDATE residents SET family_head_resident_id = ? WHERE id = ?', [$selectedHeadResidentId, $residentId]);
        }
        if (columnExists($db, 'residents', 'relationship_to_head') && $relationship !== '') {
            $db->query('UPDATE residents SET relationship_to_head = ? WHERE id = ?', [$relationship, $residentId]);
        }

        logHouseholdHistory($householdId, 'Member Added', 'Resident joined household via family code', $residentId);

        recalcHouseholdType($db, $householdId);

        $db->commit();
        householdJsonResponse(true, [
            'household_id' => $householdId,
            'selected_head_resident_id' => $selectedHeadResidentId > 0 ? $selectedHeadResidentId : null,
            'family_head_code' => $familyHeadCode !== '' ? $familyHeadCode : (string)($household['family_head_code'] ?? ''),
            'family_code' => (string)($household['family_code'] ?? '')
        ], 'Joined household successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Recalculate household_type from member relationships and persist it.
 */
function recalcHouseholdType($db, $householdId) {
    if (!columnExists($db, 'households', 'household_type')) {
        error_log("[recalcHH] household_type column not found");
        return;
    }

    $headColumn = householdHeadColumn();
    $hh = $db->fetchOne("SELECT `{$headColumn}` AS family_head_id FROM households WHERE id = ? LIMIT 1", [$householdId]);
    if (!$hh) {
        error_log("[recalcHH] household not found for id=$householdId");
        return;
    }
    $headId = (int)($hh['family_head_id'] ?? 0);

    // Query non-head members from residents
    $hasRelCol = columnExists($db, 'residents', 'relationship_to_head');
    $resSelect = $hasRelCol ? 'id, relationship_to_head' : 'id';
    $members = $db->fetchAll(
        "SELECT {$resSelect} FROM residents WHERE household_id = ? AND id <> ?",
        [$householdId, $headId]
    );

    // Also include household_members relationships as fallback
    $hmMembers = [];
    try {
        $hmMembers = $db->fetchAll(
            "SELECT resident_id, relationship_to_head FROM household_members WHERE household_id = ?",
            [$householdId]
        );
    } catch (Exception $ignore) {}
    $hmMap = [];
    foreach ($hmMembers as $hm) {
        $rid = (int)($hm['resident_id'] ?? 0);
        if ($rid > 0 && $rid !== $headId) {
            $hmMap[$rid] = strtolower(trim((string)($hm['relationship_to_head'] ?? '')));
        }
    }

    $rels = [];
    foreach ($members as $m) {
        $r = $hasRelCol ? strtolower(trim((string)($m['relationship_to_head'] ?? ''))) : '';
        if ($r === '' || $r === 'member') {
            $mid = (int)($m['id'] ?? 0);
            if (isset($hmMap[$mid]) && $hmMap[$mid] !== '' && $hmMap[$mid] !== 'member') {
                $r = $hmMap[$mid];
            }
        }
        if ($r !== '') $rels[] = $r;
    }

    error_log("[recalcHH] householdId=$householdId headId=$headId memberCount=" . count($members) . " rels=" . json_encode($rels) . " hmMap=" . json_encode($hmMap));

    if (empty($rels)) {
        $type = 'Single Inhabitant';
    } else {
        $hasSpouse = false;
        $hasFamily = false;
        $hasNonRelative = false;
        foreach ($rels as $r) {
            if (strpos($r, 'spouse') !== false || strpos($r, 'wife') !== false || strpos($r, 'husband') !== false) $hasSpouse = true;
            if (strpos($r, 'son') !== false || strpos($r, 'daughter') !== false || strpos($r, 'child') !== false ||
                strpos($r, 'parent') !== false || strpos($r, 'mother') !== false || strpos($r, 'father') !== false ||
                strpos($r, 'sibling') !== false || strpos($r, 'brother') !== false || strpos($r, 'sister') !== false ||
                strpos($r, 'grandchild') !== false || strpos($r, 'grandparent') !== false ||
                strpos($r, 'nephew') !== false || strpos($r, 'niece') !== false ||
                strpos($r, 'uncle') !== false || strpos($r, 'aunt') !== false || strpos($r, 'cousin') !== false ||
                strpos($r, 'in-law') !== false) $hasFamily = true;
            if (strpos($r, 'boarder') !== false || strpos($r, 'helper') !== false || strpos($r, 'non-relative') !== false ||
                strpos($r, 'tenant') !== false || strpos($r, 'shared') !== false) $hasNonRelative = true;
        }

        if ($hasFamily || ($hasSpouse && count($rels) > 1)) {
            $type = 'Family Household';
        } elseif ($hasSpouse && count($rels) === 1) {
            $type = 'Couple Only';
        } elseif ($hasNonRelative && !$hasFamily && !$hasSpouse) {
            $type = 'Non-Relative Household (Shared / Boarders)';
        } else {
            $type = 'Family Household';
        }
    }

    error_log("[recalcHH] Setting type='$type' for householdId=$householdId");
    $db->query("UPDATE households SET household_type = ? WHERE id = ?", [$type, $householdId]);
}

function leaveHousehold($residentId) {
    $context = getResidentHouseholdContext($residentId);
    if (!$context) {
        householdJsonResponse(false, null, 'No household assignment found', 400);
    }
    if (!empty($context['is_head'])) {
        householdJsonResponse(false, null, 'Head of household cannot leave without assigning a new head', 400);
    }

    $householdId = (int)$context['household_id'];
    $db = Database::getInstance();

    $db->beginTransaction();
    try {
        $db->query('DELETE FROM household_members WHERE resident_id = ?', [$residentId]);
        $db->query('UPDATE residents SET household_id = NULL WHERE id = ?', [$residentId]);
        if (columnExists($db, 'residents', 'family_head_resident_id')) {
            $db->query('UPDATE residents SET family_head_resident_id = NULL WHERE id = ?', [$residentId]);
        }
        if (columnExists($db, 'residents', 'family_head_code')) {
            $db->query('UPDATE residents SET family_head_code = NULL WHERE id = ?', [$residentId]);
        }
        if (columnExists($db, 'residents', 'family_code')) {
            $db->query('UPDATE residents SET family_code = NULL WHERE id = ?', [$residentId]);
        }
        logHouseholdHistory($householdId, 'Member Removed', 'Resident left household', $residentId);
        recalcHouseholdType($db, $householdId);
        $db->commit();
        householdJsonResponse(true, null, 'You have left the household successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function switchHead($residentId, $data) {
    $familyHeadCodeRaw = trim((string)($data['family_head_code'] ?? ''));
    $fhNorm = normalizeFamilyHeadCodeForMatch($familyHeadCodeRaw);
    if ($fhNorm === '') {
        householdJsonResponse(false, null, 'Family head code is required', 400);
    }

    $context = getResidentHouseholdContext($residentId);
    if (!$context) {
        householdJsonResponse(false, null, 'No household assignment found', 400);
    }
    if (!empty($context['is_head'])) {
        householdJsonResponse(false, null, 'Only household members can switch head', 400);
    }

    $householdId = (int)$context['household_id'];
    $db = Database::getInstance();
    $fhMatchExpr = familyHeadCodeMatchExpr();

    // Find the head who owns this FH code and is in the SAME household
    if (!columnExists($db, 'residents', 'family_head_code')) {
        householdJsonResponse(false, null, 'Family head code lookup not available', 400);
    }
    $selectedHead = $db->fetchOne(
        "SELECT id, family_code FROM residents
         WHERE household_id = ? AND {$fhMatchExpr} = ?
         AND family_head_code IS NOT NULL AND TRIM(family_head_code) <> '' AND family_head_code <> '-'
         LIMIT 1",
        [$householdId, $fhNorm]
    );
    if (!$selectedHead) {
        householdJsonResponse(false, null, 'Family head code not found in this household', 404);
    }

    $selectedHeadResidentId = (int)$selectedHead['id'];
    $memberFamilyCode = trim((string)($selectedHead['family_code'] ?? ''));
    if ($memberFamilyCode === '' && columnExists($db, 'residents', 'family_code')) {
        $headRow = $db->fetchOne("SELECT family_code FROM residents WHERE id = ?", [$selectedHeadResidentId]);
        $memberFamilyCode = trim((string)($headRow['family_code'] ?? ''));
        if ($memberFamilyCode === '') {
            $memberFamilyCode = generateFamilyCode();
            $db->query('UPDATE residents SET family_code = ? WHERE id = ?', [$memberFamilyCode, $selectedHeadResidentId]);
        }
    }

    $db->beginTransaction();
    try {
        if (columnExists($db, 'residents', 'family_code') && $memberFamilyCode !== '') {
            $db->query('UPDATE residents SET family_code = ? WHERE id = ?', [$memberFamilyCode, $residentId]);
        }
        if (columnExists($db, 'residents', 'family_head_resident_id')) {
            $db->query('UPDATE residents SET family_head_resident_id = ? WHERE id = ?', [$selectedHeadResidentId, $residentId]);
        }
        logHouseholdHistory($householdId, 'Head Switched', 'Member switched to different family head within household', $residentId);
        $db->commit();
        householdJsonResponse(true, ['selected_head_resident_id' => $selectedHeadResidentId], 'Head updated successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function updateHouseholdMeta($residentId, $data) {
    $context = getResidentHouseholdContext($residentId);
    if (!$context || !$context['is_head']) {
        householdJsonResponse(false, null, 'Only household heads can update household details', 403);
    }

    $householdId = (int)$context['household_id'];
    $householdType = trim(sanitizeInput((string)($data['household_type'] ?? '')));
    $housingStatus = strtolower(sanitizeInput((string)($data['housing_status'] ?? '')));
    $yearsResidency = $data['years_of_residency'] ?? null;
    $emergencyName = sanitizeInput((string)($data['emergency_contact_name'] ?? ''));
    $emergencyRelationship = sanitizeInput((string)($data['emergency_contact_relationship'] ?? ''));
    $emergencyNumber = sanitizeInput((string)($data['emergency_contact_number'] ?? ''));

    $allowedType = ['Family Household', 'Couple Only', 'Single Inhabitant', 'Non-Relative Household (Shared / Boarders)', 'Other (Specify)'];
    $legacyType = ['nuclear', 'extended', 'single_parent', 'others'];
    $allowedHousing = ['owned', 'renting', 'informal_settler', 'government_housing'];

    $updates = [];
    $params = [];

    if (array_key_exists('household_type', $data)) {
        if ($householdType !== '' && in_array($householdType, $allowedType, true)) {
            $updates[] = 'household_type = ?';
            $params[] = $householdType;
        } else {
            $updates[] = 'household_type = ?';
            $params[] = null;
        }
    }

    if ($housingStatus !== '' && in_array($housingStatus, $allowedHousing, true)) {
        $updates[] = 'housing_status = ?';
        $params[] = $housingStatus;
    }

    if ($yearsResidency !== null && $yearsResidency !== '') {
        $updates[] = 'years_of_residency = ?';
        $params[] = max(0, (int)$yearsResidency);
    }

    if (array_key_exists('emergency_contact_name', $data)) {
        $updates[] = 'emergency_contact_name = ?';
        $params[] = $emergencyName !== '' ? $emergencyName : null;
    }

    if (array_key_exists('emergency_contact_relationship', $data)) {
        $updates[] = 'emergency_contact_relationship = ?';
        $params[] = $emergencyRelationship !== '' ? $emergencyRelationship : null;
    }

    if (array_key_exists('emergency_contact_number', $data)) {
        $updates[] = 'emergency_contact_number = ?';
        $params[] = $emergencyNumber !== '' ? $emergencyNumber : null;
    }

    if (empty($updates)) {
        householdJsonResponse(false, null, 'No valid fields provided for update', 400);
    }

    $params[] = $householdId;

    $db = Database::getInstance();
    $db->query('UPDATE households SET ' . implode(', ', $updates) . ' WHERE id = ?', $params);
    logHouseholdHistory($householdId, 'Address Updated', 'Household overview fields updated', null);

    householdJsonResponse(true, null, 'Household information updated');
}

function getAvailableHouseholdsForJoin() {
    $db = Database::getInstance();
    $headColumn = householdHeadColumn();

    $fcCol = columnExists($db, 'households', 'family_code') ? 'h.family_code,' : '';

    $rows = $db->fetchAll(
        "SELECT h.id, {$fcCol} h.house_number, h.street, h.barangay, h.city, h.province,
                r.first_name, r.middle_name, r.last_name
         FROM households h
         INNER JOIN residents r ON r.id = h.`{$headColumn}`
         ORDER BY h.id DESC
         LIMIT 200"
    );

    $items = [];
    foreach ($rows as $row) {
        $addressParts = array_filter([
            $row['house_number'] ?: null,
            $row['street'] ?: null,
            $row['barangay'] ?: null,
            $row['city'] ?: null,
            $row['province'] ?: null
        ]);

        $items[] = [
            'id' => (int)$row['id'],
            'family_code' => (string)($row['family_code'] ?? ''),
            'head_name' => formatResidentName($row),
            'address' => implode(', ', $addressParts),
            'label' => ((string)($row['family_code'] ?? ('HH-' . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT)))) . ' | ' . formatResidentName($row)
        ];
    }

    return $items;
}
