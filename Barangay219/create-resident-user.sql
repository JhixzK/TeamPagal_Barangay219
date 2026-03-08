-- Create Resident User Account for Testing
-- E-Barangay Information Management System

USE barangay_db;

-- Insert a sample resident if not exists
INSERT INTO residents (
    first_name, middle_name, last_name, suffix,
    date_of_birth, place_of_birth, gender, civil_status,
    house_no, street, barangay, city, province,
    mobile_number, email, national_id,
    occupation, employment_status, employer_name,
    household_head_name, relationship_to_head, household_members_count,
    years_of_residency, emergency_contact_name, emergency_contact_number,
    verification_status, status, created_at
) VALUES (
    'Juan', 'Santos', 'Dela Cruz', '',
    '1995-06-14', 'Tondo, Manila', 'male', 'single',
    'Blk 12 Lot 8', 'Isla Puting Bato', 'Barangay 219', 'Manila', 'Metro Manila',
    '+63 917 123 4567', 'juandelacruz@email.com', 'PHL-1234-5678-9012',
    'Administrative Assistant', 'employed', 'City Logistics Services',
    'Pedro Dela Cruz', 'son', 5,
    12, 'Maria Dela Cruz', '+63 918 765 4321',
    'verified', 'active', NOW()
) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);

-- Get the resident ID (either newly inserted or existing)
SET @resident_id = LAST_INSERT_ID();

-- Create user account for the resident
-- Username: resident
-- Password: resident123
-- The password hash below is bcrypt hash of 'resident123'
INSERT INTO users (
    username, 
    password, 
    email, 
    role, 
    status, 
    resident_id,
    created_at
) VALUES (
    'resident',
    '$2y$10$5xKqLrH8F6J9vYm8xYZKUe2N3WxB7qF5LjK9aH7fN1mC3pD4eE5fG',
    'juandelacruz@email.com',
    'resident',
    'active',
    @resident_id,
    NOW()
) ON DUPLICATE KEY UPDATE 
    resident_id = @resident_id,
    status = 'active';

-- Display created account info
SELECT 
    'Resident User Account Created Successfully!' AS message,
    'Username: resident' AS username,
    'Password: resident123' AS password,
    'Role: resident' AS role,
    @resident_id AS resident_id;
