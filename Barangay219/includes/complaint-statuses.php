<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

function complaintStatusCodes(): array {
    return ['pending', 'approved', 'assigned', 'in_progress', 'resolved', 'rejected'];
}

function complaintStatusLabels(): array {
    return [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'assigned' => 'Assigned',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'rejected' => 'Rejected',
    ];
}

function complaintStatusLabel(string $code): string {
    $code = strtolower(trim($code));
    $map = complaintStatusLabels();
    return $map[$code] ?? ucfirst(str_replace('_', ' ', $code));
}

function complaintStatusIsValid(?string $status): bool {
    $status = strtolower(trim((string)$status));
    return $status !== '' && in_array($status, complaintStatusCodes(), true);
}

function complaintStatusNormalize(?string $status): string {
    $status = strtolower(trim((string)$status));
    return complaintStatusIsValid($status) ? $status : 'pending';
}
