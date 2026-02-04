<?php
/**
 * E-Barangay Information Management System
 * Root redirect to public folder
 */

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
header('Location: ' . $protocol . '://' . $host . $path . '/public/');
exit();
