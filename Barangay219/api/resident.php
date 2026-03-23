<?php
/**
 * E-Barangay Information Management System
 * Resident Information API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';

requireLogin();
requireModuleAccess('residents');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'list':
        listResidents();
        break;
    
    case 'get':
        getResident();
        break;
    
    case 'create':
        sendResponse(false, 'Creating new residents from Residents Management is disabled. Approve resident applications instead.', null, 403);
        break;
    
    case 'update':
        if (!canPerformModulePermission('residents', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateResident();
        break;

    case 'verify_id':
        if (!canPerformModulePermission('residents', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateVerificationStatus('verified');
        break;

    case 'reject_id':
        if (!canPerformModulePermission('residents', 'can_edit')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        updateVerificationStatus('rejected');
        break;
    
    case 'delete':
        if (!canPerformModulePermission('residents', 'can_delete')) {
            sendResponse(false, 'Access denied', null, 403);
        }
        deleteResident();
        break;
    
    case 'search':
        searchResidents();
        break;
    
    default:
        sendResponse(false, 'Invalid action', null, 400);
        break;
}

/**
 * Build a safe resident_applications lookup for a resident row.
 * Preference order: approved_resident_id match, then name + birth date fallback.
 */
function buildRegistrationLookupSql($db, $residentAlias = 'r') {
    $hasRaApprovedId = columnExists($db, 'resident_applications', 'approved_resident_id');
    $hasRaBirth = columnExists($db, 'resident_applications', 'birth_date');
    $statusCol = columnExists($db, 'resident_applications', 'record_status')
        ? 'record_status'
        : (columnExists($db, 'resident_applications', 'status') ? 'status' : null);
    $birthRef = columnExists($db, 'residents', 'birth_date')
        ? $residentAlias . '.birth_date'
        : (columnExists($db, 'residents', 'date_of_birth') ? $residentAlias . '.date_of_birth' : null);

    $matchParts = [];
    if ($hasRaApprovedId) {
        $matchParts[] = 'ra.approved_resident_id = ' . $residentAlias . '.id';
    }
    if ($birthRef !== null && $hasRaBirth) {
        $matchParts[] = '(LOWER(TRIM(ra.first_name)) = LOWER(TRIM(' . $residentAlias . '.first_name)) AND LOWER(TRIM(ra.last_name)) = LOWER(TRIM(' . $residentAlias . '.last_name)) AND ra.birth_date = ' . $birthRef . ')';
    }
    if (empty($matchParts)) {
        return null;
    }

    $matchSql = '(' . implode(' OR ', $matchParts) . ')';
    if ($statusCol !== null) {
        $whereSql = "LOWER(TRIM(COALESCE(ra.`{$statusCol}`, ''))) = 'approved' AND {$matchSql}";
    } elseif ($hasRaApprovedId) {
        $whereSql = 'ra.approved_resident_id = ' . $residentAlias . '.id';
    } else {
        $whereSql = $matchSql;
    }

    $orderBy = $hasRaApprovedId
        ? '(ra.approved_resident_id = ' . $residentAlias . '.id) DESC, ra.id DESC'
        : 'ra.id DESC';

    return [
        'where' => $whereSql,
        'order' => $orderBy
    ];
}

function buildRegistrationSelectExpression($db, $lookup, $column, $alias) {
    if (!$lookup || !columnExists($db, 'resident_applications', $column)) {
        return "NULL AS {$alias}";
    }

    return "(SELECT ra.`{$column}` FROM resident_applications ra WHERE {$lookup['where']} ORDER BY {$lookup['order']} LIMIT 1) AS {$alias}";
}

function buildRegistrationSelectExpressions($db, $residentAlias = 'r') {
    $lookup = buildRegistrationLookupSql($db, $residentAlias);

    $exprs = [
        buildRegistrationSelectExpression($db, $lookup, 'house_type', 'registration_house_type'),
        buildRegistrationSelectExpression($db, $lookup, 'house_ownership', 'registration_house_ownership'),
        buildRegistrationSelectExpression($db, $lookup, 'household_role', 'registration_household_role'),
        buildRegistrationSelectExpression($db, $lookup, 'voter_status', 'registration_voter_status'),
        buildRegistrationSelectExpression($db, $lookup, 'precinct_number', 'registration_precinct_number'),
        buildRegistrationSelectExpression($db, $lookup, 'household_income', 'registration_household_income'),
        buildRegistrationSelectExpression($db, $lookup, 'occupation', 'registration_occupation'),
        buildRegistrationSelectExpression($db, $lookup, 'educational_attainment', 'registration_educational_attainment'),
        buildRegistrationSelectExpression($db, $lookup, 'employment_status', 'registration_employment_status'),
        buildRegistrationSelectExpression($db, $lookup, 'residency_start_date', 'registration_residency_start_date'),
        buildRegistrationSelectExpression($db, $lookup, 'length_of_residency', 'registration_length_of_residency'),
        buildRegistrationSelectExpression($db, $lookup, 'length_of_residency_years', 'registration_length_of_residency_years'),
        buildRegistrationSelectExpression($db, $lookup, 'valid_id_type', 'registration_valid_id_type'),
        buildRegistrationSelectExpression($db, $lookup, 'valid_id_number', 'registration_valid_id_number'),
        buildRegistrationSelectExpression($db, $lookup, 'is_senior_citizen', 'registration_is_senior_citizen'),
        buildRegistrationSelectExpression($db, $lookup, 'is_pwd', 'registration_is_pwd'),
        buildRegistrationSelectExpression($db, $lookup, 'pwd_id_number', 'registration_pwd_id_number'),
        buildRegistrationSelectExpression($db, $lookup, 'is_solo_parent', 'registration_is_solo_parent'),
        buildRegistrationSelectExpression($db, $lookup, 'solo_parent_id_number', 'registration_solo_parent_id_number'),
        buildRegistrationSelectExpression($db, $lookup, 'is_ip_member', 'registration_is_ip_member'),
        buildRegistrationSelectExpression($db, $lookup, 'ip_group', 'registration_ip_group'),
        buildRegistrationSelectExpression($db, $lookup, 'is_4ps_beneficiary', 'registration_is_4ps_beneficiary')
    ];

    return implode(",\n                ", $exprs);
}

/**
 * List all residents with pagination
 */
function listResidents() {
    try {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? ITEMS_PER_PAGE);
        $offset = ($page - 1) * $limit;

        $q = sanitizeInput($_GET['q'] ?? $_GET['search'] ?? '');
        $status = sanitizeInput($_GET['status'] ?? '');
        $gender = sanitizeInput($_GET['gender'] ?? '');
        $age_from = $_GET['age_from'] ?? '';
        $age_to = $_GET['age_to'] ?? '';
        $residency_from = $_GET['residency_from'] ?? ''; // Minimum years of residency
        $residency_to = $_GET['residency_to'] ?? ''; // Maximum years of residency
        
        $db = Database::getInstance();

        $where = '1=1';
        $params = [];
        if (!empty($q)) {
            $term = '%' . $q . '%';
            $where .= " AND (r.first_name LIKE ? OR r.middle_name LIKE ? OR r.last_name LIKE ? OR r.address LIKE ? OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?)";
            $params = array_merge($params, [$term, $term, $term, $term, $term]);
        }
        if (!empty($status)) {
            $where .= " AND r.status = ?";
            $params[] = $status;
        }
        if (!empty($gender)) {
            $where .= " AND r.gender = ?";
            $params[] = $gender;
        }
        if ($age_from !== '' && is_numeric($age_from)) {
            $where .= " AND TIMESTAMPDIFF(YEAR, r.birth_date, CURDATE()) >= ?";
            $params[] = intval($age_from);
        }
        if ($age_to !== '' && is_numeric($age_to)) {
            $where .= " AND TIMESTAMPDIFF(YEAR, r.birth_date, CURDATE()) <= ?";
            $params[] = intval($age_to);
        }
        
        // Filter by length of residency (in years)
        if ($residency_from !== '' && is_numeric($residency_from)) {
            $minYears = floatval($residency_from);
            $minDate = date('Y-m-d', strtotime("-$minYears years"));
            $where .= " AND r.residency_start_date <= ?";
            $params[] = $minDate;
        }
        if ($residency_to !== '' && is_numeric($residency_to)) {
            $maxYears = floatval($residency_to);
            $maxDate = date('Y-m-d', strtotime("-$maxYears years"));
            $where .= " AND r.residency_start_date >= ?";
            $params[] = $maxDate;
        }

        $headAccountOnly = filter_var(
            $_GET['head_account_only'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) === true;
        if ($headAccountOnly && columnExists($db, 'residents', 'household_role')) {
            $where .= " AND LOWER(TRIM(COALESCE(r.household_role,''))) IN ('head', 'head of household')";
        }

        // Admin "add to household" picker: only residents not yet assigned to any household (matches add_member API).
        // Client-side excludes edge cases (e.g. members already in this HH with stale household_id).
        $notInHouseholdId = intval($_GET['not_in_household_id'] ?? 0);
        if ($notInHouseholdId > 0 && columnExists($db, 'residents', 'household_id')) {
            $where .= ' AND r.household_id IS NULL';
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM residents r WHERE $where";
        $total = $db->fetchOne($countSql, $params)['total'];

        // Household code can be stored as households.household_id_code (preferred) or households.family_code (fallback).
        $hasHouseholdIdCode = columnExists($db, 'households', 'household_id_code');
        $hasFamilyCode = columnExists($db, 'households', 'family_code');
        $hasResidentFamilyHeadCode = columnExists($db, 'residents', 'family_head_code');
        $hasHouseholdFamilyHeadCode = columnExists($db, 'households', 'family_head_code');

        if ($hasHouseholdIdCode) {
            $householdCodeExpr = 'h.household_id_code AS household_code,';
        } elseif ($hasFamilyCode) {
            $householdCodeExpr = 'h.family_code AS household_code,';
        } else {
            $householdCodeExpr = 'NULL AS household_code,';
        }
        // Prefer per-resident family_head_code when available; fall back to household-level for older data.
        if ($hasResidentFamilyHeadCode) {
            $familyHeadCodeExpr = 'r.family_head_code AS family_head_code,';
        } elseif ($hasHouseholdFamilyHeadCode) {
            $familyHeadCodeExpr = 'h.family_head_code AS family_head_code,';
        } else {
            $familyHeadCodeExpr = 'NULL AS family_head_code,';
        }

        $registrationMetaExpr = buildRegistrationSelectExpressions($db, 'r');
        if (columnExists($db, 'households', 'house_type')) {
            $currentHouseTypeExpr = 'h.house_type AS current_household_house_type,';
        } else {
            $currentHouseTypeExpr = 'NULL AS current_household_house_type,';
        }
        if (columnExists($db, 'households', 'housing_status')) {
            $currentHousingStatusExpr = 'h.housing_status AS current_household_housing_status,';
        } else {
            $currentHousingStatusExpr = 'NULL AS current_household_housing_status,';
        }
        
        // Get residents - ordered by ID so new residents appear at the end
        $sql = "SELECT r.*, h.address as household_address, h.total_members, h.family_head_id,
                CASE WHEN h.family_head_id = r.id THEN 1 ELSE 0 END as is_household_head,
                $householdCodeExpr
                $familyHeadCodeExpr
                $registrationMetaExpr,
                $currentHouseTypeExpr
                $currentHousingStatusExpr
                (SELECT COUNT(*) FROM certificate_requests cr WHERE cr.resident_id = r.id) as certificates_count
                FROM residents r
                LEFT JOIN households h ON r.household_id = h.id
                WHERE $where
                ORDER BY r.id ASC
                LIMIT ? OFFSET ?";

        $queryParams = array_merge($params, [$limit, $offset]);
        $residents = $db->fetchAll($sql, $queryParams);
        
        // Compute length_of_residency for each resident
        foreach ($residents as &$resident) {
            if (!empty($resident['residency_start_date'])) {
                try {
                    $startDateTime = new DateTime($resident['residency_start_date']);
                    $todayDateTime = new DateTime();
                    $interval = $todayDateTime->diff($startDateTime);
                    $years = $interval->y;
                    $months = $interval->m;
                    
                    $resident['computed_length_of_residency'] = $years . ' year' . ($years === 1 ? '' : 's') . ' ' . $months . ' month' . ($months === 1 ? '' : 's');
                    $resident['computed_length_of_residency_years'] = (float)($years + ($months / 12));
                } catch (Exception $e) {
                    $resident['computed_length_of_residency'] = $resident['length_of_residency'] ?? null;
                    $resident['computed_length_of_residency_years'] = $resident['length_of_residency_years'] ?? null;
                }
            }
        }
        unset($resident);
        
        sendResponse(true, 'Residents retrieved successfully', [
            'residents' => $residents,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]);
        
    } catch (Exception $e) {
        error_log("List residents error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving residents', null, 500);
    }
}

/**
 * Get single resident
 */
function getResident() {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();

        $hasHouseholdIdCode = columnExists($db, 'households', 'household_id_code');
        $hasFamilyCode = columnExists($db, 'households', 'family_code');
        $hasResidentFamilyHeadCode = columnExists($db, 'residents', 'family_head_code');
        $hasFamilyHeadCode = columnExists($db, 'households', 'family_head_code');
        if ($hasHouseholdIdCode) {
            $householdCodeExpr = 'h.household_id_code AS household_code,';
        } elseif ($hasFamilyCode) {
            $householdCodeExpr = 'h.family_code AS household_code,';
        } else {
            $householdCodeExpr = 'NULL AS household_code,';
        }
        if ($hasResidentFamilyHeadCode) {
            $familyHeadCodeExpr = 'r.family_head_code AS family_head_code,';
        } elseif ($hasFamilyHeadCode) {
            $familyHeadCodeExpr = 'h.family_head_code AS family_head_code,';
        } else {
            $familyHeadCodeExpr = 'NULL AS family_head_code,';
        }

        $registrationMetaExpr = buildRegistrationSelectExpressions($db, 'r');
        if (columnExists($db, 'households', 'house_type')) {
            $currentHouseTypeExpr = 'h.house_type AS current_household_house_type,';
        } else {
            $currentHouseTypeExpr = 'NULL AS current_household_house_type,';
        }
        if (columnExists($db, 'households', 'housing_status')) {
            $currentHousingStatusExpr = 'h.housing_status AS current_household_housing_status,';
        } else {
            $currentHousingStatusExpr = 'NULL AS current_household_housing_status,';
        }
        
        $sql = "SELECT r.*, h.address as household_address, h.total_members, h.family_head_id,
                CASE WHEN h.family_head_id = r.id THEN 1 ELSE 0 END as is_household_head,
                $householdCodeExpr
                $familyHeadCodeExpr
                $registrationMetaExpr,
                $currentHouseTypeExpr
                $currentHousingStatusExpr
                (SELECT COUNT(*) FROM certificate_requests cr WHERE cr.resident_id = r.id) as certificates_count
                FROM residents r
                LEFT JOIN households h ON r.household_id = h.id
                WHERE r.id = ?";
        
        $resident = $db->fetchOne($sql, [$id]);
        
        if (!$resident) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }
        
        // Compute length_of_residency from residency_start_date if available
        if (!empty($resident['residency_start_date'])) {
            try {
                $startDateTime = new DateTime($resident['residency_start_date']);
                $todayDateTime = new DateTime();
                $interval = $todayDateTime->diff($startDateTime);
                $years = $interval->y;
                $months = $interval->m;
                
                $resident['computed_length_of_residency'] = $years . ' year' . ($years === 1 ? '' : 's') . ' ' . $months . ' month' . ($months === 1 ? '' : 's');
                $resident['computed_length_of_residency_years'] = (float)($years + ($months / 12));
            } catch (Exception $e) {
                // If computation fails, just return the stored values
                $resident['computed_length_of_residency'] = $resident['length_of_residency'] ?? null;
                $resident['computed_length_of_residency_years'] = $resident['length_of_residency_years'] ?? null;
            }
        }
        
        sendResponse(true, 'Resident retrieved successfully', $resident);
        
    } catch (Exception $e) {
        error_log("Get resident error: " . $e->getMessage());
        sendResponse(false, 'Error retrieving resident', null, 500);
    }
}

/**
 * Create new resident
 */
function createResident() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $suffix = sanitizeInput($_POST['suffix'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
    $occupation = sanitizeInput($_POST['occupation'] ?? '');
    $citizenship = sanitizeInput($_POST['citizenship'] ?? 'Filipino');
    $address = sanitizeInput($_POST['address'] ?? '');
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $household_id = intval($_POST['household_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? RESIDENT_ACTIVE);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($gender) || empty($civil_status) || empty($occupation) || empty($citizenship) || empty($contact_number) || empty($address) || empty($status)) {
        sendResponse(false, 'All fields are required except household and suffix', null, 400);
        return;
    }
    
    $allowed_genders = ['male', 'female', 'other'];
    if (!in_array($gender, $allowed_genders)) {
        sendResponse(false, 'Invalid gender', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $sql = "INSERT INTO residents (first_name, middle_name, last_name, suffix, birth_date, gender, 
                                      civil_status, occupation, citizenship, address, contact_number, 
                                      household_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $first_name,
            $middle_name ?: null,
            $last_name,
            $suffix ?: null,
            $birth_date,
            $gender,
            $civil_status ?: null,
            $occupation ?: null,
            $citizenship,
            $address,
            $contact_number ?: null,
            $household_id ?: null,
            $status
        ];
        
        $db->query($sql, $params);
        $residentId = $db->lastInsertId();
        
        // Get created resident
        $resident = $db->fetchOne("SELECT * FROM residents WHERE id = ?", [$residentId]);
        
        sendResponse(true, 'Resident created successfully', $resident);
        
    } catch (Exception $e) {
        error_log("Create resident error: " . $e->getMessage());
        sendResponse(false, 'Error creating resident', null, 500);
    }
}

/**
 * Update resident
 */
function updateResident() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $suffix = sanitizeInput($_POST['suffix'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
    $occupation = sanitizeInput($_POST['occupation'] ?? '');
    $citizenship = sanitizeInput($_POST['citizenship'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $contact_number = sanitizeInput($_POST['contact_number'] ?? '');
    $household_id = intval($_POST['household_id'] ?? 0);
    $status = sanitizeInput($_POST['status'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($gender) || empty($civil_status) || empty($occupation) || empty($citizenship) || empty($contact_number) || empty($address) || empty($status)) {
        sendResponse(false, 'All fields are required except household and suffix', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Check if resident exists
        $existing = $db->fetchOne("SELECT id FROM residents WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }
        
        $sql = "UPDATE residents SET 
                first_name = ?, middle_name = ?, last_name = ?, suffix = ?, 
                birth_date = ?, gender = ?, civil_status = ?, occupation = ?, 
                citizenship = ?, address = ?, contact_number = ?, 
                household_id = ?, status = ?
                WHERE id = ?";
        
        $params = [
            $first_name,
            $middle_name ?: null,
            $last_name,
            $suffix ?: null,
            $birth_date,
            $gender,
            $civil_status ?: null,
            $occupation ?: null,
            $citizenship,
            $address,
            $contact_number ?: null,
            $household_id ?: null,
            $status,
            $id
        ];
        
        $db->query($sql, $params);
        
        // Get updated resident
        $resident = $db->fetchOne("SELECT * FROM residents WHERE id = ?", [$id]);
        
        sendResponse(true, 'Resident updated successfully', $resident);
        
    } catch (Exception $e) {
        error_log("Update resident error: " . $e->getMessage());
        sendResponse(false, 'Error updating resident', null, 500);
    }
}

/**
 * Delete resident (soft delete)
 */
function deleteResident() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        // Soft delete by setting status to inactive
        $sql = "UPDATE residents SET status = 'inactive' WHERE id = ?";
        $db->query($sql, [$id]);
        
        sendResponse(true, 'Resident deleted successfully', null);
        
    } catch (Exception $e) {
        error_log("Delete resident error: " . $e->getMessage());
        sendResponse(false, 'Error deleting resident', null, 500);
    }
}

/**
 * Search residents
 */
function searchResidents() {
    $query = sanitizeInput($_GET['q'] ?? $_POST['q'] ?? '');
    
    if (empty($query)) {
        sendResponse(false, 'Search query is required', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        
        $searchTerm = "%{$query}%";
        $sql = "SELECT r.*, h.address as household_address, h.family_head_id,
                CASE WHEN h.family_head_id = r.id THEN 1 ELSE 0 END as is_household_head
                FROM residents r
                LEFT JOIN households h ON r.household_id = h.id
                WHERE r.first_name LIKE ? 
                   OR r.middle_name LIKE ? 
                   OR r.last_name LIKE ? 
                   OR r.address LIKE ?
                   OR CONCAT(r.first_name, ' ', r.last_name) LIKE ?
                ORDER BY r.id ASC
                LIMIT 50";
        
        $residents = $db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        
        sendResponse(true, 'Search completed', $residents);
        
    } catch (Exception $e) {
        error_log("Search residents error: " . $e->getMessage());
        sendResponse(false, 'Error searching residents', null, 500);
    }
}

function getTableColumns($db, $table) {
    $rows = $db->fetchAll(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ?",
        [$table]
    );
    return array_map(static function ($row) {
        return $row['column_name'];
    }, $rows);
}

function columnExists($db, $table, $column) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
        [$table, $column]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
}

function addColumnIfMissing($db, $table, $column, $definition) {
    if (!columnExists($db, $table, $column)) {
        $db->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function ensureResidentVerificationColumns($db) {
    addColumnIfMissing($db, 'residents', 'id_document_path', "VARCHAR(255) NULL AFTER email");
    addColumnIfMissing($db, 'residents', 'verification_status', "ENUM('pending','verified','rejected') DEFAULT 'pending' AFTER record_status");
    addColumnIfMissing($db, 'residents', 'rejection_reason', "TEXT NULL AFTER remarks");
}

function updateVerificationStatus($targetStatus) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method', null, 405);
        return;
    }

    $id = intval($_POST['id'] ?? 0);
    $remarks = sanitizeInput($_POST['remarks'] ?? '');

    if (!$id) {
        sendResponse(false, 'Resident ID is required', null, 400);
        return;
    }

    try {
        $db = Database::getInstance();
        ensureResidentVerificationColumns($db);

        $cols = array_flip(getTableColumns($db, 'residents'));
        if (!isset($cols['verification_status'])) {
            sendResponse(false, 'Verification status column is not available. Run latest migrations first.', null, 500);
            return;
        }

        $selectCols = ['id', 'resident_code'];
        if (isset($cols['id_document_path'])) $selectCols[] = 'id_document_path';
        if (isset($cols['verification_status'])) $selectCols[] = 'verification_status';
        if (isset($cols['status'])) $selectCols[] = 'status';
        if (isset($cols['record_status'])) $selectCols[] = 'record_status';
        $selectSql = '`' . implode('`,`', $selectCols) . '`';

        $resident = $db->fetchOne(
            "SELECT $selectSql
             FROM residents
             WHERE id = ?
             LIMIT 1",
            [$id]
        );

        if (!$resident) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }

        if (empty($resident['id_document_path'])) {
            sendResponse(false, 'Resident has no uploaded ID document to verify.', null, 400);
            return;
        }

        $setParts = ["verification_status = ?"];
        $params = [$targetStatus];

        if (isset($cols['record_status'])) {
            $setParts[] = "record_status = ?";
            $params[] = $targetStatus === 'verified' ? 'active' : 'rejected';
        }

        if (isset($cols['status']) && $targetStatus === 'verified') {
            $setParts[] = "status = ?";
            $params[] = 'active';
        }

        if (isset($cols['remarks'])) {
            if ($targetStatus === 'verified') {
                $setParts[] = "remarks = ?";
                $params[] = $remarks !== '' ? $remarks : 'ID verified by barangay staff.';
            } else {
                $setParts[] = "remarks = ?";
                $params[] = $remarks !== '' ? $remarks : 'ID verification rejected by barangay staff.';
            }
        }

        if (isset($cols['rejection_reason'])) {
            $setParts[] = "rejection_reason = ?";
            $params[] = $targetStatus === 'rejected'
                ? ($remarks !== '' ? $remarks : 'ID verification was rejected. Please upload a clearer or valid ID document.')
                : null;
        }

        if (isset($cols['last_updated_at'])) {
            $setParts[] = "last_updated_at = NOW()";
        }

        if (isset($cols['last_updated_by']) && function_exists('getCurrentUserId')) {
            $setParts[] = "last_updated_by = ?";
            $params[] = (int)getCurrentUserId();
        }

        $params[] = $id;

        $sql = "UPDATE residents SET " . implode(', ', $setParts) . " WHERE id = ?";
        $db->query($sql, $params);

        sendResponse(true, $targetStatus === 'verified' ? 'Resident ID verified successfully.' : 'Resident ID rejected successfully.', [
            'id' => $id,
            'resident_code' => $resident['resident_code'] ?? null,
            'verification_status' => $targetStatus
        ]);
    } catch (Exception $e) {
        error_log('Update verification status error: ' . $e->getMessage());
        sendResponse(false, 'Error updating verification status', null, 500);
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
