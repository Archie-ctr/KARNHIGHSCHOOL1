-- ============================================================
-- KHSMIS — Academic Year Priority Migration
-- Adds academic_year_id to tables that lacked it
-- Run once against karnhighschool database
-- ============================================================
USE karnhighschool;

-- 1. discipline_records — add academic_year_id
ALTER TABLE discipline_records
  ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED NULL
    COMMENT 'Academic year this incident occurred in',
  ADD INDEX IF NOT EXISTS idx_dr_ay (academic_year_id);

-- Back-fill from incident_date range matching academic years
UPDATE discipline_records dr
JOIN academic_years ay
  ON dr.incident_date BETWEEN ay.start_date AND ay.end_date
SET dr.academic_year_id = ay.id
WHERE dr.academic_year_id IS NULL;

-- Any still NULL → assign to current year
UPDATE discipline_records
SET academic_year_id = (SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)
WHERE academic_year_id IS NULL;

-- 2. applications — add academic_year_id
ALTER TABLE applications
  ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED NULL
    COMMENT 'Academic year this application targets',
  ADD INDEX IF NOT EXISTS idx_app_ay (academic_year_id);

-- Back-fill from academic_year text column if it exists
UPDATE applications a
JOIN academic_years ay ON ay.name = a.academic_year
SET a.academic_year_id = ay.id
WHERE a.academic_year_id IS NULL AND a.academic_year IS NOT NULL;

-- Any still NULL → assign to current year
UPDATE applications
SET academic_year_id = (SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)
WHERE academic_year_id IS NULL;

-- 3. library_transactions — add academic_year_id
-- (library_borrowings is the table used in portal; library_transactions in admin)
-- Handle whichever exists
ALTER TABLE library_transactions
  ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED NULL
    COMMENT 'Academic year of this borrowing',
  ADD INDEX IF NOT EXISTS idx_lt_ay (academic_year_id);

-- Back-fill from issued_at date
UPDATE library_transactions lt
JOIN academic_years ay
  ON DATE(lt.issued_at) BETWEEN ay.start_date AND ay.end_date
SET lt.academic_year_id = ay.id
WHERE lt.academic_year_id IS NULL;

-- Any still NULL → current year
UPDATE library_transactions
SET academic_year_id = (SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)
WHERE academic_year_id IS NULL;

-- 4. library_borrowings (portal table) — only if it exists
-- (Skip if not present — portal may use library_transactions)
SET @lb_exists = (SELECT COUNT(*) FROM information_schema.tables
  WHERE table_schema='karnhighschool' AND table_name='library_borrowings');

SET @sql = IF(@lb_exists > 0,
  "ALTER TABLE library_borrowings ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED NULL",
  "SELECT 'library_borrowings table not found - skipped'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql2 = IF(@lb_exists > 0,
  "UPDATE library_borrowings lb JOIN academic_years ay ON DATE(lb.borrowed_at) BETWEEN ay.start_date AND ay.end_date SET lb.academic_year_id = ay.id WHERE lb.academic_year_id IS NULL",
  "SELECT 1");
PREPARE stmt FROM @sql2; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql3 = IF(@lb_exists > 0,
  "UPDATE library_borrowings SET academic_year_id=(SELECT id FROM academic_years WHERE is_current=1 LIMIT 1) WHERE academic_year_id IS NULL",
  "SELECT 1");
PREPARE stmt FROM @sql3; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Verify
SELECT 'discipline_records' tbl,
       COUNT(*) total,
       SUM(academic_year_id IS NULL) missing_ay
FROM discipline_records
UNION ALL
SELECT 'applications',
       COUNT(*), SUM(academic_year_id IS NULL)
FROM applications
UNION ALL
SELECT 'library_transactions',
       COUNT(*), SUM(academic_year_id IS NULL)
FROM library_transactions;
