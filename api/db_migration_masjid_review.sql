-- =============================================================
-- Masjid review/user ownership migration
-- Run once in phpMyAdmin or MySQL CLI.
-- Compatible with MySQL versions that do not support ADD COLUMN IF NOT EXISTS.
-- =============================================================

SET @db_name = DATABASE();

-- Ensure Created_by exists
SELECT COUNT(*) INTO @has_created_by
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'Masjids_AWS'
  AND COLUMN_NAME = 'Created_by';

SET @sql_created_by = IF(
  @has_created_by = 0,
  'ALTER TABLE Masjids_AWS ADD COLUMN Created_by INT DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt_created_by FROM @sql_created_by;
EXECUTE stmt_created_by;
DEALLOCATE PREPARE stmt_created_by;

-- Ensure Clear exists for review/approval workflow (0=pending, 1=approved)
SELECT COUNT(*) INTO @has_clear
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'Masjids_AWS'
  AND COLUMN_NAME = 'Clear';

SET @sql_clear = IF(
  @has_clear = 0,
  'ALTER TABLE Masjids_AWS ADD COLUMN `Clear` TINYINT(1) NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE stmt_clear FROM @sql_clear;
EXECUTE stmt_clear;
DEALLOCATE PREPARE stmt_clear;

-- Ensure index on Created_by for per-user queries
SELECT COUNT(*) INTO @has_idx_created_by
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'Masjids_AWS'
  AND INDEX_NAME = 'idx_masjids_created_by';

SET @sql_idx_created_by = IF(
  @has_idx_created_by = 0,
  'CREATE INDEX idx_masjids_created_by ON Masjids_AWS (Created_by)',
  'SELECT 1'
);
PREPARE stmt_idx_created_by FROM @sql_idx_created_by;
EXECUTE stmt_idx_created_by;
DEALLOCATE PREPARE stmt_idx_created_by;

-- Ensure index on Clear for pending review list
SELECT COUNT(*) INTO @has_idx_clear
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'Masjids_AWS'
  AND INDEX_NAME = 'idx_masjids_clear';

SET @sql_idx_clear = IF(
  @has_idx_clear = 0,
  'CREATE INDEX idx_masjids_clear ON Masjids_AWS (`Clear`)',
  'SELECT 1'
);
PREPARE stmt_idx_clear FROM @sql_idx_clear;
EXECUTE stmt_idx_clear;
DEALLOCATE PREPARE stmt_idx_clear;
