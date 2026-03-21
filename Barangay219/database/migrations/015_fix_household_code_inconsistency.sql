-- Fix data inconsistency: members should NOT have a family_head_code on residents table.
-- Only designated household heads should hold a family_head_code.
-- Stale family_head_code values on non-head residents cause the UI to misidentify them
-- as heads and display incorrect "joined via FH-XXXXX" labels.

-- Step 1: Clear family_head_code on residents who are NOT a designated head of any household.
-- A resident is a head if households.family_head_id = residents.id.
UPDATE residents r
LEFT JOIN households h
    ON h.family_head_id = r.id
SET r.family_head_code = NULL
WHERE h.id IS NULL
  AND r.family_head_code IS NOT NULL
  AND TRIM(r.family_head_code) <> '';

-- Step 2: Sync family_code for members to match their household head's family_code.
-- This ensures members are grouped correctly under their head in the UI.
UPDATE residents member
INNER JOIN households h ON h.id = member.household_id
INNER JOIN residents head ON head.id = h.family_head_id
SET member.family_code = head.family_code
WHERE member.id <> h.family_head_id
  AND head.family_code IS NOT NULL
  AND TRIM(head.family_code) <> ''
  AND (member.family_code IS NULL
       OR TRIM(member.family_code) = ''
       OR member.family_code <> head.family_code);

-- Step 3: Fill in family_head_resident_id ONLY when it is NULL or points to a resident
-- outside the household. In multi-head households a member may correctly reference a
-- non-designated head, so we must not overwrite valid references.
UPDATE residents member
INNER JOIN households h ON h.id = member.household_id
LEFT JOIN residents ref_head
    ON ref_head.id = member.family_head_resident_id
   AND ref_head.household_id = member.household_id
SET member.family_head_resident_id = h.family_head_id
WHERE member.id <> h.family_head_id
  AND h.family_head_id IS NOT NULL
  AND (member.family_head_resident_id IS NULL
       OR ref_head.id IS NULL);

-- Step 4: Ensure every designated head has a family_head_code matching their household.
UPDATE residents r
INNER JOIN households h ON h.family_head_id = r.id
SET r.family_head_code = h.family_head_code
WHERE h.family_head_code IS NOT NULL
  AND TRIM(h.family_head_code) <> ''
  AND (r.family_head_code IS NULL
       OR TRIM(r.family_head_code) = ''
       OR r.family_head_code <> h.family_head_code);
