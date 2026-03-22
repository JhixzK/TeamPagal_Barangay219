<?php
require_once __DIR__ . '/_common.php';

try {
    $residentId = requireResidentHouseholdSession();
    ensureResidentHouseholdSchema();
    require_once __DIR__ . '/info.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        householdJsonResponse(false, null, 'Method not allowed', 405);
    }

    $data = getRequestBodyData();
    createDependentMember($residentId, $data);
} catch (Exception $e) {
    error_log('Household dependent endpoint error: ' . $e->getMessage());
    householdJsonResponse(false, null, 'Unable to process dependent request', 500);
}

function createDependentMember($residentId, $data) {
    $context = getResidentHouseholdContext($residentId);
    if (!$context || !$context['is_head']) {
        householdJsonResponse(false, null, 'Only household heads can add family members', 403);
    }

    $householdId = (int)$context['household_id'];
    if ($householdId <= 0) {
        householdJsonResponse(false, null, 'No household assignment found', 400);
    }

    $first = sanitizeInput((string)($data['first_name'] ?? ''));
    $middle = sanitizeInput((string)($data['middle_name'] ?? ''));
    $last = sanitizeInput((string)($data['last_name'] ?? ''));
    $suffix = sanitizeInput((string)($data['suffix'] ?? ''));
    $birthDate = sanitizeInput((string)($data['birth_date'] ?? $data['date_of_birth'] ?? ''));
    $gender = strtolower(sanitizeInput((string)($data['gender'] ?? '')));
    $civil = strtolower(sanitizeInput((string)($data['civil_status'] ?? 'single')));
    $relationship = sanitizeInput((string)($data['relationship_to_head'] ?? 'Member'));

    if ($first === '' || $last === '' || $birthDate === '' || $gender === '') {
        householdJsonResponse(false, null, 'First name, last name, birth date, and gender are required', 400);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate) || strtotime($birthDate) === false) {
        householdJsonResponse(false, null, 'Valid birth date is required', 400);
    }
    if (strtotime($birthDate) > time()) {
        householdJsonResponse(false, null, 'Birth date cannot be in the future', 400);
    }
    $allowedGender = ['male', 'female', 'other'];
    if (!in_array($gender, $allowedGender, true)) {
        householdJsonResponse(false, null, 'Gender must be male, female, or other', 400);
    }
    $allowedCivil = ['single', 'married', 'widowed', 'divorced', 'separated'];
    if (!in_array($civil, $allowedCivil, true)) {
        $civil = 'single';
    }
    if ($relationship === '') {
        householdJsonResponse(false, null, 'Relationship is required', 400);
    }

    $db = Database::getInstance();
    $house = $db->fetchOne("SELECT * FROM households WHERE id = ? LIMIT 1", [$householdId]);
    if (!$house) {
        householdJsonResponse(false, null, 'Household not found', 404);
    }

    $address = householdAddressLabel($house);
    if (trim($address) === '') {
        $address = 'Barangay 219, Manila';
    }

    // Create resident record without creating a login account.
    $resCols = getColumnsMap('residents');
    $insertCols = [];
    $vals = [];

    $set = function ($col, $val) use (&$insertCols, &$vals, $resCols) {
        if (!isset($resCols[$col])) return;
        $insertCols[] = $col;
        $vals[] = $val;
    };

    $set('first_name', $first);
    $set('middle_name', $middle !== '' ? $middle : null);
    $set('last_name', $last);
    $set('suffix', $suffix !== '' ? $suffix : null);
    $set('birth_date', $birthDate);
    $set('gender', $gender);
    if (isset($resCols['civil_status'])) $set('civil_status', $civil);
    $set('citizenship', 'Filipino');
    $set('address', $address);
    if (isset($resCols['household_id'])) $set('household_id', $householdId);
    if (isset($resCols['status'])) $set('status', 'active');
    if (isset($resCols['record_status'])) $set('record_status', 'active');
    // Do not assign BR219 resident_code here — official registration / applications issue IDs.
    if (isset($resCols['relationship_to_head'])) {
        $set('relationship_to_head', $relationship);
    }
    $headId = (int)($house['family_head_id'] ?? 0);
    if ($headId > 0 && isset($resCols['family_code'])) {
        $fc = '';
        $hrow = $db->fetchOne('SELECT family_code FROM residents WHERE id = ? LIMIT 1', [$headId]);
        $fc = trim((string)($hrow['family_code'] ?? ''));
        if ($fc === '' && isset($house['family_code'])) {
            $fc = trim((string)$house['family_code']);
        }
        if ($fc !== '') {
            $set('family_code', $fc);
        }
    }
    if ($headId > 0 && isset($resCols['family_head_resident_id'])) {
        $set('family_head_resident_id', $headId);
    }

    if (empty($insertCols)) {
        householdJsonResponse(false, null, 'Residents table is not compatible', 500);
    }

    $colSql = '`' . implode('`,`', $insertCols) . '`';
    $placeholders = implode(', ', array_fill(0, count($vals), '?'));

    $dateColumn = householdMemberDateColumn();

    $db->beginTransaction();
    try {
        $db->query("INSERT INTO residents ({$colSql}) VALUES ({$placeholders})", $vals);
        $newResidentId = (int)$db->lastInsertId();

        $db->query(
            "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$dateColumn}, gender, civil_status)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$householdId, $newResidentId, $relationship, $birthDate, $gender, $civil,]
        );

        if (isset($resCols['household_id'])) {
            $db->query('UPDATE residents SET household_id = ? WHERE id = ?', [$householdId, $newResidentId]);
        }

        logHouseholdHistory($householdId, 'Member Added', 'Family head added a dependent member (no login)', $newResidentId);
        syncHouseholdTotalMembersFromResidents($db, $householdId);
        $db->commit();

        householdJsonResponse(true, ['resident_id' => $newResidentId], 'Family member added');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
