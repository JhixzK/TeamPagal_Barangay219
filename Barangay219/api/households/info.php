<?php
require_once __DIR__ . '/_common.php';

try {
    $residentId = requireResidentHouseholdSession();
    ensureResidentHouseholdSchema();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetHouseholdInfo($residentId);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handleSelectRole($residentId);
    }

    householdJsonResponse(false, null, 'Method not allowed', 405);
} catch (Exception $e) {
    error_log('Household info endpoint error: ' . $e->getMessage());
    householdJsonResponse(false, null, 'Unable to process household request', 500);
}

function handleGetHouseholdInfo($residentId) {
    $db = Database::getInstance();
    $context = getResidentHouseholdContext($residentId);

    $base = [
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
        "SELECT h.id, h.head_id, h.house_number, h.street, h.barangay, h.city, h.province,
                h.created_at, h.updated_at,
                r.first_name, r.middle_name, r.last_name
         FROM households h
         INNER JOIN residents r ON r.id = h.head_id
         WHERE h.id = ?
         LIMIT 1",
        [$householdId]
    );

    if (!$household) {
        $base['available_households'] = getAvailableHouseholdsForJoin();
        householdJsonResponse(true, $base, 'Household link is invalid. Please select role again.');
    }

    $members = $db->fetchAll(
        "SELECT hm.id, hm.household_id, hm.resident_id, hm.relationship_to_head, hm.dob,
                hm.gender, hm.civil_status, hm.created_at, hm.updated_at,
                r.first_name, r.middle_name, r.last_name
         FROM household_members hm
         INNER JOIN residents r ON r.id = hm.resident_id
         WHERE hm.household_id = ?
         ORDER BY CASE WHEN hm.resident_id = ? THEN 0 ELSE 1 END, hm.created_at ASC",
        [$householdId, $household['head_id']]
    );

    $mappedMembers = [];
    foreach ($members as $member) {
        $mappedMembers[] = [
            'id' => (int)$member['id'],
            'resident_id' => (int)$member['resident_id'],
            'name' => formatResidentName($member),
            'relationship_to_head' => $member['relationship_to_head'],
            'dob' => $member['dob'],
            'gender' => $member['gender'],
            'civil_status' => $member['civil_status'],
            'is_self' => (int)$member['resident_id'] === (int)$residentId
        ];
    }

    $payload = [
        'resident_id' => $residentId,
        'role' => $context['is_head'] ? 'head' : 'member',
        'can_manage_members' => (bool)$context['is_head'],
        'household' => [
            'id' => (int)$household['id'],
            'head_id' => (int)$household['head_id'],
            'head_name' => formatResidentName($household),
            'house_number' => $household['house_number'],
            'street' => $household['street'],
            'barangay' => $household['barangay'],
            'city' => $household['city'],
            'province' => $household['province'],
            'total_members' => count($mappedMembers),
            'created_at' => $household['created_at'],
            'updated_at' => $household['updated_at']
        ],
        'members' => $mappedMembers,
        'available_households' => []
    ];

    householdJsonResponse(true, $payload, 'Household information fetched');
}

function handleSelectRole($residentId) {
    $data = getRequestBodyData();
    $role = strtolower(sanitizeInput((string)($data['role'] ?? '')));

    if (!in_array($role, ['head', 'member'], true)) {
        householdJsonResponse(false, null, 'Role must be head or member', 400);
    }

    if ($role === 'head') {
        createHeadHousehold($residentId, $data);
        return;
    }

    joinAsMember($residentId, $data);
}

function createHeadHousehold($residentId, $data) {
    $houseNumber = sanitizeInput((string)($data['house_number'] ?? ''));
    $street = sanitizeInput((string)($data['street'] ?? ''));
    $barangay = sanitizeInput((string)($data['barangay'] ?? 'Barangay 219'));
    $city = sanitizeInput((string)($data['city'] ?? 'Manila'));
    $province = sanitizeInput((string)($data['province'] ?? 'Metro Manila'));

    if ($street === '' || $barangay === '' || $city === '' || $province === '') {
        householdJsonResponse(false, null, 'Address fields are required for head of household', 400);
    }

    $db = Database::getInstance();
    $resident = getResidentProfileForHousehold($residentId);
    if (!$resident) {
        householdJsonResponse(false, null, 'Resident not found', 404);
    }

    $db->beginTransaction();
    try {
        // Detach from any prior household membership before creating a new one.
        $db->query("DELETE FROM household_members WHERE resident_id = ?", [$residentId]);

        $db->query(
            "INSERT INTO households (head_id, house_number, street, barangay, city, province)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$residentId, $houseNumber ?: null, $street, $barangay, $city, $province]
        );
        $householdId = (int)$db->lastInsertId();

        $db->query(
            "INSERT INTO household_members (household_id, resident_id, relationship_to_head, dob, gender, civil_status)
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
        householdJsonResponse(true, ['household_id' => $householdId], 'Household created successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function joinAsMember($residentId, $data) {
    $householdId = (int)($data['household_id'] ?? 0);
    $relationship = sanitizeInput((string)($data['relationship_to_head'] ?? ''));

    if ($householdId <= 0 || $relationship === '') {
        householdJsonResponse(false, null, 'Household and relationship are required', 400);
    }

    $db = Database::getInstance();
    $household = $db->fetchOne("SELECT id, head_id FROM households WHERE id = ? LIMIT 1", [$householdId]);
    if (!$household) {
        householdJsonResponse(false, null, 'Selected household not found', 404);
    }

    if ((int)$household['head_id'] === (int)$residentId) {
        householdJsonResponse(false, null, 'You are the household head of this household', 400);
    }

    $resident = getResidentProfileForHousehold($residentId);
    if (!$resident) {
        householdJsonResponse(false, null, 'Resident not found', 404);
    }

    $existing = $db->fetchOne("SELECT id FROM household_members WHERE resident_id = ? LIMIT 1", [$residentId]);

    $db->beginTransaction();
    try {
        if ($existing) {
            $db->query(
                "UPDATE household_members
                 SET household_id = ?, relationship_to_head = ?, dob = ?, gender = ?, civil_status = ?, updated_at = CURRENT_TIMESTAMP
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
                "INSERT INTO household_members (household_id, resident_id, relationship_to_head, dob, gender, civil_status)
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

        householdJsonResponse(true, ['household_id' => $householdId], 'Joined household successfully');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function getAvailableHouseholdsForJoin() {
    $db = Database::getInstance();
    $rows = $db->fetchAll(
        "SELECT h.id, h.house_number, h.street, h.barangay, h.city, h.province,
                r.first_name, r.middle_name, r.last_name
         FROM households h
         INNER JOIN residents r ON r.id = h.head_id
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
            'head_name' => formatResidentName($row),
            'address' => implode(', ', $addressParts),
            'label' => 'HH-' . str_pad((string)$row['id'], 5, '0', STR_PAD_LEFT) . ' | ' . formatResidentName($row)
        ];
    }

    return $items;
}
