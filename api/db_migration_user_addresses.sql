-- =============================================================
-- User-Owned Addresses Migration
-- Run once in phpMyAdmin or MySQL CLI on Joula_AWS database.
-- =============================================================

-- Add uploaded_by FK to Addresses_AWS
-- NULL = legacy row (not owned by any specific user)
ALTER TABLE Addresses_AWS
    ADD COLUMN IF NOT EXISTS uploaded_by INT DEFAULT NULL;

-- Index for fast per-user filtering in missing_coordinates endpoint
CREATE INDEX IF NOT EXISTS idx_addresses_uploaded_by
    ON Addresses_AWS (uploaded_by);

-- =============================================================
-- DONE
-- =============================================================
