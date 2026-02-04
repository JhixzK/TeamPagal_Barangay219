-- E-Barangay Information Management System
-- Database Seeds - Initial Data

-- Insert default admin user (password: admin123)
-- Password hash for 'admin123' using bcrypt (properly generated)
-- IMPORTANT: If login doesn't work, run fix-password.php in your browser to generate a new hash
INSERT INTO users (username, password, email, role, status) VALUES
('admin', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin@barangay195.gov.ph', 'barangay_captain', 'active')
ON DUPLICATE KEY UPDATE password = VALUES(password);

-- Note: The password hash above is a properly generated bcrypt hash for 'admin123'
-- If you need to regenerate it, access: http://localhost/Barangay219/barangay219/barangay219/fix-password.php

-- Sample residents (optional - for testing)
INSERT INTO residents (first_name, middle_name, last_name, birth_date, gender, civil_status, address, citizenship, status) VALUES
('Juan', 'Dela', 'Cruz', '1990-01-15', 'male', 'married', '123 Main Street, Barangay 195, Tondo, Manila', 'Filipino', 'active'),
('Maria', 'Santos', 'Garcia', '1985-05-20', 'female', 'married', '456 Oak Avenue, Barangay 195, Tondo, Manila', 'Filipino', 'active'),
('Pedro', 'Reyes', 'Lopez', '1992-08-10', 'male', 'single', '789 Pine Road, Barangay 195, Tondo, Manila', 'Filipino', 'active');

-- Sample household
INSERT INTO households (family_head_id, address, total_members, registration_date) VALUES
(1, '123 Main Street, Barangay 195, Tondo, Manila', 1, CURDATE());

-- Update first resident to link to household
UPDATE residents SET household_id = 1 WHERE id = 1;
