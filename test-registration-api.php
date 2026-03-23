<?php
/**
 * Test script to verify registration fields are exposed in residents API
 */

// Set up session for auth check
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['permissions'] = ['residents' => ['can_view' => true]];

define('ACCESS_ALLOWED', true);

require_once __DIR__ . '/Barangay219/config/database.php';

// Function to check module access (from auth-check)
function canPerformModulePermission($module, $permission) {
    return true; // Skip for test
}

function requireLogin() {}
function requireModuleAccess($module) {}
function sendResponse($success, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_PRETTY_PRINT);
    exit;
}

// Get a resident
try {
    $db = Database::getInstance();
    
    // Get first resident
    $resident = $db->fetchOne("SELECT * FROM residents LIMIT 1");
    $residentId = $resident['id'];
    
    echo "=== Testing Registration Fields in Residents API ===\n\n";
    echo "Testing with Resident ID: $residentId\n";
    echo "Resident Name: {$resident['first_name']} {$resident['last_name']}\n\n";
    
    // Include the API file and test it
    $_GET['action'] = 'get';
    $_GET['id'] = $residentId;
    
    // Manually test the API query
    $hasHouseholdIdCode = true;
    $hasFamilyCode = false;
    $hasResidentFamilyHeadCode = true;
    $hasFamilyHeadCode = false;
    
    $householdCodeExpr = 'h.household_id_code AS household_code,';
    $familyHeadCodeExpr = 'r.family_head_code AS family_head_code,';
    
    // Build the registration expressions
    include __DIR__ . '/Barangay219/api/resident.php';
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
