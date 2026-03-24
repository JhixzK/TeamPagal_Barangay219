<?php
/**
 * E-Barangay Information Management System
 * Resident Information API
 */

header('Content-Type: application/json');
define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/indigent-classification.php';

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
        
        $hasResidentsHouseholdId = columnExists($db, 'residents', 'household_id');
        $hasHouseholdsTable = tableExists($db, 'households');
        $canJoinHouseholds = $hasHouseholdsTable && $hasResidentsHouseholdId;
        $hasHouseholdAddress = $canJoinHouseholds && columnExists($db, 'households', 'address');
        $hasHouseholdTotalMembers = $canJoinHouseholds && columnExists($db, 'households', 'total_members');
        $hasHouseholdFamilyHeadId = $canJoinHouseholds && columnExists($db, 'households', 'family_head_id');

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM residents r WHERE $where";
        $total = $db->fetchOne($countSql, $params)['total'];

        // Household code can be stored as households.household_id_code (preferred) or households.family_code (fallback).
        $hasHouseholdIdCode = $canJoinHouseholds && columnExists($db, 'households', 'household_id_code');
        $hasFamilyCode = $canJoinHouseholds && columnExists($db, 'households', 'family_code');
        $hasResidentFamilyHeadCode = columnExists($db, 'residents', 'family_head_code');
        $hasHouseholdFamilyHeadCode = $canJoinHouseholds && columnExists($db, 'households', 'family_head_code');

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

        $housingMetaExpr = '';
        // Registration housing: prefer approved_resident_id link; fall back to name + birth_date when that link is missing.
        $hasRaHouseType = columnExists($db, 'resident_applications', 'house_type');
        $hasRaOwnership = columnExists($db, 'resident_applications', 'house_ownership');
        $hasRaApprovedId = columnExists($db, 'resident_applications', 'approved_resident_id');
        $hasRaBirth = columnExists($db, 'resident_applications', 'birth_date');
        $statusCol = columnExists($db, 'resident_applications', 'record_status') ? 'record_status'
            : (columnExists($db, 'resident_applications', 'status') ? 'status' : null);
        $birthRef = columnExists($db, 'residents', 'birth_date') ? 'r.birth_date'
            : (columnExists($db, 'residents', 'date_of_birth') ? 'r.date_of_birth' : null);

        $raMatchParts = [];
        if ($hasRaApprovedId) {
            $raMatchParts[] = 'ra.approved_resident_id = r.id';
        }
        if ($birthRef !== null && $hasRaBirth) {
            $raMatchParts[] = '(LOWER(TRIM(ra.first_name)) = LOWER(TRIM(r.first_name)) AND LOWER(TRIM(ra.last_name)) = LOWER(TRIM(r.last_name)) AND ra.birth_date = ' . $birthRef . ')';
        }
        $raMatchSql = !empty($raMatchParts) ? '(' . implode(' OR ', $raMatchParts) . ')' : '0';
        if ($statusCol !== null) {
            $raWhereSql = "LOWER(TRIM(COALESCE(ra.`{$statusCol}`,''))) = 'approved' AND {$raMatchSql}";
        } elseif ($hasRaApprovedId) {
            // No status column: only trust approved_resident_id linkage.
            $raWhereSql = 'ra.approved_resident_id = r.id';
        } else {
            $raWhereSql = '0';
        }
        $raOrderBy = $hasRaApprovedId ? '(ra.approved_resident_id = r.id) DESC, ra.id DESC' : 'ra.id DESC';

        if ($hasRaHouseType) {
            $housingMetaExpr .= "(SELECT ra.house_type FROM resident_applications ra WHERE {$raWhereSql} ORDER BY {$raOrderBy} LIMIT 1) AS registration_house_type, ";
        } else {
            $housingMetaExpr .= 'NULL AS registration_house_type, ';
        }
        if ($hasRaOwnership) {
            $housingMetaExpr .= "(SELECT ra.house_ownership FROM resident_applications ra WHERE {$raWhereSql} ORDER BY {$raOrderBy} LIMIT 1) AS registration_house_ownership, ";
        } else {
            $housingMetaExpr .= 'NULL AS registration_house_ownership, ';
        }
        if ($canJoinHouseholds && columnExists($db, 'households', 'house_type')) {
            $housingMetaExpr .= 'h.house_type AS current_household_house_type, ';
        } else {
            $housingMetaExpr .= 'NULL AS current_household_house_type, ';
        }
        if ($canJoinHouseholds && columnExists($db, 'households', 'housing_status')) {
            $housingMetaExpr .= 'h.housing_status AS current_household_housing_status, ';
        } else {
            $housingMetaExpr .= 'NULL AS current_household_housing_status, ';
        }

        $householdAddressExpr = $hasHouseholdAddress ? 'h.address as household_address,' : 'NULL as household_address,';
        $householdTotalMembersExpr = $hasHouseholdTotalMembers ? 'h.total_members,' : '0 as total_members,';
        $householdFamilyHeadExpr = $hasHouseholdFamilyHeadId ? 'h.family_head_id,' : 'NULL as family_head_id,';
        $isHouseholdHeadExpr = $hasHouseholdFamilyHeadId ? 'CASE WHEN h.family_head_id = r.id THEN 1 ELSE 0 END as is_household_head,' : '0 as is_household_head,';
        $certificatesCountExpr = (tableExists($db, 'certificate_requests') && columnExists($db, 'certificate_requests', 'resident_id'))
            ? '(SELECT COUNT(*) FROM certificate_requests cr WHERE cr.resident_id = r.id) as certificates_count'
            : '0 as certificates_count';
        $householdJoinSql = $canJoinHouseholds ? 'LEFT JOIN households h ON r.household_id = h.id' : '';
        
        // Get residents - ordered by ID so new residents appear at the end
        $sql = "SELECT r.*, $householdAddressExpr $householdTotalMembersExpr $householdFamilyHeadExpr
                $isHouseholdHeadExpr
                $householdCodeExpr
                $familyHeadCodeExpr
                $housingMetaExpr
                $certificatesCountExpr
                FROM residents r
                $householdJoinSql
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

        $hasResidentsHouseholdId = columnExists($db, 'residents', 'household_id');
        $hasHouseholdsTable = tableExists($db, 'households');
        $canJoinHouseholds = $hasHouseholdsTable && $hasResidentsHouseholdId;
        $hasHouseholdAddress = $canJoinHouseholds && columnExists($db, 'households', 'address');
        $hasHouseholdTotalMembers = $canJoinHouseholds && columnExists($db, 'households', 'total_members');
        $hasHouseholdFamilyHeadId = $canJoinHouseholds && columnExists($db, 'households', 'family_head_id');
        $hasHouseholdIdCode = $canJoinHouseholds && columnExists($db, 'households', 'household_id_code');
        $hasFamilyCode = $canJoinHouseholds && columnExists($db, 'households', 'family_code');
        $hasFamilyHeadCode = $canJoinHouseholds && columnExists($db, 'households', 'family_head_code');
        $certificatesCountExpr = (tableExists($db, 'certificate_requests') && columnExists($db, 'certificate_requests', 'resident_id'))
            ? '(SELECT COUNT(*) FROM certificate_requests cr WHERE cr.resident_id = r.id) as certificates_count'
            : '0 as certificates_count';
        $householdAddressExpr = $hasHouseholdAddress ? 'h.address as household_address,' : 'NULL as household_address,';
        $householdTotalMembersExpr = $hasHouseholdTotalMembers ? 'h.total_members,' : '0 as total_members,';
        $householdFamilyHeadExpr = $hasHouseholdFamilyHeadId ? 'h.family_head_id,' : 'NULL as family_head_id,';
        $isHouseholdHeadExpr = $hasHouseholdFamilyHeadId ? 'CASE WHEN h.family_head_id = r.id THEN 1 ELSE 0 END as is_household_head,' : '0 as is_household_head,';
        $householdJoinSql = $canJoinHouseholds ? 'LEFT JOIN households h ON r.household_id = h.id' : '';

        if ($hasHouseholdIdCode) {
            $householdCodeExpr = 'h.household_id_code AS household_code,';
        } elseif ($hasFamilyCode) {
            $householdCodeExpr = 'h.family_code AS household_code,';
        } else {
            $householdCodeExpr = 'NULL AS household_code,';
        }
        $familyHeadCodeExpr = $hasFamilyHeadCode ? 'h.family_head_code AS family_head_code,' : 'NULL AS family_head_code,';
        
        $sql = "SELECT r.*, $householdAddressExpr $householdTotalMembersExpr $householdFamilyHeadExpr
            $isHouseholdHeadExpr
                $householdCodeExpr
                $familyHeadCodeExpr
            $certificatesCountExpr
                FROM residents r
            $householdJoinSql
                WHERE r.id = ?";
        
        $resident = $db->fetchOne($sql, [$id]);
        
        if (!$resident) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }

        $registrationSnapshot = getLatestApprovedRegistrationSnapshot($db, $resident);
        if (!empty($registrationSnapshot)) {
            $resident = array_merge($resident, $registrationSnapshot);
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
 * Return latest approved registration data for a resident, mapped to
 * registration_* keys consumed by the Residents admin detail modal.
 */
function getLatestApprovedRegistrationSnapshot($db, array $resident) {
    if (!tableExists($db, 'resident_applications')) {
        return [];
    }

    $appCols = array_flip(getTableColumns($db, 'resident_applications'));
    if (empty($appCols)) {
        return [];
    }

    $statusCol = isset($appCols['record_status']) ? 'record_status' : (isset($appCols['status']) ? 'status' : null);

    $neededMap = [
        'first_name' => 'registration_first_name',
        'middle_name' => 'registration_middle_name',
        'last_name' => 'registration_last_name',
        'suffix' => 'registration_suffix',
        'sex' => 'registration_sex',
        'gender' => 'registration_gender',
        'civil_status' => 'registration_civil_status',
        'citizenship' => 'registration_citizenship',
        'mobile_number' => 'registration_mobile_number',
        'email' => 'registration_email',
        'house_number' => 'registration_house_number',
        'street' => 'registration_street',
        'purok_sitio' => 'registration_purok_sitio',
        'barangay' => 'registration_barangay',
        'city' => 'registration_city',
        'province' => 'registration_province',
        'occupation' => 'registration_occupation',
        'occupation_other' => 'registration_occupation_other',
        'educational_attainment' => 'registration_educational_attainment',
        'educational_attainment_other' => 'registration_educational_attainment_other',
        'employment_status' => 'registration_employment_status',
        'voter_status' => 'registration_voter_status',
        'precinct_number' => 'registration_precinct_number',
        'house_type' => 'registration_house_type',
        'house_ownership' => 'registration_house_ownership',
        'household_role' => 'registration_household_role',
        'relationship_to_head' => 'registration_relationship_to_head',
        'residency_start_date' => 'registration_residency_start_date',
        'length_of_residency' => 'registration_length_of_residency',
        'monthly_income' => 'registration_monthly_income',
        'household_income' => 'registration_household_income',
        'place_of_birth' => 'registration_place_of_birth',
        'is_senior_citizen' => 'registration_is_senior_citizen',
        'is_pwd' => 'registration_is_pwd',
        'pwd_id_number' => 'registration_pwd_id_number',
        'is_solo_parent' => 'registration_is_solo_parent',
        'solo_parent_id_number' => 'registration_solo_parent_id_number',
        'is_ip_member' => 'registration_is_ip_member',
        'ip_group' => 'registration_ip_group',
        'is_4ps_beneficiary' => 'registration_is_4ps_beneficiary',
    ];

    $selectCols = [];
    foreach ($neededMap as $source => $target) {
        if (isset($appCols[$source])) {
            $selectCols[] = $source;
        }
    }
    if (empty($selectCols)) {
        return [];
    }

    $selectSql = '`' . implode('`,`', $selectCols) . '`';
    $statusSql = $statusCol ? " AND LOWER(TRIM(COALESCE(`{$statusCol}`, ''))) = 'approved'" : '';

    $row = null;
    if (isset($appCols['approved_resident_id']) && !empty($resident['id'])) {
        $row = $db->fetchOne(
            "SELECT {$selectSql}
             FROM resident_applications
             WHERE approved_resident_id = ? {$statusSql}
             ORDER BY id DESC
             LIMIT 1",
            [(int)$resident['id']]
        );
    }

    if (!$row
        && isset($appCols['first_name'])
        && isset($appCols['last_name'])
        && isset($appCols['birth_date'])
        && !empty($resident['first_name'])
        && !empty($resident['last_name'])
        && !empty($resident['birth_date'])) {
        $row = $db->fetchOne(
            "SELECT {$selectSql}
             FROM resident_applications
             WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(?))
               AND LOWER(TRIM(last_name)) = LOWER(TRIM(?))
               AND birth_date = ? {$statusSql}
             ORDER BY id DESC
             LIMIT 1",
            [
                (string)$resident['first_name'],
                (string)$resident['last_name'],
                (string)$resident['birth_date'],
            ]
        );
    }

    if (!$row) {
        return [];
    }

    $out = [];
    foreach ($neededMap as $source => $target) {
        if (array_key_exists($source, $row)) {
            $out[$target] = $row[$source];
        }
    }

    if (empty($out['registration_household_role']) && !empty($out['registration_relationship_to_head'])) {
        $out['registration_household_role'] = $out['registration_relationship_to_head'];
    }

    return $out;
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

    $monthly_income_raw = trim((string)($_POST['monthly_income'] ?? ''));
    $monthly_income_val = null;
    if ($monthly_income_raw !== '') {
        if (!is_numeric($monthly_income_raw)) {
            sendResponse(false, 'Monthly income must be a non-negative number.', null, 400);
            return;
        }
        $monthly_income_val = (float)$monthly_income_raw;
        if ($monthly_income_val < 0) {
            sendResponse(false, 'Monthly income cannot be negative.', null, 400);
            return;
        }
    }

    if (empty($first_name) || empty($last_name) || empty($birth_date) || empty($gender) || empty($civil_status) || empty($occupation) || empty($citizenship) || empty($contact_number) || empty($address) || empty($status)) {
        sendResponse(false, 'All fields are required except household and suffix', null, 400);
        return;
    }
    
    try {
        $db = Database::getInstance();
        ensureIndigentClassificationSchema($db);
        
        // Check if resident exists
        $existing = $db->fetchOne("SELECT id FROM residents WHERE id = ?", [$id]);
        if (!$existing) {
            sendResponse(false, 'Resident not found', null, 404);
            return;
        }
        
        $sets = [
            'first_name = ?',
            'middle_name = ?',
            'last_name = ?',
            'suffix = ?',
            'birth_date = ?',
            'gender = ?',
            'civil_status = ?',
            'occupation = ?',
            'citizenship = ?',
            'address = ?',
            'contact_number = ?',
            'household_id = ?',
            'status = ?',
        ];
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
        ];
        if (columnExists($db, 'residents', 'monthly_income')) {
            $sets[] = 'monthly_income = ?';
            $params[] = $monthly_income_val;
        }
        $params[] = $id;

        $sql = 'UPDATE residents SET ' . implode(', ', $sets) . ' WHERE id = ?';
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

function tableExists($db, $table) {
    $row = $db->fetchOne(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?",
        [$table]
    );
    return !empty($row) && (int)$row['cnt'] > 0;
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
