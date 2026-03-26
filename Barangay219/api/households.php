<?php
/**
 * E-Barangay Information Management System
 * Household Management API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/household_head_transfer_guard.php';
require_once __DIR__ . '/../includes/indigent-classification.php';

requireLogin();
requireModuleAccess('households');

// Must load before the switch: getHousehold() calls isRegisterHouseholdType() which uses these globals.
$REGISTER_HOUSEHOLD_TYPES = ['Family Household', 'Couple Only', 'Single Inhabitant', 'Non-Relative Household (Shared / Boarders)', 'Other (Specify)'];
$LEGACY_HOUSEHOLD_TYPES = ['nuclear', 'extended', 'single_parent', 'others'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listHouseholds();
        break;

    case 'list_streets':
        listStreetsSummary();
        break;
    
    case 'get':
        getHousehold();
        break;

    // Return possible family-head choices for a household (distinct head codes).
    case 'family_heads':
        listFamilyHeads();
        break;
    
    case 'create':
        if (!canPerformModulePermission('households', 'can_create')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        createHousehold();
        break;
    
    case 'update':
        if (!canPerformModulePermission('households', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateHousehold();
        break;
    
    case 'delete':
        if (!canPerformModulePermission('households', 'can_delete')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        deleteHousehold();
        break;
    
    case 'members':
        getHouseholdMembers();
        break;
    
    case 'add_member':
        addHouseholdMember();
        break;
    
    case 'remove_member':
        removeHouseholdMember();
        break;
    
    case 'get_member':
        getHouseholdMemberDetails();
        break;
    
    case 'update_member':
        updateHouseholdMember();
        break;
    
    case 'delete_member':
        deleteHouseholdMember();
        break;
    
    case 'update_household_details':
        updateHouseholdDetails();
        break;

    case 'assign_head_official':
        if (!canPerformModulePermission('households', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        assignHeadOfficial();
        break;

    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * List distinct family-head options inside a household.
 * Used when assigning a member to a household that already has multiple heads.
 */
function listFamilyHeads() {
    $householdId = intval($_GET['household_id'] ?? $_POST['household_id'] ?? 0);
    if ($householdId <= 0) {
        sendResponse(false, 'household_id is required', null, 400);
        return;
    }

    $db = Database::getInstance();

    if (!tableExists($db, 'residents')) {
        sendResponse(false, 'Residents table missing', null, 500);
        return;
    }

    if (!columnExists($db, 'residents', 'family_head_code')) {
        sendResponse(true, 'No family_head_code column in residents', []);
        return;
    }

    $hasFamilyCode = columnExists($db, 'residents', 'family_code');
    $hasRelationship = columnExists($db, 'residents', 'relationship_to_head');
    $hasHouseholdRole = columnExists($db, 'residents', 'household_role');

    $selectCols = [
        'id',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'family_head_code'
    ];
    if ($hasFamilyCode) $selectCols[] = 'family_code';
    if ($hasRelationship) $selectCols[] = 'relationship_to_head';
    if ($hasHouseholdRole) $selectCols[] = 'household_role';

    $sql = "SELECT " . implode(', ', $selectCols) . "
            FROM residents
            WHERE household_id = ?
              AND family_head_code IS NOT NULL
              AND TRIM(family_head_code) <> ''
              AND TRIM(family_head_code) <> '-'";

    $rows = $db->fetchAll($sql, [$householdId]);
    if (!$rows) {
        sendResponse(true, 'Family heads retrieved', []);
        return;
    }

    // Group by family_head_code and pick a representative head for each code.
    $groups = [];
    foreach ($rows as $r) {
        $code = trim((string)($r['family_head_code'] ?? ''));
        if ($code === '' || $code === '-') continue;

        if (!isset($groups[$code])) {
            $groups[$code] = [];
        }
        $groups[$code][] = $r;
    }

    $heads = [];
    foreach ($groups as $code => $groupRows) {
        $pick = null;
        foreach ($groupRows as $r) {
            $rel = $hasRelationship ? strtolower(trim((string)($r['relationship_to_head'] ?? ''))) : '';
            $role = $hasHouseholdRole ? strtolower(trim((string)($r['household_role'] ?? ''))) : '';
            $isHead = (strpos($rel, 'head') !== false) || (strpos($role, 'head') !== false);
            if ($isHead) {
                $pick = $r;
                break;
            }
        }
        if (!$pick) {
            $pick = $groupRows[0];
        }

        $last = (string)($pick['last_name'] ?? '');
        $first = (string)($pick['first_name'] ?? '');
        $middle = (string)($pick['middle_name'] ?? '');
        $name = trim($last . ', ' . $first . ' ' . $middle);

        $heads[] = [
            'resident_id' => (int)($pick['id'] ?? 0),
            'name' => $name,
            'family_head_code' => $code,
            'family_code' => $hasFamilyCode ? ($pick['family_code'] ?? null) : null
        ];
    }

    usort($heads, function($a, $b) {
        return strcmp((string)($a['family_head_code'] ?? ''), (string)($b['family_head_code'] ?? ''));
    });

    sendResponse(true, 'Family heads retrieved', $heads);
}

function isLegacyHouseholdType($val) {
    global $LEGACY_HOUSEHOLD_TYPES;
    return in_array(strtolower(trim((string)$val)), $LEGACY_HOUSEHOLD_TYPES, true);
}

function isRegisterHouseholdType($val) {
    global $REGISTER_HOUSEHOLD_TYPES;
    return in_array(trim((string)$val), $REGISTER_HOUSEHOLD_TYPES, true);
}

/**
 * Map pre-registration slugs to current labels for admin/API display after legacy rows were cleared.
 */
function mapLegacyHouseholdTypeToRegister($val) {
    $slug = strtolower(trim((string)$val));
    if ($slug === '') {
        return null;
    }
    $map = [
        'nuclear' => 'Family Household',
        'extended' => 'Family Household',
        'single_parent' => 'Family Household',
        'others' => 'Other (Specify)',
    ];
    return $map[$slug] ?? null;
}

function resolveHeadIdForHouseholdTypeCompute(array $household, array $members) {
    $hid = (int)($household['family_head_id'] ?? $household['head_id'] ?? 0);
    if ($hid > 0) {
        return $hid;
    }
    foreach ($members as $m) {
        $hmHead = $m['hm_is_head'] ?? null;
        if ($hmHead === 1 || $hmHead === true || $hmHead === '1') {
            return (int)($m['id'] ?? 0);
        }
    }
    foreach ($members as $m) {
        $rel = strtolower(trim((string)($m['relationship_to_head'] ?? $m['hm_relationship_to_head'] ?? '')));
        if ($rel !== '' && strpos($rel, 'head') !== false) {
            return (int)($m['id'] ?? 0);
        }
    }
    foreach ($members as $m) {
        $fhc = trim((string)($m['family_head_code'] ?? ''));
        if ($fhc !== '' && $fhc !== '-') {
            return (int)($m['id'] ?? 0);
        }
    }
    return (int)($members[0]['id'] ?? 0);
}

/**
 * Infer display household type from residents (aligned with api/households/info.php).
 */
function computeHouseholdTypeFromResidents($headId, array $members) {
    if (empty($members)) {
        return 'Single Inhabitant';
    }
    if ($headId <= 0) {
        $headId = resolveHeadIdForHouseholdTypeCompute(['family_head_id' => 0, 'head_id' => 0], $members);
    }
    $rels = [];
    foreach ($members as $m) {
        $rid = (int)($m['id'] ?? 0);
        if ($rid <= 0 || $rid === $headId) {
            continue;
        }
        $r = strtolower(trim((string)($m['relationship_to_head'] ?? $m['hm_relationship_to_head'] ?? '')));
        if ($r !== '' && $r !== 'member' && strpos($r, 'head') === false) {
            $rels[] = $r;
        }
    }
    if (empty($rels)) {
        // Multiple people but only generic Head/Member roles (e.g. after head transfer) ⇒ still a family household.
        return count($members) > 1 ? 'Family Household' : 'Single Inhabitant';
    }
    $hasSpouse = false;
    $hasFamily = false;
    $hasNonRelative = false;
    foreach ($rels as $r) {
        if (strpos($r, 'spouse') !== false || strpos($r, 'wife') !== false || strpos($r, 'husband') !== false) {
            $hasSpouse = true;
        }
        if (strpos($r, 'son') !== false || strpos($r, 'daughter') !== false || strpos($r, 'child') !== false ||
            strpos($r, 'parent') !== false || strpos($r, 'mother') !== false || strpos($r, 'father') !== false ||
            strpos($r, 'sibling') !== false || strpos($r, 'brother') !== false || strpos($r, 'sister') !== false ||
            strpos($r, 'grandchild') !== false || strpos($r, 'grandparent') !== false ||
            strpos($r, 'nephew') !== false || strpos($r, 'niece') !== false ||
            strpos($r, 'uncle') !== false || strpos($r, 'aunt') !== false || strpos($r, 'cousin') !== false ||
            strpos($r, 'in-law') !== false) {
            $hasFamily = true;
        }
        if (strpos($r, 'boarder') !== false || strpos($r, 'helper') !== false || strpos($r, 'non-relative') !== false ||
            strpos($r, 'tenant') !== false || strpos($r, 'shared') !== false) {
            $hasNonRelative = true;
        }
    }
    if ($hasFamily || ($hasSpouse && count($rels) > 1)) {
        return 'Family Household';
    }
    if ($hasSpouse && count($rels) === 1) {
        return 'Couple Only';
    }
    if ($hasNonRelative && !$hasFamily && !$hasSpouse) {
        return 'Non-Relative Household (Shared / Boarders)';
    }
    return 'Family Household';
}

/**
 * Fill household_type for admin JSON when DB value was cleared (legacy) or never set.
 */
function resolveHouseholdTypeForAdminApi(array $household, array $members, $rawBeforeEnrich) {
    $cur = trim((string)($household['household_type'] ?? ''));
    if ($cur !== '' && isRegisterHouseholdType($cur)) {
        return $cur;
    }
    $mapped = mapLegacyHouseholdTypeToRegister($rawBeforeEnrich);
    if ($mapped !== null) {
        return $mapped;
    }
    $headId = resolveHeadIdForHouseholdTypeCompute($household, $members);
    return computeHouseholdTypeFromResidents($headId, $members);
}

/**
 * Map registration / stored house type labels or slugs to households.house_type form slug (see applications.php).
 */
function structureHouseTypeToSlug($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return '';
    }
    $map = [
        'Concrete' => 'concrete',
        'Semi-Concrete' => 'semi_concrete',
        'Light Materials' => 'light_materials',
        'Apartment / Boarding House' => 'apartment_boarding',
        'Townhouse / Row House' => 'townhouse_row',
        'Informal / Improvised' => 'informal_improvised',
    ];
    if (isset($map[$raw])) {
        return $map[$raw];
    }
    $known = ['concrete', 'semi_concrete', 'light_materials', 'apartment_boarding', 'townhouse_row', 'informal_improvised'];
    $norm = strtolower(str_replace(['-', '/', ' '], '_', $raw));
    $norm = preg_replace('/_+/', '_', $norm);
    $norm = trim($norm, '_');
    if (in_array($norm, $known, true)) {
        return $norm;
    }
    if ($norm === 'apartment_boarding_house') {
        return 'apartment_boarding';
    }
    if ($norm === 'townhouse_row_house') {
        return 'townhouse_row';
    }
    return '';
}

/**
 * Resolve structure house_type from resident_applications for a resident (legacy-friendly).
 * Tries: approved_resident_id, then name+DOB, then email. Does not require strict status if id match exists.
 */
function fetchHouseTypeFromApplicationsForResident($db, $residentId) {
    $residentId = (int)$residentId;
    if ($residentId <= 0) {
        return '';
    }
    if (!tableExists($db, 'resident_applications') || !columnExists($db, 'resident_applications', 'house_type')) {
        return '';
    }

    try {
        if (columnExists($db, 'resident_applications', 'approved_resident_id')) {
            $app = $db->fetchOne(
                'SELECT house_type FROM resident_applications WHERE approved_resident_id = ? ORDER BY id DESC LIMIT 1',
                [$residentId]
            );
            if ($app) {
                $ht = trim((string)($app['house_type'] ?? ''));
                if ($ht !== '') {
                    return $ht;
                }
            }
        }

        $sel = ['first_name', 'last_name'];
        if (columnExists($db, 'residents', 'birth_date')) {
            $sel[] = 'birth_date';
        }
        if (columnExists($db, 'residents', 'date_of_birth')) {
            $sel[] = 'date_of_birth';
        }
        if (columnExists($db, 'residents', 'email')) {
            $sel[] = 'email';
        }
        if (columnExists($db, 'residents', 'family_code')) {
            $sel[] = 'family_code';
        }
        $r = $db->fetchOne(
            'SELECT ' . implode(', ', $sel) . ' FROM residents WHERE id = ? LIMIT 1',
            [$residentId]
        );
        if (!$r) {
            return '';
        }
        $fn = trim((string)($r['first_name'] ?? ''));
        $ln = trim((string)($r['last_name'] ?? ''));
        $dob = $r['birth_date'] ?? $r['date_of_birth'] ?? null;
        if ($fn !== '' && $ln !== '' && $dob) {
            $dobStr = is_string($dob) ? substr($dob, 0, 10) : $dob;
            $app = $db->fetchOne(
                'SELECT house_type FROM resident_applications
                 WHERE LOWER(TRIM(first_name)) = LOWER(?) AND LOWER(TRIM(last_name)) = LOWER(?)
                   AND DATE(birth_date) = DATE(?)
                 ORDER BY id DESC LIMIT 1',
                [$fn, $ln, $dobStr]
            );
            if ($app) {
                $ht = trim((string)($app['house_type'] ?? ''));
                if ($ht !== '') {
                    return $ht;
                }
            }
        }

        if (columnExists($db, 'resident_applications', 'email')) {
            $email = trim((string)($r['email'] ?? ''));
            if ($email !== '') {
                $app = $db->fetchOne(
                    'SELECT house_type FROM resident_applications WHERE LOWER(TRIM(email)) = LOWER(?) ORDER BY id DESC LIMIT 1',
                    [$email]
                );
                if ($app) {
                    $ht = trim((string)($app['house_type'] ?? ''));
                    if ($ht !== '') {
                        return $ht;
                    }
                }
            }
        }

        if (columnExists($db, 'resident_applications', 'family_code')) {
            $fc = trim((string)($r['family_code'] ?? ''));
            if ($fc !== '' && $fc !== '-') {
                $app = $db->fetchOne(
                    'SELECT house_type FROM resident_applications WHERE TRIM(family_code) = ? AND house_type IS NOT NULL AND TRIM(house_type) <> \'\' ORDER BY id DESC LIMIT 1',
                    [$fc]
                );
                if ($app) {
                    $ht = trim((string)($app['house_type'] ?? ''));
                    if ($ht !== '') {
                        return $ht;
                    }
                }
            }
        }

    } catch (Exception $e) {
        error_log('fetchHouseTypeFromApplicationsForResident: ' . $e->getMessage());
    }

    return '';
}

/**
 * When households.house_type is empty, fill from applications (head first, then other members).
 * Works even if households.house_type column is missing (virtual field for API JSON).
 */
function enrichStructureHouseTypeFromHeadApplication($db, array $household, array $members = []) {
    $cur = trim((string)($household['house_type'] ?? ''));
    if ($cur !== '') {
        return $household;
    }

    $headId = (int)($household['family_head_id'] ?? $household['head_id'] ?? 0);
    $candidateIds = [];
    if ($headId > 0) {
        $candidateIds[] = $headId;
    }
    foreach ($members as $m) {
        $rid = (int)($m['id'] ?? 0);
        if ($rid > 0 && !in_array($rid, $candidateIds, true)) {
            $candidateIds[] = $rid;
        }
    }

    $rawHt = '';
    foreach ($candidateIds as $rid) {
        $rawHt = fetchHouseTypeFromApplicationsForResident($db, $rid);
        if ($rawHt !== '') {
            break;
        }
    }

    if ($rawHt === '') {
        return $household;
    }

    $slug = structureHouseTypeToSlug($rawHt);
    $household['house_type'] = $slug !== '' ? $slug : $rawHt;
    return $household;
}

/**
 * Enrich household_type from the head's registration (resident_applications) when available.
 * Legacy values (nuclear, extended, etc.) are cleared to null.
 */
function enrichHouseholdTypeFromHeadApplication($db, $household) {
    $headId = (int)($household['family_head_id'] ?? $household['head_id'] ?? 0);
    $current = trim((string)($household['household_type'] ?? ''));
    $hid = (int)($household['id'] ?? 0);
    $hasCol = $hid > 0 && columnExists($db, 'households', 'household_type');

    if (isLegacyHouseholdType($current)) {
        $household['household_type'] = null;
        if ($hasCol) {
            try {
                $db->query("UPDATE households SET household_type = NULL WHERE id = ?", [$hid]);
            } catch (Exception $e) {
                error_log("enrichHouseholdType clear legacy: " . $e->getMessage());
            }
        }
        return $household;
    }
    if (isRegisterHouseholdType($current)) {
        return $household;
    }
    if ($headId <= 0) {
        return $household;
    }
    if (!tableExists($db, 'resident_applications')) {
        return $household;
    }
    $statusCol = columnExists($db, 'resident_applications', 'record_status') ? 'record_status' : 'status';
    $approvedCol = columnExists($db, 'resident_applications', 'approved_resident_id') ? 'approved_resident_id' : null;
    if (!$approvedCol) {
        return $household;
    }
    $app = $db->fetchOne(
        "SELECT household_type, household_role FROM resident_applications WHERE $approvedCol = ? AND (`$statusCol` = 'approved' OR `$statusCol` = 'Approved') LIMIT 1",
        [$headId]
    );
    if ($app) {
        $appType = trim((string)($app['household_type'] ?? ''));
        if ($appType !== '' && isRegisterHouseholdType($appType)) {
            $household['household_type'] = $appType;
        }
        if ($household['household_type'] !== null && $hasCol) {
            try {
                $db->query("UPDATE households SET household_type = ? WHERE id = ?", [$household['household_type'], $hid]);
            } catch (Exception $e) {
                error_log("enrichHouseholdType from app: " . $e->getMessage());
            }
        }
    }
    return $household;
}

function generateUniqueHouseholdCode($db) {
    // HH-XXXXXX (6 digits)
    for ($i = 0; $i < 20; $i++) {
        $code = 'HH-' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM households WHERE household_id_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique household code.');
}

function generateUniqueFamilyHeadCode($db) {
    // HC-XXXXX (5 digits)
    for ($i = 0; $i < 20; $i++) {
        $code = 'HC-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM households WHERE family_head_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique family head code.');
}

function generateResidentFamilyHeadCode($db) {
    $prefix = 'HC-';
    if (!columnExists($db, 'residents', 'family_head_code')) {
        return $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i < 30; $i++) {
        $code = $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM residents WHERE family_head_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique resident family head code.');
}

function generateUniqueFamilyCode($db) {
    // BR219-YYYY-XXXX (4 digits)
    $year = date('Y');
    for ($i = 0; $i < 30; $i++) {
        $suffix = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $code = 'BR219-' . $year . '-' . $suffix;
        $exists = $db->fetchOne("SELECT id FROM households WHERE family_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique family code.');
}

function columnExists($db, $table, $column) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = ?
           AND column_name = ?",
        [$table, $column]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function tableExists($db, $table) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?",
        [$table]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function isMissingCode($value) {
    // Treat common placeholder values as "missing" so codes get generated.
    $v = trim((string)$value);
    return $v === '' || $v === '-' || strtolower($v) === 'null' || strtolower($v) === 'n/a';
}

/**
 * Ensure household codes and basic details exist once a head is assigned.
 * This can be called from multiple flows (updateHousehold, addHouseholdMember, etc.)
 */
function ensureHouseholdCodesAndDetails($db, $householdId) {
    $householdId = (int)$householdId;
    if ($householdId <= 0) {
        return;
    }

    $existing = $db->fetchOne("SELECT * FROM households WHERE id = ?", [$householdId]);
    if (!$existing) {
        return;
    }

    $family_head_id = (int)($existing['family_head_id'] ?? 0);
    $hasHead = $family_head_id > 0;

    $hasHouseholdIdCode = columnExists($db, 'households', 'household_id_code');
    $hasFamilyHeadCode = columnExists($db, 'households', 'family_head_code');
    $hasFamilyCode = columnExists($db, 'households', 'family_code');

    // Generate official approval codes when a head is assigned (first time).
    $needsHouseholdCode = $hasHouseholdIdCode && isMissingCode($existing['household_id_code'] ?? null);
    // Only generate per-head codes when a head is actually assigned.
    $needsHeadCode = $hasHead && $hasFamilyHeadCode && isMissingCode($existing['family_head_code'] ?? null);

    if ($needsHouseholdCode || $needsHeadCode) {
        $sets = [];
        $params = [];

        if ($needsHouseholdCode) {
            $sets[] = 'household_id_code = ?';
            $params[] = generateUniqueHouseholdCode($db);
        }
        if ($needsHeadCode) {
            $sets[] = 'family_head_code = ?';
            $params[] = generateUniqueFamilyHeadCode($db);
        }

        if (!empty($sets)) {
            $params[] = $householdId;
            $db->query("UPDATE households SET " . implode(', ', $sets) . " WHERE id = ?", $params);
        }
    }

    // Ensure legacy family_code exists for resident-side display/join (generate once).
    if ($hasFamilyCode && empty($existing['family_code'])) {
        $db->query(
            "UPDATE households SET family_code = COALESCE(family_code, ?) WHERE id = ?",
            [generateUniqueFamilyCode($db), $householdId]
        );
    }

    // Auto-fill household details from head when missing (so tiles show info right away).
    $current = $db->fetchOne("SELECT address, registration_date FROM households WHERE id = ?", [$householdId]);
    if ($current) {
        if (!$hasHead) {
            // No head assigned yet; don't try to pull address from a head resident.
            return;
        }
        $needsAddress = empty(trim((string)($current['address'] ?? '')));
        $needsRegDate = empty($current['registration_date']);
        if ($needsAddress || $needsRegDate) {
            $headRow = $db->fetchOne(
                "SELECT address, house_number, street, purok_sitio FROM residents WHERE id = ? LIMIT 1",
                [$family_head_id]
            );
            $headAddress = '';
            if ($headRow) {
                $parts = array_filter([
                    $headRow['house_number'] ?? null,
                    $headRow['street'] ?? null,
                    $headRow['purok_sitio'] ?? null
                ], function($v) { return trim((string)$v) !== ''; });
                $headAddress = trim((string)($headRow['address'] ?? ''));
                if ($headAddress === '' && !empty($parts)) {
                    $headAddress = implode(', ', $parts);
                }
            }

            $db->query(
                "UPDATE households
                 SET address = CASE WHEN (address IS NULL OR address = '') THEN ? ELSE address END,
                     registration_date = CASE WHEN registration_date IS NULL OR registration_date = '' THEN ? ELSE registration_date END
                 WHERE id = ?",
                [
                    $needsAddress ? ($headAddress !== '' ? $headAddress : null) : ($current['address'] ?? null),
                    $needsRegDate ? date('Y-m-d') : ($current['registration_date'] ?? null),
                    $householdId
                ]
            );
        }
    }
}

/**
 * List all households
 */
/**
 * Canonical street names (no households required). File returns a list of strings.
 */
function getOfficialBarangay219Streets() {
    $path = __DIR__ . '/../config/barangay219_streets.php';
    if (!is_readable($path)) {
        return [];
    }
    $list = require $path;
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $item) {
        if (!is_string($item)) {
            continue;
        }
        $t = trim($item);
        if ($t !== '') {
            $out[] = $t;
        }
    }
    return $out;
}

function normalizeStreetLookupKey($street) {
    return strtolower(trim((string)$street));
}

/**
 * Aggregated street tiles: official registry (incl. zero households) + DB-only streets.
 * Search filter `q` is applied after merge so empty streets still match by name.
 */
function listStreetsSummary() {
    try {
        $db = Database::getInstance();
        if (!columnExists($db, 'households', 'street')) {
            sendResponse(true, 'No street column', []);
            return;
        }

        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');

        $where = '1=1';
        $params = [];

        if (!empty($from)) {
            $where .= " AND DATE(h.registration_date) >= ?";
            $params[] = $from;
        }
        if (!empty($to)) {
            $where .= " AND DATE(h.registration_date) <= ?";
            $params[] = $to;
        }

        $sql = "SELECT
                    COALESCE(NULLIF(TRIM(h.street), ''), '') AS street_key,
                    COUNT(*) AS household_count,
                    MAX(NULLIF(TRIM(COALESCE(h.barangay, '')), '')) AS barangay_sample
                FROM households h
                WHERE $where
                GROUP BY COALESCE(NULLIF(TRIM(h.street), ''), '')";

        $rows = $db->fetchAll($sql, $params);

        $agg = [];
        foreach ($rows as $row) {
            $key = (string)($row['street_key'] ?? '');
            $norm = normalizeStreetLookupKey($key);
            $cnt = (int)($row['household_count'] ?? 0);
            $bg = trim((string)($row['barangay_sample'] ?? ''));
            if (!isset($agg[$norm])) {
                $agg[$norm] = [
                    'count' => 0,
                    'barangay' => '',
                    'display_key' => $key,
                ];
            }
            $agg[$norm]['count'] += $cnt;
            if ($bg !== '' && $agg[$norm]['barangay'] === '') {
                $agg[$norm]['barangay'] = $bg;
            }
            if ($key !== '' && ($agg[$norm]['display_key'] === '' || $agg[$norm]['display_key'] === $key)) {
                $agg[$norm]['display_key'] = $key;
            }
        }

        $defaultBarangay = 'Barangay 219';
        $out = [];
        $usedNorm = [];

        $official = getOfficialBarangay219Streets();
        $seenOfficial = [];
        foreach ($official as $name) {
            $norm = normalizeStreetLookupKey($name);
            if (isset($seenOfficial[$norm])) {
                continue;
            }
            $seenOfficial[$norm] = true;
            $usedNorm[$norm] = true;
            $cell = $agg[$norm] ?? null;
            $count = $cell ? (int)$cell['count'] : 0;
            $bg = ($cell && $cell['barangay'] !== '') ? $cell['barangay'] : $defaultBarangay;
            $out[] = [
                'street_key' => $name,
                'street_label' => $name,
                'filter_token' => $name,
                'household_count' => $count,
                'barangay' => $bg,
                'from_registry' => true,
            ];
        }

        $emptyNorm = normalizeStreetLookupKey('');
        if (isset($agg[$emptyNorm]) && $agg[$emptyNorm]['count'] > 0) {
            $usedNorm[$emptyNorm] = true;
            $cell = $agg[$emptyNorm];
            $out[] = [
                'street_key' => '',
                'street_label' => '(No street on file)',
                'filter_token' => '__EMPTY__',
                'household_count' => (int)$cell['count'],
                'barangay' => ($cell['barangay'] !== '') ? $cell['barangay'] : $defaultBarangay,
                'from_registry' => false,
            ];
        }

        $extras = [];
        foreach ($agg as $norm => $cell) {
            if (isset($usedNorm[$norm])) {
                continue;
            }
            $key = (string)($cell['display_key'] ?? '');
            $label = $key !== '' ? $key : '(No street on file)';
            $extras[] = [
                'street_key' => $key,
                'street_label' => $label,
                'filter_token' => $key !== '' ? $key : '__EMPTY__',
                'household_count' => (int)$cell['count'],
                'barangay' => ($cell['barangay'] !== '') ? $cell['barangay'] : $defaultBarangay,
                'from_registry' => false,
            ];
        }
        usort($extras, function ($a, $b) {
            return strcasecmp((string)($a['street_label'] ?? ''), (string)($b['street_label'] ?? ''));
        });
        $out = array_merge($out, $extras);

        if ($q !== '') {
            $needle = strtolower($q);
            $out = array_values(array_filter($out, function ($row) use ($needle, $q) {
                $label = strtolower((string)($row['street_label'] ?? ''));
                $key = strtolower((string)($row['street_key'] ?? ''));
                return (strpos($label, $needle) !== false) || (strpos($key, $needle) !== false)
                    || stripos((string)($row['barangay'] ?? ''), $q) !== false;
            }));
        }

        sendResponse(true, 'Streets summary', $out);
    } catch (Exception $e) {
        error_log('List streets summary error: ' . $e->getMessage());
        sendResponse(false, 'Error retrieving streets', null, 500);
    }
}

function listHouseholds() {
    try {
        $db = Database::getInstance();
        // Ensure household_type column exists so registration input can be persisted
        if (!columnExists($db, 'households', 'household_type')) {
            try {
                $db->query("ALTER TABLE households ADD COLUMN household_type VARCHAR(80) NULL DEFAULT NULL");
            } catch (Exception $e) {
                error_log("Ensure household_type column: " . $e->getMessage());
            }
        }
        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');
        $streetToken = isset($_GET['street']) ? trim((string)$_GET['street']) : '';
        $memberRange = strtolower(trim((string)($_GET['member_range'] ?? '')));
        $withSenior = filter_var($_GET['with_senior'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        $withMinors = filter_var($_GET['with_minors'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        $singleOccupant = filter_var($_GET['single_occupant'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        $houseTypeFilter = strtolower(trim(sanitizeInput($_GET['house_type'] ?? '')));
        $indigentStatusFilter = strtolower(trim(sanitizeInput($_GET['indigent_status'] ?? '')));
        $sortBy = strtolower(trim(sanitizeInput($_GET['sort_by'] ?? 'newest')));
        
        // New filter parameters
        $withRegisteredVoters = filter_var($_GET['with_registered_voters'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        $allMembersVerified = filter_var($_GET['all_members_verified'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        $withMissingInfo = filter_var($_GET['with_missing_info'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
        $verificationStatus = strtolower(trim(sanitizeInput($_GET['verification_status'] ?? '')));
        $residencyFrom = floatval($_GET['residency_from'] ?? 0);
        $residencyTo = floatval($_GET['residency_to'] ?? 0);

        $where = '1=1';
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $parts = ['h.address LIKE ?', "CONCAT(r.first_name, ' ', r.last_name) LIKE ?"];
            $params = [$term, $term];
            if (columnExists($db, 'households', 'household_id_code')) {
                $parts[] = 'h.household_id_code LIKE ?';
                $params[] = $term;
            }
            if (columnExists($db, 'households', 'street')) {
                $parts[] = "TRIM(COALESCE(h.street, '')) LIKE ?";
                $params[] = $term;
            }
            $where = '(' . implode(' OR ', $parts) . ')';
        }
        if (!empty($from)) {
            $where .= " AND DATE(h.registration_date) >= ?";
            $params[] = $from;
        }
        if (!empty($to)) {
            $where .= " AND DATE(h.registration_date) <= ?";
            $params[] = $to;
        }
        if ($streetToken !== '' && columnExists($db, 'households', 'street')) {
            if ($streetToken === '__EMPTY__') {
                $where .= " AND (h.street IS NULL OR TRIM(h.street) = '')";
            } else {
                $where .= " AND TRIM(COALESCE(h.street, '')) = ?";
                $params[] = $streetToken;
            }
        }

        if ($singleOccupant) {
            $where .= " AND (SELECT COUNT(*) FROM residents r_cnt WHERE r_cnt.household_id = h.id) = 1";
        }

        if ($memberRange !== '') {
            if ($memberRange === '1') {
                $where .= " AND (SELECT COUNT(*) FROM residents r_cnt WHERE r_cnt.household_id = h.id) = 1";
            } elseif ($memberRange === '2-4') {
                $where .= " AND (SELECT COUNT(*) FROM residents r_cnt WHERE r_cnt.household_id = h.id) BETWEEN 2 AND 4";
            } elseif ($memberRange === '5-7') {
                $where .= " AND (SELECT COUNT(*) FROM residents r_cnt WHERE r_cnt.household_id = h.id) BETWEEN 5 AND 7";
            } elseif ($memberRange === '8+') {
                $where .= " AND (SELECT COUNT(*) FROM residents r_cnt WHERE r_cnt.household_id = h.id) >= 8";
            }
        }

        $birthCol = columnExists($db, 'residents', 'birth_date') ? 'birth_date'
            : (columnExists($db, 'residents', 'date_of_birth') ? 'date_of_birth' : null);

        if ($withSenior && $birthCol !== null) {
            $where .= " AND EXISTS (SELECT 1 FROM residents r_age WHERE r_age.household_id = h.id AND r_age.`$birthCol` IS NOT NULL AND TIMESTAMPDIFF(YEAR, r_age.`$birthCol`, CURDATE()) >= 60)";
        }
        if ($withMinors && $birthCol !== null) {
            $where .= " AND EXISTS (SELECT 1 FROM residents r_age WHERE r_age.household_id = h.id AND r_age.`$birthCol` IS NOT NULL AND TIMESTAMPDIFF(YEAR, r_age.`$birthCol`, CURDATE()) < 18)";
        }

        if ($houseTypeFilter !== '' && columnExists($db, 'households', 'house_type')) {
            $houseTypeAliases = [
                'concrete' => ['concrete'],
                'semi_concrete' => ['semi_concrete', 'semi-concrete'],
                'light_materials' => ['light_materials', 'light-materials'],
                'apartment_boarding' => ['apartment_boarding', 'apartment_boarding_house'],
                'townhouse_row' => ['townhouse_row', 'townhouse_row_house'],
                'informal_improvised' => ['informal_improvised']
            ];

            $normalizedHouseType = strtolower(trim(preg_replace('/_+/', '_', str_replace(['-', '/', ' '], '_', $houseTypeFilter))));
            if ($normalizedHouseType !== '') {
                $accepted = $houseTypeAliases[$normalizedHouseType] ?? [$normalizedHouseType];
                $placeholders = implode(',', array_fill(0, count($accepted), '?'));
                $where .= " AND LOWER(TRIM(REPLACE(REPLACE(REPLACE(COALESCE(h.house_type, ''), '-', '_'), '/', '_'), ' ', '_'))) IN ($placeholders)";
                foreach ($accepted as $a) {
                    $params[] = strtolower(trim(preg_replace('/_+/', '_', str_replace(['-', '/', ' '], '_', (string)$a))));
                }
            }
        }

        if (($indigentStatusFilter === 'indigent' || $indigentStatusFilter === 'non_indigent') && columnExists($db, 'residents', 'monthly_income')) {
            ensureIndigentClassificationSchema($db);
            $thresholdMonthly = getIndigentThresholdMonthly($db);
            if ($indigentStatusFilter === 'indigent') {
                $where .= " AND COALESCE((SELECT SUM(CASE WHEN r_inc.monthly_income IS NULL OR r_inc.monthly_income < 0 THEN 0 ELSE r_inc.monthly_income END) FROM residents r_inc WHERE r_inc.household_id = h.id), 0) <= ?";
                $params[] = $thresholdMonthly;
            } else {
                $where .= " AND COALESCE((SELECT SUM(CASE WHEN r_inc.monthly_income IS NULL OR r_inc.monthly_income < 0 THEN 0 ELSE r_inc.monthly_income END) FROM residents r_inc WHERE r_inc.household_id = h.id), 0) > ?";
                $params[] = $thresholdMonthly;
            }
        }

        // Filter by years of residency
        if ($residencyFrom > 0 || $residencyTo > 0) {
            if ($residencyFrom > 0 && $residencyTo > 0) {
                // Between range: DATEDIFF(CURDATE(), registration_date) / 365.25
                $where .= " AND (DATEDIFF(CURDATE(), h.registration_date) / 365.25) BETWEEN ? AND ?";
                $params[] = $residencyFrom;
                $params[] = $residencyTo;
            } elseif ($residencyFrom > 0) {
                // At least residencyFrom years
                $where .= " AND (DATEDIFF(CURDATE(), h.registration_date) / 365.25) >= ?";
                $params[] = $residencyFrom;
            } elseif ($residencyTo > 0) {
                // At most residencyTo years
                $where .= " AND (DATEDIFF(CURDATE(), h.registration_date) / 365.25) <= ?";
                $params[] = $residencyTo;
            }
        }

        // Filter by verification status
        if ($verificationStatus !== '' && $verificationStatus !== 'all' && columnExists($db, 'residents', 'verification_status')) {
            if ($verificationStatus === 'verified') {
                $where .= " AND (SELECT COUNT(*) FROM residents r_ver WHERE r_ver.household_id = h.id AND LOWER(TRIM(COALESCE(r_ver.verification_status, ''))) = 'verified') > 0";
            } elseif ($verificationStatus === 'pending') {
                $where .= " AND (SELECT COUNT(*) FROM residents r_ver WHERE r_ver.household_id = h.id AND LOWER(TRIM(COALESCE(r_ver.verification_status, ''))) = 'pending') > 0";
            } elseif ($verificationStatus === 'needs_review') {
                $where .= " AND (SELECT COUNT(*) FROM residents r_ver WHERE r_ver.household_id = h.id AND LOWER(TRIM(COALESCE(r_ver.verification_status, ''))) = 'needs_review') > 0";
            }
        }

        // Filter households with at least one registered voter
        if ($withRegisteredVoters && columnExists($db, 'residents', 'voter_status')) {
            $where .= " AND EXISTS (SELECT 1 FROM residents r_voter WHERE r_voter.household_id = h.id AND r_voter.voter_status IS NOT NULL AND LOWER(TRIM(r_voter.voter_status)) LIKE '%registered%')";
        }

        // Filter households where all members are verified
        if ($allMembersVerified && columnExists($db, 'residents', 'verification_status')) {
            $where .= " AND NOT EXISTS (SELECT 1 FROM residents r_unver WHERE r_unver.household_id = h.id AND LOWER(TRIM(COALESCE(r_unver.verification_status, ''))) <> 'verified')";
        }

        // Filter households with missing information
        if ($withMissingInfo && columnExists($db, 'residents', 'first_name')) {
            $missingCols = [];
            $missingCheck = '';
            
            // Check for commonly missing fields
            if (columnExists($db, 'residents', 'birth_date') || columnExists($db, 'residents', 'date_of_birth')) {
                $birthCol = columnExists($db, 'residents', 'birth_date') ? 'birth_date' : 'date_of_birth';
                $missingCheck .= "($birthCol IS NULL OR TRIM($birthCol) = '') OR ";
            }
            if (columnExists($db, 'residents', 'contact_number')) {
                $missingCheck .= "(contact_number IS NULL OR TRIM(contact_number) = '') OR ";
            }
            if (columnExists($db, 'residents', 'address')) {
                $missingCheck .= "(address IS NULL OR TRIM(address) = '') OR ";
            }
            if (columnExists($db, 'residents', 'monthly_income')) {
                $missingCheck .= "(monthly_income IS NULL) OR ";
            }
            if (columnExists($db, 'residents', 'monthly_gross_income')) {
                $missingCheck .= "(monthly_gross_income IS NULL) OR ";
            }
            if (columnExists($db, 'residents', 'verification_status')) {
                $missingCheck .= "(verification_status IS NULL OR TRIM(verification_status) = '') OR ";
            }
            
            // Remove trailing " OR "
            $missingCheck = rtrim($missingCheck, ' OR ');
            
            if ($missingCheck !== '') {
                $where .= " AND EXISTS (SELECT 1 FROM residents r_miss WHERE r_miss.household_id = h.id AND ($missingCheck))";
            }
        }
        $orderBySql = 'h.registration_date DESC, h.id DESC';
        if ($sortBy === 'oldest') {
            $orderBySql = 'h.registration_date ASC, h.id ASC';
        } elseif ($sortBy === 'members_desc') {
            $orderBySql = '_live_member_count DESC, h.registration_date DESC, h.id DESC';
        } elseif ($sortBy === 'members_asc') {
            $orderBySql = '_live_member_count ASC, h.registration_date DESC, h.id DESC';
        }

        $sql = "SELECT h.*, 
                       (SELECT COUNT(*) FROM residents r_cnt WHERE r_cnt.household_id = h.id) AS _live_member_count,
                       CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) as family_head_name,
                       r.contact_number as family_head_contact
                FROM households h
                LEFT JOIN residents r ON h.family_head_id = r.id
                WHERE $where
                ORDER BY $orderBySql";
        
        $households = $db->fetchAll($sql, $params);

        foreach ($households as &$h) {
            if (array_key_exists('_live_member_count', $h)) {
                $h['total_members'] = (int)$h['_live_member_count'];
                unset($h['_live_member_count']);
            }
        }
        unset($h);

        // If some existing households were created before codes were generated,
        // generate household_id_code on-the-fly so the tiles don't show "-".
        if (columnExists($db, 'households', 'household_id_code')) {
            $needsCode = [];
            foreach ($households as $h) {
                if (isMissingCode($h['household_id_code'] ?? null)) {
                    $needsCode[] = (int)($h['id'] ?? 0);
                }
            }

            foreach ($needsCode as $householdId) {
                if ($householdId <= 0) {
                    continue;
                }
                ensureHouseholdCodesAndDetails($db, $householdId);

                // Update only the code field in the in-memory list.
                $codeRow = $db->fetchOne("SELECT household_id_code FROM households WHERE id = ?", [$householdId]);
                if ($codeRow) {
                    foreach ($households as &$hh) {
                        if ((int)($hh['id'] ?? 0) === $householdId) {
                            $hh['household_id_code'] = $codeRow['household_id_code'] ?? $hh['household_id_code'];
                        }
                    }
                }
            }
            unset($hh);
        }

        foreach ($households as &$h) {
            $h['family_head_names'] = $h['family_head_name'] ?? '';
        }
        unset($h);
        try {
            $hasRel = columnExists($db, 'residents', 'relationship_to_head');
            $hasFhc = columnExists($db, 'residents', 'family_head_code');
            $resSelect = "id, first_name, middle_name, last_name";
            if ($hasRel) $resSelect .= ", relationship_to_head";
            if ($hasFhc) $resSelect .= ", family_head_code";
            foreach ($households as &$h) {
                $hid = (int)($h['id'] ?? 0);
                $designatedId = (int)($h['family_head_id'] ?? 0);
                $headNames = [];
                $residents = $db->fetchAll("SELECT $resSelect FROM residents WHERE household_id = ?", [$hid]);
                foreach ($residents as $r) {
                    $rid = (int)($r['id'] ?? 0);
                    $isHead = ($rid === $designatedId);
                    if (!$isHead && $hasRel) {
                        $rel = strtolower(trim((string)($r['relationship_to_head'] ?? '')));
                        $isHead = $isHead || (strpos($rel, 'head') !== false);
                    }
                    if (!$isHead && $hasFhc) {
                        $fhc = trim((string)($r['family_head_code'] ?? ''));
                        $isHead = $isHead || ($fhc !== '' && $fhc !== '-');
                    }
                    if ($isHead) {
                        $name = trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                        if ($name !== '') $headNames[] = $name;
                    }
                }
                $h['family_head_names'] = !empty($headNames) ? implode(', ', $headNames) : ($h['family_head_name'] ?? '');
            }
            unset($h);
        } catch (Exception $headEx) {
            error_log("List households family_head_names enrichment: " . $headEx->getMessage());
        }

        foreach ($households as &$h) {
            $rawTypeList = trim((string)($h['household_type'] ?? ''));
            try {
                $h = enrichHouseholdTypeFromHeadApplication($db, $h);
            } catch (Throwable $ex) {
                error_log("enrichHouseholdType list: " . $ex->getMessage());
                $cur = trim((string)($h['household_type'] ?? ''));
                if (in_array(strtolower($cur), ['nuclear', 'extended', 'single_parent', 'others'], true)) {
                    $h['household_type'] = null;
                }
            }
            $curList = trim((string)($h['household_type'] ?? ''));
            if ($curList === '' && $rawTypeList !== '') {
                $mappedList = mapLegacyHouseholdTypeToRegister($rawTypeList);
                if ($mappedList !== null) {
                    $h['household_type'] = $mappedList;
                }
            }
        }
        unset($h);
        
        sendResponse(true, 'Households retrieved successfully', $households);
        
    } catch (Exception $e) {
        error_log("List households error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving households', null, 500);
    }
}

/**
 * Get single household with members
 */
function getHousehold() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        ensureIndigentClassificationSchema($db);

        // Ensure residents table can store special category proof path
        if (function_exists('addColumnIfMissing')) {
            addColumnIfMissing($db, 'residents', 'special_category_proof_path', "VARCHAR(255) NULL AFTER proof_of_residency_path");
        }

        $sql = "SELECT h.*, 
                       CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) as family_head_name
                FROM households h
                LEFT JOIN residents r ON h.family_head_id = r.id
                WHERE h.id = ?";
        
        $household = $db->fetchOne($sql, [$id]);
        
        if (!$household) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }
        
        // Auto-generate missing household codes when viewing.
        // This prevents '-/-' when migrations were added after household creation.
        ensureHouseholdCodesAndDetails($db, $id);
        // Reload so UI gets updated code values.
        $household = $db->fetchOne($sql, [$id]);
        $rawHouseholdTypeForDisplay = trim((string)($household['household_type'] ?? ''));
        try {
            $household = enrichHouseholdTypeFromHeadApplication($db, $household);
        } catch (Throwable $ex) {
            error_log("enrichHouseholdType get: " . $ex->getMessage());
            $cur = trim((string)($household['household_type'] ?? ''));
            if (in_array(strtolower($cur), ['nuclear', 'extended', 'single_parent', 'others'], true)) {
                $household['household_type'] = null;
            }
        }

        // Get household members (residents table is also used for remove-member actions).
        $orderCol = columnExists($db, 'residents', 'birth_date') ? 'birth_date'
            : (columnExists($db, 'residents', 'date_of_birth') ? 'date_of_birth' : 'id');
        $membersSql = "SELECT * FROM residents WHERE household_id = ? ORDER BY " . $orderCol;
        $members = $db->fetchAll($membersSql, [$id]);

        // If `household_members` exists, enrich each resident with head/member flags.
        // Note: `household_members` in this project may NOT store `resident_id`, so we match
        // using name + birth date (+ optionally contact) to find the corresponding row.
        // Wrapped in try to avoid failing when household_members schema differs.
        try {
        if (tableExists($db, 'household_members') && !empty($members)) {
            $hmMembersSql = "SELECT id, first_name, middle_name, last_name, suffix,
                                     relationship_to_head, date_of_birth, is_head, contact_number
                              FROM household_members
                              WHERE household_id = ?";
            $hmMembers = $db->fetchAll($hmMembersSql, [$id]);

            $normalizeStr = function($v) {
                return strtolower(trim((string)$v));
            };
            $normalizeDate = function($v) {
                $s = trim((string)$v);
                return $s;
            };
            $digits = function($v) {
                return preg_replace('/\\D+/', '', (string)$v);
            };

            // Build lookup maps.
            $mapPrimary = [];   // first|middle|last|dob
            $mapSecondary = []; // first|last|dob
            $mapContactDob = []; // contact|dob

            foreach ($hmMembers as $hm) {
                $dob = $normalizeDate($hm['date_of_birth'] ?? '');
                $first = $normalizeStr($hm['first_name'] ?? '');
                $middle = $normalizeStr($hm['middle_name'] ?? '');
                $last = $normalizeStr($hm['last_name'] ?? '');
                $suffix = $normalizeStr($hm['suffix'] ?? '');
                $rel = (string)($hm['relationship_to_head'] ?? '');
                $isHeadVal = $hm['is_head'] ?? 0;
                $contact = $digits($hm['contact_number'] ?? '');

                if ($first === '' || $last === '' || $dob === '') continue;

                $keyPrimary = $first . '|' . ($middle ?: '') . '|' . $last . '|' . $dob . '|' . ($suffix ?: '');
                $keySecondary = $first . '||' . $last . '|' . $dob . '|' . ($suffix ?: '');

                // store the last match; usually unique enough
                $mapPrimary[$keyPrimary] = ['is_head' => $isHeadVal, 'relationship_to_head' => $rel];
                $mapSecondary[$keySecondary] = ['is_head' => $isHeadVal, 'relationship_to_head' => $rel];

                if ($contact !== '') {
                    $keyContact = $contact . '|' . $dob;
                    $mapContactDob[$keyContact] = ['is_head' => $isHeadVal, 'relationship_to_head' => $rel];
                }
            }

            // Enrich residents (birth_date or date_of_birth depending on schema).
            foreach ($members as &$m) {
                $dob = $normalizeDate($m['birth_date'] ?? $m['date_of_birth'] ?? '');
                $first = $normalizeStr($m['first_name'] ?? '');
                $middle = $normalizeStr($m['middle_name'] ?? '');
                $last = $normalizeStr($m['last_name'] ?? '');
                $suffix = $normalizeStr($m['suffix'] ?? '');
                $rel = null;
                $isHeadVal = null;

                if ($first !== '' && $last !== '' && $dob !== '') {
                    $keyPrimary = $first . '|' . ($middle ?: '') . '|' . $last . '|' . $dob . '|' . ($suffix ?: '');
                    $keySecondary = $first . '||' . $last . '|' . $dob . '|' . ($suffix ?: '');

                    if (isset($mapPrimary[$keyPrimary])) {
                        $rel = $mapPrimary[$keyPrimary]['relationship_to_head'] ?? null;
                        $isHeadVal = $mapPrimary[$keyPrimary]['is_head'] ?? null;
                    } elseif (isset($mapSecondary[$keySecondary])) {
                        $rel = $mapSecondary[$keySecondary]['relationship_to_head'] ?? null;
                        $isHeadVal = $mapSecondary[$keySecondary]['is_head'] ?? null;
                    }
                }

                if ($isHeadVal === null) {
                    // Try contact+dob matching as a fallback.
                    $contact = $digits($m['mobile_number'] ?? ($m['contact_number'] ?? ''));
                    if ($contact !== '' && $dob !== '') {
                        $keyContact = $contact . '|' . $dob;
                        if (isset($mapContactDob[$keyContact])) {
                            $rel = $mapContactDob[$keyContact]['relationship_to_head'] ?? null;
                            $isHeadVal = $mapContactDob[$keyContact]['is_head'] ?? null;
                        }
                    }
                }

                if ($isHeadVal !== null) {
                    $m['hm_is_head'] = $isHeadVal;
                    $m['hm_relationship_to_head'] = $rel;
                    if (empty(trim((string)($m['relationship_to_head'] ?? ''))) && $rel !== null && $rel !== '') {
                        $m['relationship_to_head'] = $rel;
                    }
                }
            }
            unset($m);
        }
        } catch (Exception $hmEx) {
            error_log("Household members enrichment skipped: " . $hmEx->getMessage());
        }
        // Enrich head members with their household_type from resident_applications
        // so the UI can display per-head household type when switching tabs.
        try {
            if (tableExists($db, 'resident_applications') && columnExists($db, 'resident_applications', 'household_type')) {
                $approvedCol = columnExists($db, 'resident_applications', 'approved_resident_id') ? 'approved_resident_id' : null;
                $statusCol = columnExists($db, 'resident_applications', 'record_status') ? 'record_status' : 'status';
                if ($approvedCol) {
                    foreach ($members as &$m) {
                        $rid = (int)($m['id'] ?? 0);
                        if ($rid <= 0) continue;
                        $app = $db->fetchOne(
                            "SELECT household_type FROM resident_applications WHERE `{$approvedCol}` = ? AND LOWER(`{$statusCol}`) = 'approved' ORDER BY id DESC LIMIT 1",
                            [$rid]
                        );
                        if ($app) {
                            $appType = trim((string)($app['household_type'] ?? ''));
                            if ($appType !== '') {
                                $m['registration_household_type'] = $appType;
                            }
                        }
                    }
                    unset($m);
                }
            }
        } catch (Exception $appEx) {
            error_log("Head household_type enrichment skipped: " . $appEx->getMessage());
        }

        $household['members'] = $members;
        $household['household_type'] = resolveHouseholdTypeForAdminApi($household, $members, $rawHouseholdTypeForDisplay);
        $household = enrichStructureHouseTypeFromHeadApplication($db, $household, $members);

        attachIndigentFieldsToHouseholdArray($db, $household, $members);

        sendResponse(true, 'Household retrieved successfully', $household);
        
    } catch (Throwable $e) {
        error_log("Get household error: " . $e->getMessage());
        $msg = defined('DEBUG_MODE') && DEBUG_MODE
            ? ('Error retrieving household: ' . $e->getMessage())
            : 'Error retrieving household';
        sendResponse(false, $msg, null, 500);
    }
}

/**
 * Persist address/structure fields from POST (house_number, street, house_type, house_ownership → housing_status).
 */
function householdPersistDetailFieldsFromPost($db, $householdId, $post) {
    $householdId = (int)$householdId;
    if ($householdId <= 0) {
        return false;
    }

    $house_number = sanitizeInput($post['house_number'] ?? '');
    $street = sanitizeInput($post['street'] ?? '');
    $house_type = sanitizeInput($post['house_type'] ?? '');
    $ownRaw = strtolower(trim((string)($post['house_ownership'] ?? '')));
    $housingStatus = null;
    if ($ownRaw === 'owned') {
        $housingStatus = 'owned';
    } elseif ($ownRaw === 'rented') {
        $housingStatus = 'renting';
    }

    $sets = [];
    $params = [];

    if (columnExists($db, 'households', 'house_number')) {
        $sets[] = 'house_number = ?';
        $params[] = $house_number;
    }
    if (columnExists($db, 'households', 'street')) {
        $sets[] = 'street = ?';
        $params[] = $street;
    }
    if ($house_type !== '' && columnExists($db, 'households', 'house_type')) {
        $sets[] = 'house_type = ?';
        $params[] = $house_type;
    }

    if ($housingStatus !== null && columnExists($db, 'households', 'housing_status')) {
        $sets[] = 'housing_status = ?';
        $params[] = $housingStatus;
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = $householdId;
    $db->query('UPDATE households SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    return true;
}

/**
 * Create new household
 */
function createHousehold() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $family_head_id = intval($_POST['family_head_id'] ?? 0);
    $address = sanitizeInput($_POST['address'] ?? '');
    $registration_date_raw = $_POST['registration_date'] ?? '';
    $registration_date = is_string($registration_date_raw) ? trim($registration_date_raw) : '';
    // If empty string is posted, default to today's date so households can be created without selecting a head.
    if ($registration_date === '') {
        $registration_date = date('Y-m-d');
    }
    $total_members_raw = $_POST['total_members'] ?? null;
    $total_members = is_null($total_members_raw) || $total_members_raw === '' ? null : intval($total_members_raw);
    
    try {
        $db = Database::getInstance();
        
        $family_head_db_val = null;
        if ($family_head_id > 0) {
            // Check if family head exists
            $familyHead = $db->fetchOne("SELECT id, household_id FROM residents WHERE id = ?", [$family_head_id]);
            if (!$familyHead) {
                sendResponse(false, 'Family head not found', null, 404);
                return;
            }
            if (!empty($familyHead['household_id'])) {
                sendResponse(false, 'Selected resident is already assigned to a household', null, 400);
                return;
            }
            $family_head_db_val = $family_head_id;
        }
        
        // Allow creating an empty household (no head yet, no address yet).
        // Your schema still has NOT NULL on `address`, so we must not pass NULL.
        $address_db_val = $address !== '' ? $address : '';
        $total_members_db_val = is_null($total_members) ? ($family_head_db_val ? 1 : 0) : max(0, $total_members);

        // Insert household
        $sql = "INSERT INTO households (family_head_id, address, total_members, registration_date)
                VALUES (?, ?, ?, ?)";

        $db->query($sql, [$family_head_db_val, $address_db_val, $total_members_db_val, $registration_date]);
        $householdId = $db->lastInsertId();

        householdPersistDetailFieldsFromPost($db, $householdId, $_POST);

        // Generate Household ID Code immediately, even if the household is still empty.
        if (columnExists($db, 'households', 'household_id_code')) {
            $currentCodeRow = $db->fetchOne("SELECT household_id_code FROM households WHERE id = ?", [$householdId]);
            $currentCode = $currentCodeRow['household_id_code'] ?? null;
            if (isMissingCode($currentCode)) {
                $db->query(
                    "UPDATE households SET household_id_code = ? WHERE id = ?",
                    [generateUniqueHouseholdCode($db), $householdId]
                );
            }
        }
        
        // If head was selected during creation, link them into the household and recompute member count.
        if ($family_head_db_val) {
            $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $family_head_db_val]);
            $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$householdId])['c'];
            $db->query("UPDATE households SET total_members = ? WHERE id = ?", [(int)$count, $householdId]);
        }
        
        // Get created household
        $household = $db->fetchOne("SELECT * FROM households WHERE id = ?", [$householdId]);
        
        sendResponse(true, 'Household created successfully', $household);
        
    } catch (Exception $e) {
        // If schema still enforces NOT NULL on family_head_id, fix automatically and retry once.
        $msgLower = strtolower($e->getMessage());
        $isFamilyHeadNullViolation = strpos($msgLower, "family_head_id") !== false
            && (strpos($msgLower, "cannot be null") !== false || strpos($msgLower, "null") !== false);

        $isAddressNullViolation = strpos($msgLower, "address") !== false
            && (strpos($msgLower, "cannot be null") !== false || strpos($msgLower, "null") !== false);

        if (($family_head_db_val === null && $isFamilyHeadNullViolation) || ($address_db_val === null && $isAddressNullViolation)) {
            try {
                $db = Database::getInstance();
                // Allow empty household creation for both head and address.
                if ($family_head_db_val === null && $isFamilyHeadNullViolation) {
                    $db->query("ALTER TABLE households MODIFY COLUMN family_head_id INT(11) NULL");
                }
                if ($address_db_val === null && $isAddressNullViolation) {
                    // address is text-ish in this project; make it nullable.
                    $db->query("ALTER TABLE households MODIFY COLUMN address TEXT NULL");
                }

                // Retry insert
                $sql = "INSERT INTO households (family_head_id, address, total_members, registration_date)
                        VALUES (?, ?, ?, ?)";
                $db->query($sql, [$family_head_db_val, $address_db_val, $total_members_db_val, $registration_date]);
                $householdId = $db->lastInsertId();

                householdPersistDetailFieldsFromPost($db, $householdId, $_POST);

                // Generate Household ID Code immediately, even if household is empty.
                if (columnExists($db, 'households', 'household_id_code')) {
                    $currentCodeRow = $db->fetchOne("SELECT household_id_code FROM households WHERE id = ?", [$householdId]);
                    $currentCode = $currentCodeRow['household_id_code'] ?? null;
                    if (isMissingCode($currentCode)) {
                        $db->query(
                            "UPDATE households SET household_id_code = ? WHERE id = ?",
                            [generateUniqueHouseholdCode($db), $householdId]
                        );
                    }
                }

                // If head was selected during creation, link them into the household and recompute member count.
                if ($family_head_db_val) {
                    $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $family_head_db_val]);
                    $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$householdId])['c'];
                    $db->query("UPDATE households SET total_members = ? WHERE id = ?", [(int)$count, $householdId]);
                }

                $household = $db->fetchOne("SELECT * FROM households WHERE id = ?", [$householdId]);
                sendResponse(true, 'Household created successfully', $household);
            } catch (Exception $e2) {
                // Fall through to standard error response.
                error_log("Retry create household error: " . $e2->getMessage());
            }
        }

        error_log("Create household error: " . $e->getMessage());
        $msg = 'Error creating household';
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $msg .= ': ' . $e->getMessage();
        }
        sendResponse(false, $msg, null, 500);
    }
}

/**
 * Update household
 */
function updateHousehold() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    $family_head_raw = $_POST['family_head_id'] ?? null;
    $family_head_id = is_null($family_head_raw) || $family_head_raw === '' ? null : intval($family_head_raw);
    $address_raw = $_POST['address'] ?? null;
    $address = is_null($address_raw) ? null : sanitizeInput($address_raw);
    $total_members = intval($_POST['total_members'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if household exists
        $existing = $db->fetchOne("SELECT * FROM households WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }
        
        $updates = [];
        $params = [];
        
        $assigningHead = false;
        if (!is_null($family_head_id)) {
            if ($family_head_id > 0) {
                $head = $db->fetchOne("SELECT id, household_id FROM residents WHERE id = ?", [$family_head_id]);
                if (!$head) {
                    sendResponse(false, 'Family head not found', null, 404);
                    return;
                }
                if (!empty($head['household_id']) && intval($head['household_id']) !== $id) {
                    sendResponse(false, 'Selected resident is already assigned to a different household', null, 400);
                    return;
                }
                // Link resident into this household when assigning as head.
                $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$id, $family_head_id]);
                $updates[] = "family_head_id = ?";
                $params[] = $family_head_id;
                $assigningHead = true;
            } else {
                // Explicitly unassign head when 0 is provided.
                $updates[] = "family_head_id = NULL";
            }
        }
        
        if (!is_null($address)) {
            $updates[] = "address = ?";
            // Your schema may have address as NOT NULL; keep empty as ''.
            $params[] = ($address === '' ? '' : $address);
        }
        
        if ($total_members >= 0 && isset($_POST['total_members'])) {
            $updates[] = "total_members = ?";
            $params[] = max(0, $total_members);
        }

        $detailSaved = householdPersistDetailFieldsFromPost($db, $id, $_POST);

        if (empty($updates) && !$detailSaved) {
            sendResponse(false, 'No fields to update', null, 400);
            return;
        }

        if (!empty($updates)) {
            $params[] = $id;
            $sql = "UPDATE households SET " . implode(', ', $updates) . " WHERE id = ?";
            $db->query($sql, $params);
        }

        // Always ensure codes exist:
        // - `household_id_code` should exist even when household is still empty
        // - `family_head_code` will only be generated when a head is actually assigned
        ensureHouseholdCodesAndDetails($db, $id);

        // Keep total_members aligned with actual linked residents when possible.
        $countRow = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$id]);
        if ($countRow && isset($countRow['c'])) {
            $db->query("UPDATE households SET total_members = ? WHERE id = ?", [(int)$countRow['c'], $id]);
        }
        
        // Get updated household
        $household = $db->fetchOne("SELECT * FROM households WHERE id = ?", [$id]);
        
        sendResponse(true, 'Household updated successfully', $household);
        
    } catch (Exception $e) {
        error_log("Update household error: " . $e->getMessage());
        sendResponse(false, 'Error updating household', null, 500);
    }
}

/**
 * Delete household
 */
function deleteHousehold() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Clear all household linkage fields from residents before deleting
        if (columnExists($db, 'residents', 'family_head_code')) {
            $db->query("UPDATE residents SET family_head_code = NULL WHERE household_id = ?", [$id]);
        }
        if (columnExists($db, 'residents', 'family_code')) {
            $db->query("UPDATE residents SET family_code = NULL WHERE household_id = ?", [$id]);
        }
        if (columnExists($db, 'residents', 'family_head_resident_id')) {
            $db->query("UPDATE residents SET family_head_resident_id = NULL WHERE household_id = ?", [$id]);
        }
        $db->query("UPDATE residents SET household_id = NULL WHERE household_id = ?", [$id]);
        
        // Delete household
        $db->query("DELETE FROM households WHERE id = ?", [$id]);
        
        sendResponse(true, 'Household deleted successfully', null);
        
    } catch (Exception $e) {
        error_log("Delete household error: " . $e->getMessage());
        sendResponse(false, 'Error deleting household', null, 500);
    }
}

/**
 * Add household member record (household_members table)
 */
function addHouseholdMemberRecord() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    // Sanitize and validate inputs
    $household_id = intval($_POST['household_id'] ?? 0);
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $suffix = sanitizeInput($_POST['suffix'] ?? '');
    $relationship_to_head = sanitizeInput($_POST['relationship_to_head'] ?? '');
    $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
    $occupation = sanitizeInput($_POST['occupation'] ?? '');
    $government_id_type = sanitizeInput($_POST['government_id_type'] ?? '');
    $government_id_number = sanitizeInput($_POST['government_id_number'] ?? '');
    $voter_status = sanitizeInput($_POST['voter_status'] ?? 'Not Registered');
    $voter_id_number = sanitizeInput($_POST['voter_id_number'] ?? '');
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $is_head = intval($_POST['is_head'] ?? 0);
    $is_senior_citizen = intval($_POST['is_senior_citizen'] ?? 0);
    $is_pwd = intval($_POST['is_pwd'] ?? 0);
    $is_4ps_beneficiary = intval($_POST['is_4ps_beneficiary'] ?? 0);
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    
    // Validation
    if (!$household_id || empty($first_name) || empty($last_name) || empty($relationship_to_head) || 
        empty($date_of_birth) || empty($gender) || empty($civil_status)) {
        sendResponse(false, 'Required fields are missing', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if household exists
        $household = $db->fetchOne("SELECT id FROM households WHERE id = ?", [$household_id]);
        if (!$household) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }
        
        // Insert household member
        $sql = "INSERT INTO household_members (
                    household_id, first_name, middle_name, last_name, suffix, 
                    relationship_to_head, date_of_birth, gender, civil_status, 
                    occupation, government_id_type, government_id_number, 
                    voter_status, voter_id_number, contact_number, email, 
                    is_head, is_senior_citizen, is_pwd, is_4ps_beneficiary, remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->query($sql, [
            $household_id, $first_name, $middle_name, $last_name, $suffix,
            $relationship_to_head, $date_of_birth, $gender, $civil_status,
            $occupation, $government_id_type, $government_id_number,
            $voter_status, $voter_id_number, $contact_number, $email,
            $is_head, $is_senior_citizen, $is_pwd, $is_4ps_beneficiary, $remarks
        ]);
        
        // Update household statistics
        updateHouseholdStatistics($household_id);
        
        sendResponse(true, 'Household member added successfully');
        
    } catch (Exception $e) {
        error_log("Add household member record error: " . $e->getMessage());
        sendResponse(false, 'Error adding household member: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Get household member details
 */
function getHouseholdMemberDetails() {
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Member ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM household_members WHERE id = ?";
        $member = $db->fetchOne($sql, [$id]);
        
        if (!$member) {
            sendResponse(false, 'Member not found', null, 404);
            return;
        }
        
        sendResponse(true, 'Member retrieved successfully', $member);
        
    } catch (Exception $e) {
        error_log("Get household member error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving member', null, 500);
    }
}

/**
 * Update household member
 */
function updateHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['member_id'] ?? 0);
    $household_id = intval($_POST['household_id'] ?? 0);
    
    if (!$id || !$household_id) {
        sendResponse(false, 'Member ID and Household ID are required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if member exists
        $existing = $db->fetchOne("SELECT id, household_id FROM household_members WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Member not found', null, 404);
            return;
        }
        
        // Sanitize inputs
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $suffix = sanitizeInput($_POST['suffix'] ?? '');
        $relationship_to_head = sanitizeInput($_POST['relationship_to_head'] ?? '');
        $date_of_birth = sanitizeInput($_POST['date_of_birth'] ?? '');
        $gender = sanitizeInput($_POST['gender'] ?? '');
        $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
        $occupation = sanitizeInput($_POST['occupation'] ?? '');
        $government_id_type = sanitizeInput($_POST['government_id_type'] ?? '');
        $government_id_number = sanitizeInput($_POST['government_id_number'] ?? '');
        $voter_status = sanitizeInput($_POST['voter_status'] ?? 'Not Registered');
        $voter_id_number = sanitizeInput($_POST['voter_id_number'] ?? '');
        $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $is_head = intval($_POST['is_head'] ?? 0);
        $is_senior_citizen = intval($_POST['is_senior_citizen'] ?? 0);
        $is_pwd = intval($_POST['is_pwd'] ?? 0);
        $is_4ps_beneficiary = intval($_POST['is_4ps_beneficiary'] ?? 0);
        $remarks = sanitizeInput($_POST['remarks'] ?? '');
        
        // Update member
        $sql = "UPDATE household_members SET 
                first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                relationship_to_head = ?, date_of_birth = ?, gender = ?, civil_status = ?,
                occupation = ?, government_id_type = ?, government_id_number = ?,
                voter_status = ?, voter_id_number = ?, contact_number = ?, email = ?,
                is_head = ?, is_senior_citizen = ?, is_pwd = ?, is_4ps_beneficiary = ?,
                remarks = ?
                WHERE id = ?";
        
        $db->query($sql, [
            $first_name, $middle_name, $last_name, $suffix,
            $relationship_to_head, $date_of_birth, $gender, $civil_status,
            $occupation, $government_id_type, $government_id_number,
            $voter_status, $voter_id_number, $contact_number, $email,
            $is_head, $is_senior_citizen, $is_pwd, $is_4ps_beneficiary,
            $remarks, $id
        ]);
        
        // Update household statistics
        updateHouseholdStatistics($household_id);
        
        sendResponse(true, 'Member updated successfully');
        
    } catch (Exception $e) {
        error_log("Update member error: " . $e->getMessage());
        sendResponse(false, 'Error updating member: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Delete household member
 */
function deleteHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['member_id'] ?? 0);
    $household_id = intval($_POST['household_id'] ?? 0);
    
    if (!$id || !$household_id) {
        sendResponse(false, 'Member ID and Household ID are required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if member is household head
        $member = $db->fetchOne("SELECT is_head FROM household_members WHERE id = ?", [$id]);
        if ($member && $member['is_head'] == 1) {
            sendResponse(false, 'Cannot delete household head', null, 400);
            return;
        }
        
        // Delete member
        $db->query("DELETE FROM household_members WHERE id = ?", [$id]);
        
        // Update household statistics
        updateHouseholdStatistics($household_id);
        
        sendResponse(true, 'Member deleted successfully');
        
    } catch (Exception $e) {
        error_log("Delete member error: " . $e->getMessage());
        sendResponse(false, 'Error deleting member: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Update household details (address, emergency contact, notes)
 */
function updateHouseholdDetails() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['household_id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if household exists
        $existing = $db->fetchOne("SELECT id FROM households WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }
        
        // Sanitize inputs
        $house_number = sanitizeInput($_POST['house_number'] ?? '');
        $street = sanitizeInput($_POST['street'] ?? '');
        $purok_sitio = sanitizeInput($_POST['purok_sitio'] ?? '');
        $postal_code = sanitizeInput($_POST['postal_code'] ?? '1013');
        $emergency_contact_name = sanitizeInput($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');
        $special_notes = sanitizeInput($_POST['special_notes'] ?? '');
        
        // Update household
        $sql = "UPDATE households SET 
                house_number = ?, street = ?, purok_sitio = ?, postal_code = ?,
                emergency_contact_name = ?, emergency_contact_phone = ?, special_notes = ?
                WHERE id = ?";
        
        $db->query($sql, [
            $house_number, $street, $purok_sitio, $postal_code,
            $emergency_contact_name, $emergency_contact_phone, $special_notes, $id
        ]);
        
        sendResponse(true, 'Household details updated successfully');
        
    } catch (Exception $e) {
        error_log("Update household details error: " . $e->getMessage());
        sendResponse(false, 'Error updating household details: ' . $e->getMessage(), null, 500);
    }
}

/**
 * Update household statistics (total members, adults, minors, seniors)
 */
function updateHouseholdStatistics($household_id) {
    try {
        $db = Database::getInstance();
        
        // Get all members with ages
        $members = $db->fetchAll(
            "SELECT age FROM household_members WHERE household_id = ?", 
            [$household_id]
        );
        
        $total = count($members);
        $adults = 0;
        $minors = 0;
        $seniors = 0;
        
        foreach ($members as $member) {
            $age = intval($member['age']);
            
            if ($age >= 60) {
                $seniors++;
                $adults++; // Seniors are also counted as adults
            } elseif ($age >= 18) {
                $adults++;
            } else {
                $minors++;
            }
        }
        
        // Update household
        $sql = "UPDATE households SET 
                total_members = ?, number_of_adults = ?, 
                number_of_minors = ?, number_of_seniors = ?
                WHERE id = ?";
        
        $db->query($sql, [$total, $adults, $minors, $seniors, $household_id]);
        
    } catch (Exception $e) {
        error_log("Update household statistics error: " . $e->getMessage());
        // Don't throw error, just log it
    }
}

/**
 * Map residents.gender / civil_status to household_members ENUM labels (schema uses Title Case).
 */
function normalizeHouseholdMemberGenderEnum($raw) {
    $g = strtolower(trim((string)$raw));
    if ($g === 'male' || $g === 'm') {
        return 'Male';
    }
    if ($g === 'female' || $g === 'f') {
        return 'Female';
    }
    if (in_array($g, ['other', 'o', 'prefer not to say', ''], true)) {
        return 'Other';
    }
    $t = ucfirst($g);
    return in_array($t, ['Male', 'Female', 'Other'], true) ? $t : 'Other';
}

function normalizeHouseholdMemberCivilEnum($raw) {
    $s = strtolower(trim((string)$raw));
    $map = [
        'single' => 'Single',
        'married' => 'Married',
        'widowed' => 'Widowed',
        'divorced' => 'Divorced',
        'separated' => 'Separated',
    ];
    if (isset($map[$s])) {
        return $map[$s];
    }
    $t = ucfirst($s);
    return in_array($t, ['Single', 'Married', 'Widowed', 'Divorced', 'Separated'], true) ? $t : 'Single';
}

/**
 * Add resident to household
 */
function addHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    if (!canPerformModulePermission('households', 'can_edit')) {
        sendResponse(false, 'Access denied', null, 403);
        return;
    }
    $household_id = intval($_POST['household_id'] ?? 0);
    $resident_id = intval($_POST['resident_id'] ?? 0);
    if (!$household_id || !$resident_id) {
        sendResponse(false, 'Household ID and resident ID are required', null, 400);
        return;
    }
    try {
        $db = Database::getInstance();
        $household = $db->fetchOne("SELECT id, total_members, family_head_id FROM households WHERE id = ?", [$household_id]);
        if (!$household) { sendResponse(false, 'Household not found', null, 404); return; }
        $resident = $db->fetchOne("SELECT id FROM residents WHERE id = ?", [$resident_id]);
        if (!$resident) { sendResponse(false, 'Resident not found', null, 404); return; }
        // Do not allow adding a resident already assigned elsewhere.
        $existingHousehold = $db->fetchOne("SELECT household_id FROM residents WHERE id = ?", [$resident_id]);
        if ($existingHousehold && !empty($existingHousehold['household_id']) && intval($existingHousehold['household_id']) !== $household_id) {
            sendResponse(false, 'Resident is already assigned to a different household', null, 400);
            return;
        }

        $hadHead = !empty($household['family_head_id']);
        $relationship_to_head = trim(sanitizeInput((string)($_POST['relationship_to_head'] ?? '')));

        // If this household does not yet have a head, auto-promote this resident as head.
        if (!$hadHead) {
            $relationship_to_head = 'Head';
            $db->query("UPDATE households SET family_head_id = ? WHERE id = ?", [$resident_id, $household_id]);
        } else {
            $allowedRel = [
                'Spouse', 'Son', 'Daughter', 'Mother', 'Father', 'Brother', 'Sister',
                'Grandchild', 'Grandparent', 'Son-in-Law', 'Daughter-in-Law', 'Sibling-in-Law',
                'Nephew', 'Niece', 'Uncle', 'Aunt', 'Cousin', 'Boarder', 'Tenant', 'Helper',
                'Non-Relative', 'Other', 'Relative', 'Member'
            ];
            if ($relationship_to_head !== '' && !in_array($relationship_to_head, $allowedRel, true)) {
                sendResponse(false, 'Invalid relationship to head', null, 400);
                return;
            }
        }

        // Residents column is nullable; household_members.relationship_to_head may be NOT NULL — use Member when unspecified.
        $relForResidents = ($relationship_to_head === '') ? null : $relationship_to_head;
        $relForHouseholdMembers = ($relationship_to_head === '') ? 'Member' : $relationship_to_head;

        if (columnExists($db, 'residents', 'relationship_to_head')) {
            $db->query(
                'UPDATE residents SET household_id = ?, relationship_to_head = ? WHERE id = ?',
                [$household_id, $relForResidents, $resident_id]
            );
        } else {
            $db->query('UPDATE residents SET household_id = ? WHERE id = ?', [$household_id, $resident_id]);
        }

        // Clear stale household linkage fields so the member doesn't appear as a head
        // from a previous household, and sync family_code to match the current head.
        $isNewHead = !$hadHead;
        if (!$isNewHead && columnExists($db, 'residents', 'family_head_code')) {
            $db->query("UPDATE residents SET family_head_code = NULL WHERE id = ?", [$resident_id]);
        }
        $headId = $isNewHead ? $resident_id : (int)$household['family_head_id'];
        if (!$isNewHead && columnExists($db, 'residents', 'family_code')) {
            $headFcRow = $db->fetchOne("SELECT family_code FROM residents WHERE id = ? LIMIT 1", [$headId]);
            $headFc = trim((string)($headFcRow['family_code'] ?? ''));
            if ($headFc !== '') {
                $db->query("UPDATE residents SET family_code = ? WHERE id = ?", [$headFc, $resident_id]);
            }
        }
        if (!$isNewHead && columnExists($db, 'residents', 'family_head_resident_id')) {
            $db->query("UPDATE residents SET family_head_resident_id = ? WHERE id = ?", [$headId, $resident_id]);
        }

        if (tableExists($db, 'household_members') && columnExists($db, 'household_members', 'resident_id')) {
            try {
                $resRow = $db->fetchOne('SELECT * FROM residents WHERE id = ? LIMIT 1', [$resident_id]);
                if ($resRow) {
                    $dob = $resRow['birth_date'] ?? $resRow['date_of_birth'] ?? '1990-01-01';
                    $gender = normalizeHouseholdMemberGenderEnum($resRow['gender'] ?? 'other');
                    $civilStatus = normalizeHouseholdMemberCivilEnum($resRow['civil_status'] ?? 'single');
                    $hmDateCol = columnExists($db, 'household_members', 'date_of_birth') ? 'date_of_birth' : 'dob';
                    $existingHm = $db->fetchOne(
                        'SELECT id FROM household_members WHERE resident_id = ? AND household_id = ? LIMIT 1',
                        [$resident_id, $household_id]
                    );
                    if ($existingHm) {
                        $db->query(
                            'UPDATE household_members SET relationship_to_head = ? WHERE resident_id = ? AND household_id = ?',
                            [$relForHouseholdMembers, $resident_id, $household_id]
                        );
                    } else {
                        $db->query(
                            "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$hmDateCol}, gender, civil_status) VALUES (?, ?, ?, ?, ?, ?)",
                            [$household_id, $resident_id, $relForHouseholdMembers, $dob, $gender, $civilStatus]
                        );
                    }
                }
            } catch (Throwable $hmEx) {
                error_log('addHouseholdMember household_members: ' . $hmEx->getMessage());
            }
        }

        $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$household_id])['c'];
        $db->query("UPDATE households SET total_members = ? WHERE id = ?", [$count, $household_id]);
        // Ensure codes and details are generated once a head exists.
        ensureHouseholdCodesAndDetails($db, $household_id);
        sendResponse(true, 'Member added to household');
    } catch (Throwable $e) {
        error_log("Add member error: " . $e->getMessage());
        sendResponse(false, 'Error adding member', null, 500);
    }
}

/**
 * Remove resident from household
 */
function removeHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    $resident_id = intval($_POST['resident_id'] ?? 0);
    if (!$resident_id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    try {
        $db = Database::getInstance();
        $resident = $db->fetchOne("SELECT household_id FROM residents WHERE id = ?", [$resident_id]);
        if (!$resident || !$resident['household_id']) {
            sendResponse(false, 'Resident not in a household', null, 400);
            return;
        }
        $household_id = $resident['household_id'];
        $headRow = $db->fetchOne("SELECT family_head_id FROM households WHERE id = ?", [$household_id]);
        $isDesignatedHead = $headRow && !empty($headRow['family_head_id']) && intval($headRow['family_head_id']) === $resident_id;
        if ($isDesignatedHead) {
            $others = $db->fetchAll("SELECT id FROM residents WHERE household_id = ? AND id != ? LIMIT 1", [$household_id, $resident_id]);
            if (!empty($others)) {
                $newHeadId = (int)$others[0]['id'];
                $houseCols = $db->fetchAll("SHOW COLUMNS FROM households");
                $colMap = [];
                foreach ($houseCols as $c) $colMap[$c['Field']] = true;
                $headColumn = isset($colMap['family_head_id']) ? 'family_head_id' : 'head_id';
                $db->query("UPDATE households SET {$headColumn} = ? WHERE id = ?", [$newHeadId, $household_id]);
                if (isset($colMap['family_head_id']) && $headColumn !== 'family_head_id') {
                    $db->query("UPDATE households SET family_head_id = ? WHERE id = ?", [$newHeadId, $household_id]);
                }
                if (isset($colMap['head_id']) && $headColumn !== 'head_id') {
                    $db->query("UPDATE households SET head_id = ? WHERE id = ?", [$newHeadId, $household_id]);
                }
            } else {
                $houseCols = $db->fetchAll("SHOW COLUMNS FROM households");
                $colMap = [];
                foreach ($houseCols as $c) $colMap[$c['Field']] = true;
                $headColumn = isset($colMap['family_head_id']) ? 'family_head_id' : 'head_id';
                $db->query("UPDATE households SET {$headColumn} = NULL WHERE id = ?", [$household_id]);
                if (isset($colMap['head_id']) && $headColumn !== 'head_id') {
                    $db->query("UPDATE households SET head_id = NULL WHERE id = ?", [$household_id]);
                }
            }
        }
        // Remove from both residents.household_id AND household_members so resident-side Household Information stays in sync
        if (tableExists($db, 'household_members')) {
            $db->query("DELETE FROM household_members WHERE resident_id = ? AND household_id = ?", [$resident_id, $household_id]);
        }
        $db->query("UPDATE residents SET household_id = NULL WHERE id = ?", [$resident_id]);
        // Clear all household linkage fields so the resident doesn't carry stale codes
        if (columnExists($db, 'residents', 'family_head_code')) {
            $db->query("UPDATE residents SET family_head_code = NULL WHERE id = ?", [$resident_id]);
        }
        if (columnExists($db, 'residents', 'family_code')) {
            $db->query("UPDATE residents SET family_code = NULL WHERE id = ?", [$resident_id]);
        }
        if (columnExists($db, 'residents', 'family_head_resident_id')) {
            $db->query("UPDATE residents SET family_head_resident_id = NULL WHERE id = ?", [$resident_id]);
        }
        $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$household_id])['c'];
        $db->query("UPDATE households SET total_members = ? WHERE id = ?", [(int)$count, $household_id]);
        sendResponse(true, 'Member removed from household');
    } catch (Exception $e) {
        error_log("Remove member error: " . $e->getMessage());
        sendResponse(false, 'Error removing member', null, 500);
    }
}

/**
 * Transfer head role to another member (officials only).
 * Same logic as resident-side: the head who OWNS the family group (old_head_resident_id) loses the role,
 * the selected member gains it and receives the family_head_code. Members are "inside" a head by family_code.
 */
function assignHeadOfficial() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $householdId = (int)($_POST['household_id'] ?? 0);
    $newHeadResidentId = (int)($_POST['new_head_resident_id'] ?? 0);
    $oldHeadResidentIdParam = (int)($_POST['old_head_resident_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '') {
        sendResponse(false, 'Reason is required to transfer head role', null, 400);
        return;
    }
    if (strlen($reason) > 200) {
        sendResponse(false, 'Reason is too long', null, 400);
        return;
    }
    $reasonForLog = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

    if (!$householdId || !$newHeadResidentId) {
        sendResponse(false, 'household_id and new_head_resident_id are required', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        $houseCols = $db->fetchAll("SHOW COLUMNS FROM households");
        $colMap = [];
        foreach ($houseCols as $c) $colMap[$c['Field']] = true;
        $headColumn = isset($colMap['family_head_id']) ? 'family_head_id' : 'head_id';

        $household = $db->fetchOne("SELECT id, `{$headColumn}` AS hid FROM households WHERE id = ?", [$householdId]);
        if (!$household) {
            sendResponse(false, 'Household not found', null, 404);
            return;
        }

        $designatedHeadId = (int)($household['hid'] ?? 0);
        $oldHeadResidentId = $oldHeadResidentIdParam > 0 ? $oldHeadResidentIdParam : $designatedHeadId;

        if ($oldHeadResidentId <= 0) {
            sendResponse(false, 'Cannot determine which head to transfer from. Ensure the household has a designated head or the member is under a head.', null, 400);
            return;
        }
        if ($oldHeadResidentId === $newHeadResidentId) {
            sendResponse(false, 'Selected resident is already the head of this family group', null, 400);
            return;
        }

        $resident = $db->fetchOne("SELECT id, household_id FROM residents WHERE id = ?", [$newHeadResidentId]);
        if (!$resident) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }
        if ((int)($resident['household_id'] ?? 0) !== $householdId) {
            sendResponse(false, 'Resident is not a member of this household', null, 400);
            return;
        }

        if (!householdHeadTransferSameFamilyGroup($db, $householdId, $oldHeadResidentId, $newHeadResidentId, $designatedHeadId)) {
            sendResponse(false, 'You can only transfer head role to members in the same family group', null, 400);
            return;
        }

        $ageErr = householdHeadMinimumAgeError($db, $newHeadResidentId);
        if ($ageErr !== null) {
            sendResponse(false, $ageErr, null, 400);
            return;
        }

        $priorNewRel = fetchResidentRelationshipToHeadBeforeTransfer($db, $newHeadResidentId, $householdId);
        $formerHeadRelationship = relationshipForFormerHeadAfterTransfer($priorNewRel);

        $db->beginTransaction();
        try {
            if ($designatedHeadId === $oldHeadResidentId) {
                $db->query("UPDATE households SET {$headColumn} = ? WHERE id = ?", [$newHeadResidentId, $householdId]);
                if (isset($colMap['family_head_id']) && $headColumn !== 'family_head_id') {
                    $db->query("UPDATE households SET family_head_id = ? WHERE id = ?", [$newHeadResidentId, $householdId]);
                }
                if (isset($colMap['head_id']) && $headColumn !== 'head_id') {
                    $db->query("UPDATE households SET head_id = ? WHERE id = ?", [$newHeadResidentId, $householdId]);
                }
            }

            if (tableExists($db, 'household_members')) {
                $db->query("UPDATE household_members SET relationship_to_head = ? WHERE resident_id = ? AND household_id = ?", ['Head', $newHeadResidentId, $householdId]);
                if ($oldHeadResidentId > 0) {
                    $db->query("UPDATE household_members SET relationship_to_head = ? WHERE resident_id = ? AND household_id = ?", [$formerHeadRelationship, $oldHeadResidentId, $householdId]);
                }
            }

            if (columnExists($db, 'residents', 'relationship_to_head')) {
                $db->query("UPDATE residents SET relationship_to_head = ? WHERE id = ?", ['Head', $newHeadResidentId]);
                if ($oldHeadResidentId > 0) {
                    $db->query("UPDATE residents SET relationship_to_head = ? WHERE id = ?", [$formerHeadRelationship, $oldHeadResidentId]);
                }
            }

            // Ensure the new designated head always has a family_head_code; restore from old head or generate.
            if (columnExists($db, 'residents', 'family_head_code')) {
                $oldFhc = '';
                if ($oldHeadResidentId > 0) {
                    $oldHead = $db->fetchOne("SELECT family_head_code FROM residents WHERE id = ?", [$oldHeadResidentId]);
                    $oldFhc = $oldHead ? trim((string)($oldHead['family_head_code'] ?? '')) : '';
                }
                if (($oldFhc === '' || $oldFhc === '-') && $designatedHeadId === $oldHeadResidentId && isset($colMap['family_head_code'])) {
                    $hh = $db->fetchOne("SELECT family_head_code FROM households WHERE id = ? LIMIT 1", [$householdId]);
                    $oldFhc = $hh ? trim((string)($hh['family_head_code'] ?? '')) : '';
                }
                if ($oldFhc === '' || $oldFhc === '-') {
                    $oldFhc = generateResidentFamilyHeadCode($db);
                }
                $db->query("UPDATE residents SET family_head_code = ? WHERE id = ?", [$oldFhc, $newHeadResidentId]);
                if ($oldHeadResidentId > 0) {
                    $db->query("UPDATE residents SET family_head_code = NULL WHERE id = ?", [$oldHeadResidentId]);
                }
                if ($designatedHeadId === $oldHeadResidentId && isset($colMap['family_head_code'])) {
                    $db->query("UPDATE households SET family_head_code = ? WHERE id = ?", [$oldFhc, $householdId]);
                }
            }

            // Sync family_code for all remaining members to match the new head
            if (columnExists($db, 'residents', 'family_code')) {
                $newHeadFcRow = $db->fetchOne("SELECT family_code FROM residents WHERE id = ? LIMIT 1", [$newHeadResidentId]);
                $newHeadFc = trim((string)($newHeadFcRow['family_code'] ?? ''));
                if ($newHeadFc !== '') {
                    $db->query(
                        "UPDATE residents SET family_code = ? WHERE household_id = ? AND id <> ?",
                        [$newHeadFc, $householdId, $newHeadResidentId]
                    );
                }
            }
            // Ensure non-head members don't retain stale family_head_code
            if (columnExists($db, 'residents', 'family_head_code')) {
                $db->query(
                    "UPDATE residents SET family_head_code = NULL WHERE household_id = ? AND id <> ? AND family_head_code IS NOT NULL AND TRIM(family_head_code) <> ''",
                    [$householdId, $newHeadResidentId]
                );
            }
            // Point all members' family_head_resident_id to the new head
            if (columnExists($db, 'residents', 'family_head_resident_id')) {
                $db->query(
                    "UPDATE residents SET family_head_resident_id = ? WHERE household_id = ? AND id <> ?",
                    [$newHeadResidentId, $householdId, $newHeadResidentId]
                );
            }

            if (tableExists($db, 'household_history_logs')) {
                $performedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $db->query(
                    "INSERT INTO household_history_logs (household_id, action, performed_by, target_resident_id, details) VALUES (?, ?, ?, ?, ?)",
                    [$householdId, 'Head Changed', $performedBy, $newHeadResidentId, 'Household head transferred by official. Reason: ' . $reasonForLog]
                );
            }

            $db->commit();
            sendResponse(true, 'Household head transferred successfully', ['new_head_resident_id' => $newHeadResidentId]);
        } catch (Exception $e) {
            $db->rollback();
            throw $e;
        }
    } catch (Exception $e) {
        error_log("Assign head official error: " . $e->getMessage());
        sendResponse(false, $e->getMessage(), null, 500);
    }
}

/**
 * Get household members (from household_members table)
 */
function getHouseholdMembers() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Household ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "SELECT * FROM household_members WHERE household_id = ? ORDER BY is_head DESC, date_of_birth ASC";
        $members = $db->fetchAll($sql, [$id]);
        
        sendResponse(true, 'Household members retrieved successfully', $members);
        
    } catch (Exception $e) {
        error_log("Get household members error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving household members', null, 500);
    }
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }

    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($response, $flags);
    exit();
}
