-- Resident Incident Reporting (Blotter) Module
-- Migration: 016_resident_blotter_records.sql

CREATE TABLE IF NOT EXISTS blotter_records (
    id INT(11) NOT NULL AUTO_INCREMENT,
    reference_no VARCHAR(20) NOT NULL,
    complainant_id INT(11) NOT NULL,
    incident_type ENUM('physical_assault','theft','threat','harassment','property_damage','domestic_dispute','public_disturbance','other') NOT NULL DEFAULT 'other',
    incident_location VARCHAR(255) NOT NULL,
    incident_datetime DATETIME NOT NULL,
    narrative TEXT NOT NULL,
    status ENUM('pending','investigation','mediation','settled','dismissed') NOT NULL DEFAULT 'pending',
    respondent_name VARCHAR(255) DEFAULT NULL,
    respondent_id INT(11) DEFAULT NULL,
    witnesses TEXT DEFAULT NULL,
    is_confidential TINYINT(1) NOT NULL DEFAULT 0,
    action_requested VARCHAR(50) DEFAULT NULL,
    evidence_path VARCHAR(255) DEFAULT NULL,
    admin_updates TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_blotter_reference_no (reference_no),
    KEY idx_blotter_complainant (complainant_id),
    KEY idx_blotter_status (status),
    KEY idx_blotter_incident_datetime (incident_datetime),
    KEY idx_blotter_respondent_id (respondent_id),
    CONSTRAINT fk_blotter_records_complainant
        FOREIGN KEY (complainant_id) REFERENCES residents(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_blotter_records_respondent
        FOREIGN KEY (respondent_id) REFERENCES residents(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
