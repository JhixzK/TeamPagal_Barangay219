<?php
/**
 * E-Barangay Information Management System
 * Public Entry Point - Redirect to Homepage
 */

define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../includes/auth-check.php';

// Redirect to homepage
header('Location: ' . BASE_URL . 'home.php');
exit();
