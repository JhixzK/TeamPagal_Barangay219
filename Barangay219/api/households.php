<?php
/**
 * E-Barangay Information Management System
 * Household Management API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('households');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listHouseholds();
        break;
    
    case 'get':
        getHousehold();
        break;

    // Return possible family-head choices for a household (distinct FH codes).
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
    // FH-XXXXX (5 digits)
    for ($i = 0; $i < 20; $i++) {
        $code = 'FH-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $exists = $db->fetchOne("SELECT id FROM households WHERE family_head_code = ? LIMIT 1", [$code]);
        if (!$exists) {
            return $code;
        }
    }
    throw new Exception('Unable to generate unique family head code.');
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
function listHouseholds() {
    try {
        $db = Database::getInstance();
        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $from = sanitizeInput($_GET['from'] ?? '');
        $to = sanitizeInput($_GET['to'] ?? '');
        $where = '1=1';
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where = "(h.address LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
            $params = [$term, $term];
        }
        if (!empty($from)) {
            $where .= " AND DATE(h.registration_date) >= ?";
            $params[] = $from;
        }
        if (!empty($to)) {
            $where .= " AND DATE(h.registration_date) <= ?";
            $params[] = $to;
        }
        $sql = "SELECT h.*, 
                       CONCAT(r.first_name, ' ', COALESCE(r.middle_name, ''), ' ', r.last_name) as family_head_name,
                       r.contact_number as family_head_contact
                FROM households h
                LEFT JOIN residents r ON h.family_head_id = r.id
                WHERE $where
                ORDER BY h.registration_date DESC, h.id DESC";
        
        $households = $db->fetchAll($sql, $params);

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
                }
            }
            unset($m);
        }
        } catch (Exception $hmEx) {
            error_log("Household members enrichment skipped: " . $hmEx->getMessage());
        }
        $household['members'] = $members;
        
        sendResponse(true, 'Household retrieved successfully', $household);
        
    } catch (Exception $e) {
        error_log("Get household error: " . $e->getMessage());
        $msg = defined('DEBUG_MODE') && DEBUG_MODE
            ? ('Error retrieving household: ' . $e->getMessage())
            : 'Error retrieving household';
        sendResponse(false, $msg, null, 500);
    }
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
        
        if (empty($updates)) {
            sendResponse(false, 'No fields to update', null, 400);
            return;
        }
        
        $params[] = $id;
        $sql = "UPDATE households SET " . implode(', ', $updates) . " WHERE id = ?";
        
        $db->query($sql, $params);

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
        
        // Remove household_id from residents
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
 * Add resident to household
 */
function addHouseholdMember() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
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

        // If this household does not yet have a head, auto-promote this resident as head.
        if (empty($household['family_head_id'])) {
            $db->query("UPDATE households SET family_head_id = ? WHERE id = ?", [$resident_id, $household_id]);
        }

        $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$household_id, $resident_id]);
        $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$household_id])['c'];
        $db->query("UPDATE households SET total_members = ? WHERE id = ?", [$count, $household_id]);
        // Ensure codes and details are generated once a head exists.
        ensureHouseholdCodesAndDetails($db, $household_id);
        sendResponse(true, 'Member added to household');
    } catch (Exception $e) {
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
        if ($headRow && !empty($headRow['family_head_id']) && intval($headRow['family_head_id']) === $resident_id) {
            sendResponse(false, 'Cannot remove the family head. Assign a new head first.', null, 400);
            return;
        }
        // Remove from both residents.household_id AND household_members so resident-side Household Information stays in sync
        if (tableExists($db, 'household_members')) {
            $db->query("DELETE FROM household_members WHERE resident_id = ? AND household_id = ?", [$resident_id, $household_id]);
        }
        $db->query("UPDATE residents SET household_id = NULL WHERE id = ?", [$resident_id]);
        $count = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE household_id = ?", [$household_id])['c'];
        $db->query("UPDATE households SET total_members = ? WHERE id = ?", [(int)$count, $household_id]);
        sendResponse(true, 'Member removed from household');
    } catch (Exception $e) {
        error_log("Remove member error: " . $e->getMessage());
        sendResponse(false, 'Error removing member', null, 500);
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
    
    echo json_encode($response);
    exit();
}
