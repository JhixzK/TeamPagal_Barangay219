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
        logMemberHistory($householdId, 'Member Added', 'Member linked to household', $targetResidentId);
        $db->commit();

        householdJsonResponse(true, null, 'Household member saved');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function updateHouseholdMember($residentId) {
    $data = getRequestBodyData();
    $action = strtolower(sanitizeInput((string)($data['action'] ?? '')));

    if ($action === 'assign_head') {
        assignHouseholdHead($residentId, $data);
        return;
    }

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

    logMemberHistory((int)$row['household_id'], 'Member Updated', 'Member profile updated', (int)$row['resident_id']);

    householdJsonResponse(true, null, 'Household member updated');
}

function deleteHouseholdMember($residentId) {
    $data = getRequestBodyData();
    $memberId = (int)(($data['member_id'] ?? 0) ?: ($data['id'] ?? 0));
    $targetResidentId = (int)($data['resident_id'] ?? 0);
    if ($memberId <= 0 && $targetResidentId <= 0) {
        householdJsonResponse(false, null, 'member_id or resident_id is required', 400);
    }

    $context = getResidentHouseholdContext($residentId);
    if (!$context || !$context['is_head']) {
        householdJsonResponse(false, null, 'Only household heads can remove members', 403);
    }

    $db = Database::getInstance();
    $houseCols = getColumnsMap('households');
    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';
    $row = null;
    if ($memberId > 0) {
        $row = $db->fetchOne(
            "SELECT hm.id, hm.household_id, hm.resident_id, h.`{$headColumn}` AS family_head_id
             FROM household_members hm
             INNER JOIN households h ON h.id = hm.household_id
             WHERE hm.id = ? LIMIT 1",
            [$memberId]
        );
    } else {
        $row = $db->fetchOne(
            "SELECT hm.id, hm.household_id, hm.resident_id, h.`{$headColumn}` AS family_head_id
             FROM household_members hm
             INNER JOIN households h ON h.id = hm.household_id
             WHERE hm.resident_id = ? AND hm.household_id = ?
             ORDER BY hm.id DESC
             LIMIT 1",
            [$targetResidentId, (int)$context['household_id']]
        );
        if (!$row) {
            $residentRow = $db->fetchOne("SELECT id, household_id FROM residents WHERE id = ? LIMIT 1", [$targetResidentId]);
            if (!$residentRow || (int)$residentRow['household_id'] !== (int)$context['household_id']) {
                householdJsonResponse(false, null, 'Household member not found', 404);
            }
            $hidRow = $db->fetchOne("SELECT `{$headColumn}` AS hid FROM households WHERE id = ? LIMIT 1", [(int)$residentRow['household_id']]);
            $row = [
                'id' => 0,
                'household_id' => (int)$residentRow['household_id'],
                'resident_id' => (int)$residentRow['id'],
                'family_head_id' => (int)($hidRow['hid'] ?? 0)
            ];
        }
    }

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
        if ((int)$row['id'] > 0) {
            $db->query("DELETE FROM household_members WHERE id = ?", [(int)$row['id']]);
        }
        $db->query("UPDATE residents SET household_id = NULL WHERE id = ?", [(int)$row['resident_id']]);
        logMemberHistory((int)$row['household_id'], 'Member Removed', 'Member removed from household', (int)$row['resident_id']);
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

    $allowedRelationship = ['Head', 'Member', 'Spouse', 'Son', 'Daughter', 'Parent', 'Sibling', 'Relative', 'Boarder'];
    if (!in_array($relationship, $allowedRelationship, true)) {
        householdJsonResponse(false, null, 'Invalid relationship value', 400);
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

function assignHouseholdHead($residentId, $data) {
    $memberId = (int)(($data['member_id'] ?? 0) ?: ($data['id'] ?? 0));
    $targetResidentId = (int)($data['resident_id'] ?? 0);
    if ($memberId <= 0 && $targetResidentId <= 0) {
        householdJsonResponse(false, null, 'member_id or resident_id is required', 400);
    }

    $reason = sanitizeInput((string)($data['reason'] ?? ''));
    if (trim($reason) === '') {
        householdJsonResponse(false, null, 'Reason is required to transfer head role', 400);
    }
    if (strlen($reason) > 200) {
        householdJsonResponse(false, null, 'Reason is too long', 400);
    }

    $context = getResidentHouseholdContext($residentId);
    if (!$context || !$context['is_head']) {
        householdJsonResponse(false, null, 'Only current household head can assign new head', 403);
    }

    $db = Database::getInstance();
    $houseCols = getColumnsMap('households');
    $headColumn = isset($houseCols['family_head_id']) ? 'family_head_id' : 'head_id';

    $target = null;
    if ($memberId > 0) {
        $target = $db->fetchOne(
            'SELECT id, household_id, resident_id, relationship_to_head FROM household_members WHERE id = ? LIMIT 1',
            [$memberId]
        );
    } else {
        $target = $db->fetchOne(
            'SELECT id, household_id, resident_id, relationship_to_head FROM household_members WHERE resident_id = ? AND household_id = ? ORDER BY id DESC LIMIT 1',
            [$targetResidentId, (int)$context['household_id']]
        );
        if (!$target) {
            $dateColumn = householdMemberDateColumn();
            $profile = getResidentProfileForHousehold($targetResidentId);
            if (!$profile) {
                householdJsonResponse(false, null, 'Selected resident does not exist', 404);
            }
            $db->query(
                "INSERT INTO household_members (household_id, resident_id, relationship_to_head, {$dateColumn}, gender, civil_status)
                 VALUES (?, ?, 'Member', ?, ?, ?)",
                [
                    (int)$context['household_id'],
                    $targetResidentId,
                    $profile['birth_date'] ?: '1990-01-01',
                    strtolower((string)($profile['gender'] ?: 'other')),
                    strtolower((string)($profile['civil_status'] ?: 'single'))
                ]
            );
            $target = $db->fetchOne(
                'SELECT id, household_id, resident_id, relationship_to_head FROM household_members WHERE id = ? LIMIT 1',
                [(int)$db->lastInsertId()]
            );
        }
    }

    if (!$target) {
        householdJsonResponse(false, null, 'Member not found', 404);
    }

    if ((int)$target['household_id'] !== (int)$context['household_id']) {
        householdJsonResponse(false, null, 'Access denied', 403);
    }

    $householdId = (int)$target['household_id'];
    $newHeadResidentId = (int)$target['resident_id'];
    $oldHeadResidentId = (int)$residentId;

    if (columnExists($db, 'residents', 'family_code')) {
        $oldHeadFc = $db->fetchOne("SELECT family_code FROM residents WHERE id = ?", [$oldHeadResidentId]);
        $newHeadFc = $db->fetchOne("SELECT family_code FROM residents WHERE id = ?", [$newHeadResidentId]);
        $oldFc = trim((string)($oldHeadFc['family_code'] ?? ''));
        $newFc = trim((string)($newHeadFc['family_code'] ?? ''));
        if ($oldFc !== '' && $newFc !== '' && $oldFc !== $newFc) {
            householdJsonResponse(false, null, 'You can only transfer head role to members in your family group', 400);
        }
    }

    $currentDesignated = $db->fetchOne("SELECT `{$headColumn}` AS hid FROM households WHERE id = ? LIMIT 1", [$householdId]);
    $currentDesignatedId = (int)($currentDesignated['hid'] ?? 0);

    $db->beginTransaction();
    try {
        if ($currentDesignatedId === $oldHeadResidentId) {
            $db->query("UPDATE households SET {$headColumn} = ? WHERE id = ?", [$newHeadResidentId, $householdId]);
            if (isset($houseCols['family_head_id']) && $headColumn !== 'family_head_id') {
                $db->query('UPDATE households SET family_head_id = ? WHERE id = ?', [$newHeadResidentId, $householdId]);
            }
            if (isset($houseCols['head_id']) && $headColumn !== 'head_id') {
                $db->query('UPDATE households SET head_id = ? WHERE id = ?', [$newHeadResidentId, $householdId]);
            }
        }

        $db->query('UPDATE household_members SET relationship_to_head = ? WHERE resident_id = ? AND household_id = ?', ['Head', $newHeadResidentId, $householdId]);
        $db->query('UPDATE household_members SET relationship_to_head = ? WHERE resident_id = ? AND household_id = ?', ['Member', $oldHeadResidentId, $householdId]);

        if (columnExists($db, 'residents', 'relationship_to_head')) {
            $db->query('UPDATE residents SET relationship_to_head = ? WHERE id = ?', ['Head', $newHeadResidentId]);
            $db->query('UPDATE residents SET relationship_to_head = ? WHERE id = ?', ['Member', $oldHeadResidentId]);
        }

        // Ensure the new designated head always has a family_head_code; restore from old head or generate.
        if (columnExists($db, 'residents', 'family_head_code')) {
            $oldFhc = '';
            $oldHead = $db->fetchOne('SELECT family_head_code FROM residents WHERE id = ? LIMIT 1', [$oldHeadResidentId]);
            $oldFhc = $oldHead ? trim((string)($oldHead['family_head_code'] ?? '')) : '';
            if (($oldFhc === '' || $oldFhc === '-') && $currentDesignatedId === $oldHeadResidentId && isset($houseCols['family_head_code'])) {
                $hh = $db->fetchOne('SELECT family_head_code FROM households WHERE id = ? LIMIT 1', [$householdId]);
                $oldFhc = $hh ? trim((string)($hh['family_head_code'] ?? '')) : '';
            }
            if ($oldFhc === '' || $oldFhc === '-') {
                $oldFhc = generateResidentFamilyHeadCode($db);
            }
            $db->query('UPDATE residents SET family_head_code = ? WHERE id = ?', [$oldFhc, $newHeadResidentId]);
            $db->query('UPDATE residents SET family_head_code = NULL WHERE id = ?', [$oldHeadResidentId]);
            if ($currentDesignatedId === $oldHeadResidentId && isset($houseCols['family_head_code'])) {
                $db->query('UPDATE households SET family_head_code = ? WHERE id = ?', [$oldFhc, $householdId]);
            }
        }

        logMemberHistory($householdId, 'Head Changed', 'Household head reassigned. Reason: ' . $reason, $newHeadResidentId);

        $db->commit();
        householdJsonResponse(true, ['new_head_resident_id' => $newHeadResidentId], 'Household head updated');
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function logMemberHistory($householdId, $action, $details = null, $targetResidentId = null) {
    if (!$householdId || !$action) {
        return;
    }

    try {
        $db = Database::getInstance();
        $performedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $db->query(
            'INSERT INTO household_history_logs (household_id, action, performed_by, target_resident_id, details) VALUES (?, ?, ?, ?, ?)',
            [(int)$householdId, (string)$action, $performedBy, $targetResidentId ? (int)$targetResidentId : null, $details]
        );
    } catch (Exception $e) {
        error_log('Member history log error: ' . $e->getMessage());
    }
}
