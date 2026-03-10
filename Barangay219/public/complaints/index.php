<?php
define('ACCESS_ALLOWED', true);
require_once __DIR__ . '/../../includes/resident-complaints.php';

residentComplaintsRequireResident();

header('Location: ' . BASE_URL . 'complaints/my_complaints.php');
exit();
