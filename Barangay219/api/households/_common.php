<?php
header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth-check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

function householdJsonResponse($success, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function getRequestBodyData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '{}', true);
        return is_array($json) ? $json : [];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $raw = file_get_contents('php://input');
        $parsed = [];
        parse_str($raw ?: '', $parsed);
        return is_array($parsed) ? $parsed : [];
    }

    return $_POST;
}

function requireResidentHouseholdSession() {
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        householdJsonResponse(false, null, 'Unauthorized', 401);
    }

    // True residents always pass. Officials/staff in "Resident View" keep their real role in session
    // (e.g. secretary) but must still access resident household APIs — allow when view_mode is resident.
    $realRole = normalizeRole(getRealUserRole());
    $isTrueResident = ($realRole === normalizeRole(ROLE_RESIDENT));
    $isStaffInResidentView = !$isTrueResident
        && function_exists('isResidentView')
        && isResidentView();

    if (!$isTrueResident && !$isStaffInResidentView) {
        householdJsonResponse(false, null, 'Forbidden', 403);
    }

    $residentId = (int)($_SESSION['resident_id'] ?? 0);
    if ($residentId <= 0) {
        householdJsonResponse(false, null, 'Invalid resident session', 401);
    }

    return $residentId;
}

function ensureResidentHouseholdSchema() {
    $db = Database::getInstance();

    $db->query(
        "CREATE TABLE IF NOT EXISTS households (
            id INT(11) NOT NULL AUTO_INCREMENT,
            head_id INT(11) NULL,
            house_number VARCHAR(120) DEFAULT NULL,
            street VARCHAR(200) DEFAULT NULL,
            barangay VARCHAR(150) NULL,
            city VARCHAR(150) NULL,
            province VARCHAR(150) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_head_id (head_id),
            CONSTRAINT fk_households_head_resident FOREIGN KEY (head_id) REFERENCES residents(id)
                ON UPDATE CASCADE ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $houseCols = getColumnsMap('households');

    addColumnIfMissing('households', $houseCols, 'head_id', 'INT(11) NULL');
    addColumnIfMissing('households', $houseCols, 'house_number', 'VARCHAR(120) NULL');
    addColumnIfMissing('households', $houseCols, 'street', 'VARCHAR(200) NULL');
    addColumnIfMissing('households', $houseCols, 'barangay', "VARCHAR(150) NULL DEFAULT 'Barangay 219'");
    addColumnIfMissing('households', $houseCols, 'city', "VARCHAR(150) NULL DEFAULT 'Manila'");
    addColumnIfMissing('households', $houseCols, 'province', "VARCHAR(150) NULL DEFAULT 'Metro Manila'");
    addColumnIfMissing('households', $houseCols, 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    addColumnIfMissing('households', $houseCols, 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    $houseCols = getColumnsMap('households');
    if (!isset($houseCols['head_id']) && isset($houseCols['family_head_id'])) {
        $db->query("ALTER TABLE households ADD COLUMN head_id INT(11) NULL");
        $db->query("UPDATE households SET head_id = family_head_id WHERE (head_id IS NULL OR head_id = 0) AND family_head_id IS NOT NULL");
    } elseif (isset($houseCols['family_head_id'])) {
        $db->query("UPDATE households SET head_id = family_head_id WHERE (head_id IS NULL OR head_id = 0) AND family_head_id IS NOT NULL");
    }

    if (isset($houseCols['address'])) {
        $db->query("UPDATE households SET street = address WHERE (street IS NULL OR street = '') AND address IS NOT NULL");
    }

    // Allow empty households (head can be assigned later by officials).
    $db->query("ALTER TABLE households MODIFY COLUMN head_id INT(11) NULL");

    $headIndex = $db->fetchOne("SHOW INDEX FROM households WHERE Key_name = 'idx_head_id'");
    if (!$headIndex) {
        $db->query("ALTER TABLE households ADD KEY idx_head_id (head_id)");
    }

    $db->query(
        "CREATE TABLE IF NOT EXISTS household_members (
            id INT(11) NOT NULL AUTO_INCREMENT,
            household_id INT(11) NOT NULL,
            resident_id INT(11) NOT NULL,
            relationship_to_head VARCHAR(80) NOT NULL,
            dob DATE NOT NULL,
            gender VARCHAR(20) NOT NULL,
            civil_status VARCHAR(30) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_household_id (household_id),
            KEY idx_resident_id (resident_id),
            CONSTRAINT fk_household_members_household FOREIGN KEY (household_id) REFERENCES households(id)
                ON UPDATE CASCADE ON DELETE CASCADE,
            CONSTRAINT fk_household_members_resident FOREIGN KEY (resident_id) REFERENCES residents(id)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $memberCols = getColumnsMap('household_members');
    addColumnIfMissing('household_members', $memberCols, 'resident_id', 'INT(11) NULL');
    addColumnIfMissing('household_members', $memberCols, 'relationship_to_head', "VARCHAR(80) NOT NULL DEFAULT 'Member'");
    addColumnIfMissing('household_members', $memberCols, 'dob', 'DATE NULL');
    addColumnIfMissing('household_members', $memberCols, 'gender', "VARCHAR(20) NOT NULL DEFAULT 'other'");
    addColumnIfMissing('household_members', $memberCols, 'civil_status', "VARCHAR(30) NOT NULL DEFAULT 'single'");
    addColumnIfMissing('household_members', $memberCols, 'created_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP');
    addColumnIfMissing('household_members', $memberCols, 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

    $memberCols = getColumnsMap('household_members');
    if (isset($memberCols['date_of_birth'])) {
        $db->query("UPDATE household_members SET dob = date_of_birth WHERE (dob IS NULL OR dob = '0000-00-00') AND date_of_birth IS NOT NULL");
    }

    if (isset($memberCols['first_name']) && isset($memberCols['last_name'])) {
        $legacyRows = $db->fetchAll(
            "SELECT hm.id, hm.household_id, r.id AS resident_id, r.birth_date, r.gender, r.civil_status
             FROM household_members hm
             LEFT JOIN residents r
               ON LOWER(TRIM(r.first_name)) = LOWER(TRIM(hm.first_name))
              AND LOWER(TRIM(r.last_name)) = LOWER(TRIM(hm.last_name))
             WHERE hm.resident_id IS NULL OR hm.resident_id = 0"
        );

        foreach ($legacyRows as $legacy) {
            if (!empty($legacy['resident_id'])) {
                $db->query(
                    "UPDATE household_members
                     SET resident_id = ?,
                         dob = COALESCE(dob, ?),
                         gender = COALESCE(NULLIF(gender, ''), ?),
                         civil_status = COALESCE(NULLIF(civil_status, ''), ?)
                     WHERE id = ?",
                    [
                        (int)$legacy['resident_id'],
                        $legacy['birth_date'] ?: null,
                        $legacy['gender'] ?: 'other',
                        $legacy['civil_status'] ?: 'single',
                        (int)$legacy['id']
                    ]
                );
            }
        }
    }

    // Drop rows that still cannot be tied to a resident to enforce data ownership rules.
    $db->query("DELETE FROM household_members WHERE resident_id IS NULL OR resident_id = 0");

    $db->query("ALTER TABLE household_members MODIFY COLUMN resident_id INT(11) NOT NULL");

    $db->query("UPDATE household_members SET dob = '1990-01-01' WHERE dob IS NULL OR dob = '0000-00-00'");
    $db->query("UPDATE household_members SET gender = 'other' WHERE gender IS NULL OR gender = ''");
    $db->query("UPDATE household_members SET civil_status = 'single' WHERE civil_status IS NULL OR civil_status = ''");

    $db->query("ALTER TABLE household_members MODIFY COLUMN dob DATE NOT NULL");

    $residentIdx = $db->fetchOne("SHOW INDEX FROM household_members WHERE Key_name = 'idx_resident_id'");
    if (!$residentIdx) {
        $db->query("ALTER TABLE household_members ADD KEY idx_resident_id (resident_id)");
    }

    if (!empty($db->fetchOne("SHOW COLUMNS FROM residents LIKE 'household_id'"))) {
        $db->query(
            "UPDATE residents r
             JOIN household_members hm ON hm.resident_id = r.id
             SET r.household_id = hm.household_id
             WHERE r.household_id IS NULL OR r.household_id = 0"
        );
    }
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
    return $prefix . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
}

function &householdColumnsCacheStore() {
    static $cache = [];
    return $cache;
}

function clearColumnsMapCache($table = null) {
    $cache = &householdColumnsCacheStore();
    if ($table === null) {
        $cache = [];
        return;
    }
    unset($cache[$table]);
}

function getColumnsMap($table) {
    $cache = &householdColumnsCacheStore();
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $db = Database::getInstance();
    $rows = $db->fetchAll("SHOW COLUMNS FROM {$table}");
    $map = [];
    foreach ($rows as $row) {
        $map[$row['Field']] = $row;
    }
    $cache[$table] = $map;
    return $map;
}

function addColumnIfMissing($table, $map, $columnName, $definition) {
    $db = Database::getInstance();
    if (!isset($map[$columnName])) {
        $db->query("ALTER TABLE {$table} ADD COLUMN {$columnName} {$definition}");
        clearColumnsMapCache($table);
    }
}

function getResidentProfileForHousehold($residentId) {
    $db = Database::getInstance();
    $resident = $db->fetchOne(
        "SELECT id, first_name, middle_name, last_name, birth_date, gender, civil_status, household_id
         FROM residents WHERE id = ? LIMIT 1",
        [$residentId]
    );

    return $resident ?: null;
}

function getResidentHouseholdContext($residentId) {
    $db = Database::getInstance();
    $houseCols = getColumnsMap('households');
    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';

    $memberRow = $db->fetchOne(
        "SELECT hm.household_id, hm.id AS member_row_id, hm.relationship_to_head, h.`{$headColumn}` AS head_id
         FROM household_members hm
         INNER JOIN households h ON h.id = hm.household_id
         WHERE hm.resident_id = ?
         ORDER BY hm.id DESC
         LIMIT 1",
        [$residentId]
    );

    if ($memberRow) {
        $isDesignatedHead = (int)$memberRow['head_id'] === (int)$residentId;
        $relRaw = strtolower(trim((string)($memberRow['relationship_to_head'] ?? '')));
        $isHeadByRole = $relRaw !== '' && strpos($relRaw, 'head') !== false;
        $isHeadByFhc = false;
        if (columnExists($db, 'residents', 'family_head_code')) {
            $fhcRow = $db->fetchOne("SELECT family_head_code FROM residents WHERE id = ? LIMIT 1", [$residentId]);
            $fhc = trim((string)($fhcRow['family_head_code'] ?? ''));
            $isHeadByFhc = $fhc !== '' && $fhc !== '-';
        }
        $isHead = $isDesignatedHead || $isHeadByRole || $isHeadByFhc;
        return [
            'household_id' => (int)$memberRow['household_id'],
            'is_head' => $isHead,
            'member_row_id' => (int)$memberRow['member_row_id'],
            'relationship_to_head' => $memberRow['relationship_to_head']
        ];
    }

    $resident = getResidentProfileForHousehold($residentId);
    $residentHouseholdId = (int)($resident['household_id'] ?? 0);
    if ($residentHouseholdId > 0) {
        $headRow = $db->fetchOne("SELECT `{$headColumn}` AS head_id FROM households WHERE id = ?", [$residentHouseholdId]);
        if ($headRow) {
            $isDesignatedHead = (int)$headRow['head_id'] === (int)$residentId;
            $resSelect = columnExists($db, 'residents', 'family_head_code')
                ? 'relationship_to_head, family_head_code' : 'relationship_to_head';
            $residentRel = $db->fetchOne("SELECT {$resSelect} FROM residents WHERE id = ? LIMIT 1", [$residentId]);
            $relRaw = strtolower(trim((string)($residentRel['relationship_to_head'] ?? '')));
            $isHeadByRole = $relRaw !== '' && strpos($relRaw, 'head') !== false;
            $isHeadByFhc = false;
            if (columnExists($db, 'residents', 'family_head_code') && $residentRel) {
                $fhc = trim((string)($residentRel['family_head_code'] ?? ''));
                $isHeadByFhc = $fhc !== '' && $fhc !== '-';
            }
            $isHead = $isDesignatedHead || $isHeadByRole || $isHeadByFhc;
            return [
                'household_id' => $residentHouseholdId,
                'is_head' => $isHead,
                'member_row_id' => null,
                'relationship_to_head' => $isHead ? 'Head' : 'Member'
            ];
        }
    }

    return null;
}

function formatResidentName($row) {
    $parts = [];
    if (!empty($row['first_name'])) {
        $parts[] = trim($row['first_name']);
    }
    if (!empty($row['middle_name'])) {
        $parts[] = trim($row['middle_name']);
    }
    if (!empty($row['last_name'])) {
        $parts[] = trim($row['last_name']);
    }
    return trim(implode(' ', $parts));
}
