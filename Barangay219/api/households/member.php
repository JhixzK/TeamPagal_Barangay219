<?php
require_once __DIR__ . '/_common.php';

try {
    $residentId = requireResidentHouseholdSession();
    ensureResidentHouseholdSchema();
    ensureResidentMemberContractColumns();

    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'POST') {
        addHouseholdMember($residentId);
    }
    if ($method === 'PUT') {
        updateHouseholdMember($residentId);
    }
    if ($method === 'DELETE') {
        deleteHouseholdMember($residentId);
    }

    householdJsonResponse(false, null, 'Method not allowed', 405);
} catch (Exception $e) {
    error_log('Household member endpoint error: ' . $e->getMessage());
    householdJsonResponse(false, null, 'Unable to process household member request', 500);
}

function ensureResidentMemberContractColumns() {
    $db = Database::getInstance();
    $memberCols = getColumnsMap('household_members');
    addColumnIfMissing('household_members', $memberCols, 'date_of_birth', 'DATE NULL');
    $memberCols = getColumnsMap('household_members');
    if (isset($memberCols['dob']) && isset($memberCols['date_of_birth'])) {
        $db->query("UPDATE household_members SET date_of_birth = dob WHERE (date_of_birth IS NULL OR date_of_birth = '0000-00-00') AND dob IS NOT NULL");
    }
}

function householdMemberDateColumn() {
    $memberCols = getColumnsMap('household_members');
    return isset($memberCols['date_of_birth']) ? 'date_of_birth' : 'dob';
}

function addHouseholdMember($residentId) {
    $data = getRequestBodyData();

    $context = getResidentHouseholdContext($residentId);
    if (!$context || !$context['is_head']) {
        householdJsonResponse(false, null, 'Only household heads can add members', 403);
    }

    $householdId = (int)$context['household_id'];
    $targetResidentId = (int)($data['resident_id'] ?? 0);
    if ($targetResidentId <= 0) {
        $targetResidentId = (int)$residentId;
    }
    $relationship = sanitizeInput((string)($data['relationship_to_head'] ?? ''));
    $dob = sanitizeInput((string)(($data['date_of_birth'] ?? '') ?: ($data['dob'] ?? '')));
    $gender = strtolower(sanitizeInput((string)($data['gender'] ?? '')));
    $civilStatus = strtolower(sanitizeInput((string)($data['civil_status'] ?? 'single')));
    $dateColumn = householdMemberDateColumn();

    validateMemberFields($targetResidentId, $relationship, $dob, $gender, $civilStatus);

    if ($targetResidentId === $residentId) {
        householdJsonResponse(false, null, 'Head is already in household members list', 400);
    }

    $db = Database::getInstance();
    $targetResident = getResidentProfileForHousehold($targetResidentId);
    if (!$targetResident) {
        householdJsonResponse(false, null, 'Selected resident does not exist', 404);
    }

    $existing = $db->fetchOne("SELECT id, household_id FROM household_members WHERE resident_id = ? LIMIT 1", [$targetResidentId]);

    $db->beginTransaction();
    try {
        if ($existing && (int)$existing['household_id'] !== $householdId) {
            householdJsonResponse(false, null, 'Resident already belongs to another household', 409);
        }

        if ($existing) {
            $db->query(
                "UPDATE household_members
                 SET relationship_to_head = ?, {$dateColumn} = ?, gender = ?, civil_status = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?",
                [$relationship, $dob, $gender, $civilStatus, (int)$existing['id']]
            );
        } else {
            $db->query(
                "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$dateColumn}, gender, civil_status)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$householdId, $targetResidentId, $relationship, $dob, $gender, $civilStatus]
            );
        }

        $db->query("UPDATE residents SET household_id = ? WHERE id = ?", [$householdId, $targetResidentId]);
        $db->commit();

        householdJsonResponse(true, null, 'Household member saved');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function updateHouseholdMember($residentId) {
    $data = getRequestBodyData();
    $memberId = (int)(($data['member_id'] ?? 0) ?: ($data['id'] ?? 0));

    if ($memberId <= 0) {
        householdJsonResponse(false, null, 'Member id is required', 400);
    }

    $db = Database::getInstance();
    $context = getResidentHouseholdContext($residentId);
    if (!$context) {
        householdJsonResponse(false, null, 'No household assignment found', 403);
    }

    $row = $db->fetchOne(
        "SELECT hm.id, hm.household_id, hm.resident_id
         FROM household_members hm
         WHERE hm.id = ? LIMIT 1",
        [$memberId]
    );

    if (!$row) {
        householdJsonResponse(false, null, 'Household member not found', 404);
    }

    if ((int)$row['household_id'] !== (int)$context['household_id']) {
        householdJsonResponse(false, null, 'Access denied', 403);
    }

    $isHead = (bool)$context['is_head'];
    $isSelfRecord = (int)$row['resident_id'] === (int)$residentId;
    if (!$isHead && !$isSelfRecord) {
        householdJsonResponse(false, null, 'Members can only update their own profile', 403);
    }

    $relationship = sanitizeInput((string)($data['relationship_to_head'] ?? ''));
    $dob = sanitizeInput((string)(($data['date_of_birth'] ?? '') ?: ($data['dob'] ?? '')));
    $gender = strtolower(sanitizeInput((string)($data['gender'] ?? '')));
    $civilStatus = strtolower(sanitizeInput((string)($data['civil_status'] ?? 'single')));
    $dateColumn = householdMemberDateColumn();

    if (!$isHead) {
        // Members cannot change relationship; preserve existing hierarchy.
        $existing = $db->fetchOne("SELECT relationship_to_head FROM household_members WHERE id = ?", [$memberId]);
        $relationship = $existing['relationship_to_head'] ?? 'Member';
    }

    validateMemberFields((int)$row['resident_id'], $relationship, $dob, $gender, $civilStatus);

    $db->query(
        "UPDATE household_members
         SET relationship_to_head = ?, {$dateColumn} = ?, gender = ?, civil_status = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ?",
        [$relationship, $dob, $gender, $civilStatus, $memberId]
    );

    householdJsonResponse(true, null, 'Household member updated');
}

function deleteHouseholdMember($residentId) {
    $data = getRequestBodyData();
    $memberId = (int)(($data['member_id'] ?? 0) ?: ($data['id'] ?? 0));
    if ($memberId <= 0) {
        householdJsonResponse(false, null, 'Member id is required', 400);
    }

    $context = getResidentHouseholdContext($residentId);
    if (!$context || !$context['is_head']) {
        householdJsonResponse(false, null, 'Only household heads can remove members', 403);
    }

    $db = Database::getInstance();
    $houseCols = getColumnsMap('households');
    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';
    $row = $db->fetchOne(
        "SELECT hm.id, hm.household_id, hm.resident_id, h.`{$headColumn}` AS family_head_id
         FROM household_members hm
         INNER JOIN households h ON h.id = hm.household_id
         WHERE hm.id = ? LIMIT 1",
        [$memberId]
    );

    if (!$row) {
        householdJsonResponse(false, null, 'Household member not found', 404);
    }

    if ((int)$row['household_id'] !== (int)$context['household_id']) {
        householdJsonResponse(false, null, 'Access denied', 403);
    }

    if ((int)$row['resident_id'] === (int)$row['family_head_id']) {
        householdJsonResponse(false, null, 'Household head cannot be removed', 400);
    }

    $db->beginTransaction();
    try {
        $db->query("DELETE FROM household_members WHERE id = ?", [$memberId]);
        $db->query("UPDATE residents SET household_id = NULL WHERE id = ?", [(int)$row['resident_id']]);
        $db->commit();

        householdJsonResponse(true, null, 'Household member removed');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function validateMemberFields($residentId, $relationship, $dob, $gender, $civilStatus) {
    if ($residentId <= 0) {
        householdJsonResponse(false, null, 'Valid resident id is required', 400);
    }

    if ($relationship === '') {
        householdJsonResponse(false, null, 'Relationship to head is required', 400);
    }

    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $dob) || strtotime($dob) === false) {
        householdJsonResponse(false, null, 'Valid DOB is required', 400);
    }

    if (strtotime($dob) > time()) {
        householdJsonResponse(false, null, 'DOB cannot be in the future', 400);
    }

    $allowedGender = ['male', 'female', 'other'];
    if (!in_array($gender, $allowedGender, true)) {
        householdJsonResponse(false, null, 'Gender must be male, female, or other', 400);
    }

    $allowedCivil = ['single', 'married', 'widowed', 'divorced', 'separated'];
    if (!in_array($civilStatus, $allowedCivil, true)) {
        householdJsonResponse(false, null, 'Invalid civil status', 400);
    }
}
