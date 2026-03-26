<?php
if (!defined('ACCESS_ALLOWED')) {
    define('ACCESS_ALLOWED', true);
}

function complaintCategoriesList(): array {
    return [
        'Sanitation & Cleanliness',
        'Noise',
        'Street & Road Issues',
        'Street Lighting',
        'Drainage & Flooding',
        'Public Facilities',
        'Parking & Traffic',
        'Animals & Pets',
    ];
}

function complaintCategoriesIsValid(?string $category): bool {
    $category = trim((string)$category);
    return $category !== '' && in_array($category, complaintCategoriesList(), true);
}
