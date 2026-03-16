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
        handleSelectRole($residentId, getRequestBodyData());
    }

    householdJsonResponse(false, null, 'Method not allowed', 405);
} catch (Exception $e) {
    error_log('Household info endpoint error: ' . $e->getMessage());
    householdJsonResponse(false, null, 'Unable to process household request', 500);
}

function ensureResidentHouseholdContractColumns() {
    $db = Database::getInstance();

    $houseCols = getColumnsMap('households');
    addColumnIfMissing('households', $houseCols, 'family_head_id', 'INT(11) NULL');
    addColumnIfMissing('households', $houseCols, 'family_code', 'VARCHAR(30) NULL');
    $houseCols = getColumnsMap('households');

    if (isset($houseCols['head_id']) && isset($houseCols['family_head_id'])) {
        $db->query('UPDATE households SET family_head_id = head_id WHERE (family_head_id IS NULL OR family_head_id = 0) AND head_id IS NOT NULL');
        $db->query('UPDATE households SET head_id = family_head_id WHERE (head_id IS NULL OR head_id = 0) AND family_head_id IS NOT NULL');
    }

    $familyCodeIdx = $db->fetchOne("SHOW INDEX FROM households WHERE Key_name = 'idx_households_family_code'");
    if (!$familyCodeIdx) {
        $db->query('ALTER TABLE households ADD KEY idx_households_family_code (family_code)');
    }

    $memberCols = getColumnsMap('household_members');
    addColumnIfMissing('household_members', $memberCols, 'date_of_birth', 'DATE NULL');
    $memberCols = getColumnsMap('household_members');

    if (isset($memberCols['dob']) && isset($memberCols['date_of_birth'])) {
        $db->query("UPDATE household_members SET date_of_birth = dob WHERE (date_of_birth IS NULL OR date_of_birth = '0000-00-00') AND dob IS NOT NULL");
    }
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

function generateFamilyCode() {
    $db = Database::getInstance();
    $year = date('Y');

    for ($i = 0; $i < 20; $i++) {
        $suffix = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $familyCode = 'BR219-' . $year . '-' . $suffix;
        $exists = $db->fetchOne('SELECT id FROM households WHERE family_code = ? LIMIT 1', [$familyCode]);
        if (!$exists) {
            return $familyCode;
        }
    }

    throw new Exception('Unable to generate unique family code');
}

function handleGetHouseholdInfo($residentId) {
    $db = Database::getInstance();
    $context = getResidentHouseholdContext($residentId);
    $contextPayload = buildResidentContext($residentId, $context);

    $houseCols = getColumnsMap('households');
    $memberCols = getColumnsMap('household_members');

    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';
    $dateColumn = isset($memberCols['date_of_birth']) ? 'date_of_birth' : 'dob';

    $base = [
        'context' => $contextPayload,
        'resident_id' => $residentId,
        'role' => null,
        'can_manage_members' => false,
        'household' => null,
        'members' => [],
        'available_households' => []
    ];

    if (!$context) {
        $base['available_households'] = getAvailableHouseholdsForJoin();
        householdJsonResponse(true, $base, 'No household assigned yet');
    }

    $householdId = (int)$context['household_id'];
    $household = $db->fetchOne(
        "SELECT h.id, h.family_code, h.`{$headColumn}` AS family_head_id, h.house_number, h.street, h.barangay, h.city, h.province,
                h.created_at, h.updated_at,
                r.first_name, r.middle_name, r.last_name
         FROM households h
         INNER JOIN residents r ON r.id = h.`{$headColumn}`
         WHERE h.id = ?
         LIMIT 1",
        [$householdId]
    );

    if (!$household) {
        $base['available_households'] = getAvailableHouseholdsForJoin();
        householdJsonResponse(true, $base, 'Household link is invalid. Please select role again.');
    }

    $members = $db->fetchAll(
        "SELECT hm.id, hm.household_id, hm.resident_id, hm.relationship_to_head, hm.`{$dateColumn}` AS date_of_birth,
                hm.gender, hm.civil_status, hm.created_at, hm.updated_at,
                r.first_name, r.middle_name, r.last_name
         FROM household_members hm
         INNER JOIN residents r ON r.id = hm.resident_id
         WHERE hm.household_id = ?
         ORDER BY CASE WHEN hm.resident_id = ? THEN 0 ELSE 1 END, hm.created_at ASC",
        [$householdId, $household['family_head_id']]
    );

    $mappedMembers = [];
    foreach ($members as $member) {
        $memberName = formatResidentName($member);
        $mappedMembers[] = [
            'id' => (int)$member['id'],
            'resident_id' => (int)$member['resident_id'],
            'name' => $memberName,
            'resident_name' => $memberName,
            'relationship_to_head' => $member['relationship_to_head'],
            'date_of_birth' => $member['date_of_birth'],
            'dob' => $member['date_of_birth'],
            'gender' => $member['gender'],
            'civil_status' => $member['civil_status'],
            'status' => 'Active',
            'is_self' => (int)$member['resident_id'] === (int)$residentId
        ];
    }

    // Backward-compatible fallback: if membership rows are missing, derive members
    // from residents linked by household_id so totals and list stay accurate.
    if (count($mappedMembers) === 0) {
        $linkedResidents = $db->fetchAll(
            "SELECT id AS resident_id, first_name, middle_name, last_name, birth_date, gender, civil_status
             FROM residents
             WHERE household_id = ?
             ORDER BY CASE WHEN id = ? THEN 0 ELSE 1 END, id ASC",
            [$householdId, $household['family_head_id']]
        );

        foreach ($linkedResidents as $residentRow) {
            $memberName = formatResidentName($residentRow);
            $isHeadResident = (int)$residentRow['resident_id'] === (int)$household['family_head_id'];
            $mappedMembers[] = [
                'id' => 0,
                'resident_id' => (int)$residentRow['resident_id'],
                'name' => $memberName,
                'resident_name' => $memberName,
                'relationship_to_head' => $isHeadResident ? 'Head' : 'Member',
                'date_of_birth' => $residentRow['birth_date'] ?? null,
                'dob' => $residentRow['birth_date'] ?? null,
                'gender' => $residentRow['gender'] ?? '',
                'civil_status' => $residentRow['civil_status'] ?? '',
                'status' => 'Active',
                'is_self' => (int)$residentRow['resident_id'] === (int)$residentId,
                'readonly' => true
            ];
        }
    }

    if (count($mappedMembers) === 0) {
        $headName = formatResidentName($household);
        $mappedMembers[] = [
            'id' => 0,
            'resident_id' => (int)$household['family_head_id'],
            'name' => $headName,
            'resident_name' => $headName,
            'relationship_to_head' => 'Head',
            'date_of_birth' => null,
            'dob' => null,
            'gender' => '',
            'civil_status' => '',
            'status' => 'Active',
            'is_self' => (int)$household['family_head_id'] === (int)$residentId,
            'readonly' => true
        ];
    }

    $computedTotalMembers = count($mappedMembers);

    $payload = [
        'context' => $contextPayload,
        'resident_id' => $residentId,
        'role' => $context['is_head'] ? 'head' : 'member',
        'can_manage_members' => (bool)$context['is_head'],
        'household' => [
            'id' => (int)$household['id'],
            'family_head_id' => (int)$household['family_head_id'],
            'head_id' => (int)$household['family_head_id'],
            'family_code' => (string)($household['family_code'] ?? ''),
            'head_name' => formatResidentName($household),
            'house_number' => $household['house_number'],
            'street' => $household['street'],
            'barangay' => $household['barangay'],
            'city' => $household['city'],
            'province' => $household['province'],
            'total_members' => $computedTotalMembers,
            'created_at' => $household['created_at'],
            'updated_at' => $household['updated_at']
        ],
        'members' => $mappedMembers,
        'available_households' => []
    ];

    householdJsonResponse(true, $payload, 'Household information fetched');
}

function handleSelectRole($residentId, $data) {
    $action = strtolower(sanitizeInput((string)($data['action'] ?? '')));
    if ($action === 'create_household') {
        createHeadHousehold($residentId, $data);
        return;
    }
    if ($action === 'join_household') {
        joinAsMember($residentId, $data);
        return;
    }

    $role = strtolower(sanitizeInput((string)($data['role'] ?? '')));

    if (!in_array($role, ['head', 'member'], true)) {
        householdJsonResponse(false, null, 'Action must be create_household or join_household', 400);
    }

    if ($role === 'head') {
        createHeadHousehold($residentId, $data);
        return;
    }

    joinAsMember($residentId, $data);
}

function createHeadHousehold($residentId, $data) {
    $houseNumber = sanitizeInput((string)($data['house_number'] ?? ''));
    $address = sanitizeInput((string)($data['address'] ?? ''));
    $street = sanitizeInput((string)($data['street'] ?? ''));
    $barangay = sanitizeInput((string)($data['barangay'] ?? 'Barangay 219'));
    $city = sanitizeInput((string)($data['city'] ?? 'Manila'));
    $province = sanitizeInput((string)($data['province'] ?? 'Metro Manila'));

    if ($street === '' && $address !== '') {
        $street = $address;
    }

    if ($street === '' || $barangay === '' || $city === '' || $province === '') {
        householdJsonResponse(false, null, 'Address fields are required for head of household', 400);
    }

    $db = Database::getInstance();
    $houseCols = getColumnsMap('households');
    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';
    $familyCode = generateFamilyCode();
    $resident = getResidentProfileForHousehold($residentId);
    if (!$resident) {
        householdJsonResponse(false, null, 'Resident not found', 404);
    }

    $db->beginTransaction();
    try {
        // Detach from any prior household membership before creating a new one.
        $db->query("DELETE FROM household_members WHERE resident_id = ?", [$residentId]);

        $insertCols = [$headColumn, 'family_code', 'house_number', 'street', 'barangay', 'city', 'province'];
        $insertVals = [$residentId, $familyCode, $houseNumber ?: null, $street, $barangay, $city, $province];
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

        $memberCols = getColumnsMap('household_members');
        $memberDateColumn = isset($memberCols['date_of_birth']) ? 'date_of_birth' : 'dob';

        $db->query(
            "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$memberDateColumn}, gender, civil_status)
             VALUES (?, ?, 'Head', ?, ?, ?)",
            [
                $householdId,
                $residentId,
                $resident['birth_date'] ?: '1990-01-01',
                $resident['gender'] ?: 'other',
                $resident['civil_status'] ?: 'single'
            ]
        );

        $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $residentId]);

        $db->commit();
        householdJsonResponse(true, ['household_id' => $householdId, 'family_code' => $familyCode], 'Household created successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function joinAsMember($residentId, $data) {
    $familyCode = strtoupper(trim((string)($data['family_code'] ?? '')));
    $relationship = sanitizeInput((string)($data['relationship_to_head'] ?? ''));

    if ($familyCode === '' || $relationship === '') {
        householdJsonResponse(false, null, 'Family code and relationship are required', 400);
    }

    $db = Database::getInstance();
    $houseCols = getColumnsMap('households');
    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';
    $household = $db->fetchOne("SELECT id, family_code, `{$headColumn}` AS family_head_id FROM households WHERE family_code = ? LIMIT 1", [$familyCode]);
    if (!$household) {
        householdJsonResponse(false, null, 'Invalid family code', 404);
    }

    $householdId = (int)$household['id'];

    if ((int)$household['family_head_id'] === (int)$residentId) {
        householdJsonResponse(false, null, 'You are the household head of this household', 400);
    }

    $resident = getResidentProfileForHousehold($residentId);
    if (!$resident) {
        householdJsonResponse(false, null, 'Resident not found', 404);
    }

    $existing = $db->fetchOne("SELECT id FROM household_members WHERE resident_id = ? LIMIT 1", [$residentId]);
    $memberCols = getColumnsMap('household_members');
    $memberDateColumn = isset($memberCols['date_of_birth']) ? 'date_of_birth' : 'dob';

    $db->beginTransaction();
    try {
        if ($existing) {
            $db->query(
                "UPDATE household_members
                 SET household_id = ?, relationship_to_head = ?, {$memberDateColumn} = ?, gender = ?, civil_status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE resident_id = ?",
                [
                    $householdId,
                    $relationship,
                    $resident['birth_date'] ?: '1990-01-01',
                    $resident['gender'] ?: 'other',
                    $resident['civil_status'] ?: 'single',
                    $residentId
                ]
            );
        } else {
            $db->query(
                "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$memberDateColumn}, gender, civil_status)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $householdId,
                    $residentId,
                    $relationship,
                    $resident['birth_date'] ?: '1990-01-01',
                    $resident['gender'] ?: 'other',
                    $resident['civil_status'] ?: 'single'
                ]
            );
        }

        $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $residentId]);
        $db->commit();

        householdJsonResponse(true, ['household_id' => $householdId, 'family_code' => $familyCode], 'Joined household successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function getAvailableHouseholdsForJoin() {
    $db = Database::getInstance();
    $houseCols = getColumnsMap('households');
    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';
    $rows = $db->fetchAll(
        "SELECT h.id, h.family_code, h.house_number, h.street, h.barangay, h.city, h.province,
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
