<?php
/**
 * Head-transfer guard: residents.family_code can diverge for co-heads / legacy rows
 * even when everyone is in the same household group.
 */
if (!function_exists('householdHeadTransferSameFamilyGroup')) {
    function householdHeadTransferSameFamilyGroup($db, $householdId, $oldHeadResidentId, $newHeadResidentId, $designatedHeadId) {
        $hasCol = static function ($table, $name) use ($db) {
            $row = $db->fetchOne(
                'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $name]
            );

            return !empty($row) && (int) $row['c'] > 0;
        };

        if (!$hasCol('residents', 'family_code')) {
            return true;
        }

        $selNew = 'family_code';
        if ($hasCol('residents', 'family_head_resident_id')) {
            $selNew .= ', family_head_resident_id';
        }
        $oldRow = $db->fetchOne("SELECT {$selNew} FROM residents WHERE id = ? LIMIT 1", [$oldHeadResidentId]);
        $newRow = $db->fetchOne("SELECT {$selNew} FROM residents WHERE id = ? LIMIT 1", [$newHeadResidentId]);
        if (!$oldRow || !$newRow) {
            return false;
        }

        $oldFc = trim((string) ($oldRow['family_code'] ?? ''));
        $newFc = trim((string) ($newRow['family_code'] ?? ''));
        if ($oldFc === '' || $newFc === '' || strcasecmp($oldFc, $newFc) === 0) {
            return true;
        }
        if ($oldHeadResidentId === $designatedHeadId) {
            return true;
        }
        if ($hasCol('residents', 'family_head_resident_id')) {
            $ref = (int) ($newRow['family_head_resident_id'] ?? 0);
            if ($ref === $oldHeadResidentId) {
                return true;
            }
        }

        $hFc = '';
        if ($hasCol('households', 'family_code')) {
            $hh = $db->fetchOne('SELECT family_code FROM households WHERE id = ? LIMIT 1', [$householdId]);
            $hFc = trim((string) ($hh['family_code'] ?? ''));
        }
        if ($hFc !== '') {
            $newMatchesH = ($newFc === '' || strcasecmp($newFc, $hFc) === 0);
            $oldMatchesH = ($oldFc === '' || strcasecmp($oldFc, $hFc) === 0);
            if ($newMatchesH && $oldMatchesH) {
                return true;
            }
            $oldIsHead = $db->fetchOne(
                "SELECT id FROM residents WHERE id = ? AND household_id = ? AND (
                    LOWER(TRIM(COALESCE(relationship_to_head,''))) LIKE '%head%'
                    OR LOWER(TRIM(COALESCE(household_role,''))) IN ('head','head of household')
                ) LIMIT 1",
                [$oldHeadResidentId, $householdId]
            );
            if ($oldIsHead && $newMatchesH) {
                return true;
            }
        }

        return false;
    }
}

/**
 * Error message if resident cannot be designated household head (age), or null if OK.
 * Uses calendar age vs today; minimum default 18 (legal age of majority in PH for civil acts).
 */
if (!function_exists('householdHeadMinimumAgeError')) {
    function householdHeadMinimumAgeError($db, int $residentId, int $minYears = 18) {
        $hasCol = static function ($table, $name) use ($db) {
            $row = $db->fetchOne(
                'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                [$table, $name]
            );

            return !empty($row) && (int) $row['c'] > 0;
        };

        $dobCol = null;
        if ($hasCol('residents', 'birth_date')) {
            $dobCol = 'birth_date';
        } elseif ($hasCol('residents', 'date_of_birth')) {
            $dobCol = 'date_of_birth';
        }
        if (!$dobCol) {
            return null;
        }

        $row = $db->fetchOne("SELECT `{$dobCol}` AS dob FROM residents WHERE id = ? LIMIT 1", [$residentId]);
        $dobRaw = $row['dob'] ?? null;
        if ($dobRaw === null || trim((string) $dobRaw) === '') {
            return 'Birth date is required for the new household head.';
        }

        $dobStr = substr(trim((string) $dobRaw), 0, 10);
        try {
            $birth = new DateTime($dobStr);
        } catch (Exception $e) {
            return 'Invalid birth date on resident record.';
        }
        $today = new DateTime('today');
        $age = (int) $birth->diff($today)->y;
        if ($age < $minYears) {
            return "Household head must be at least {$minYears} years old (selected resident is {$age}).";
        }

        return null;
    }
}
