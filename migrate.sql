-- ============================================================
-- KHSMIS Migration: Upgrade existing students + payments tables
-- Run this AFTER db_setup.sql if tables already existed
-- ============================================================

USE karnhighschool;

-- ── students table ────────────────────────────────────────────
-- Add new columns if they don't already exist

ALTER TABLE students
  MODIFY COLUMN grade VARCHAR(30) NULL DEFAULT NULL,
  MODIFY COLUMN class_section VARCHAR(10) NULL DEFAULT NULL;

ALTER TABLE students
  ADD COLUMN IF NOT EXISTS user_id          INT UNSIGNED DEFAULT NULL AFTER id,
  ADD COLUMN IF NOT EXISTS admission_number VARCHAR(30)  DEFAULT NULL AFTER student_id,
  ADD COLUMN IF NOT EXISTS middle_name      VARCHAR(80)  DEFAULT NULL AFTER first_name,
  ADD COLUMN IF NOT EXISTS gender           VARCHAR(20)  NOT NULL DEFAULT 'Male' AFTER last_name,
  ADD COLUMN IF NOT EXISTS date_of_birth    DATE         DEFAULT NULL AFTER gender,
  ADD COLUMN IF NOT EXISTS nationality      VARCHAR(60)  NOT NULL DEFAULT 'Liberian' AFTER date_of_birth,
  ADD COLUMN IF NOT EXISTS photo            VARCHAR(255) DEFAULT NULL AFTER nationality,
  ADD COLUMN IF NOT EXISTS community        VARCHAR(100) DEFAULT NULL AFTER address,
  ADD COLUMN IF NOT EXISTS county           VARCHAR(60)  NOT NULL DEFAULT 'Nimba' AFTER community,
  ADD COLUMN IF NOT EXISTS district         VARCHAR(60)  DEFAULT NULL AFTER county,
  ADD COLUMN IF NOT EXISTS current_class_id INT UNSIGNED DEFAULT NULL AFTER district,
  ADD COLUMN IF NOT EXISTS current_grade_id INT UNSIGNED DEFAULT NULL AFTER current_class_id,
  ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED DEFAULT NULL AFTER current_grade_id,
  ADD COLUMN IF NOT EXISTS admission_date   DATE         DEFAULT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS graduation_date  DATE         DEFAULT NULL AFTER admission_date;

-- Add unique index on admission_number if not exists
ALTER TABLE students
  ADD UNIQUE INDEX IF NOT EXISTS uk_admission_number (admission_number);

-- Backfill current_grade_id from the grade VARCHAR column
UPDATE students s
JOIN grades g ON g.name = s.grade
SET s.current_grade_id = g.id
WHERE s.current_grade_id IS NULL AND s.grade IS NOT NULL AND s.grade != '';

-- Backfill academic_year_id from the academic_year VARCHAR column
UPDATE students s
JOIN academic_years ay ON ay.name = s.academic_year
SET s.academic_year_id = ay.id
WHERE s.academic_year_id IS NULL;

-- Add FK if not exists (ignore if constraint already there)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA='karnhighschool' AND TABLE_NAME='students'
    AND CONSTRAINT_NAME='fk_students_grade'
);
-- Use ALTER IGNORE so duplicate FK constraint doesn't break
ALTER TABLE students
  ADD CONSTRAINT fk_students_grade    FOREIGN KEY IF NOT EXISTS (current_grade_id) REFERENCES grades(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_students_class    FOREIGN KEY IF NOT EXISTS (current_class_id) REFERENCES classes(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_students_ay       FOREIGN KEY IF NOT EXISTS (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_students_user     FOREIGN KEY IF NOT EXISTS (user_id)          REFERENCES users(id) ON DELETE SET NULL;

-- ── payments table ────────────────────────────────────────────
ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS fee_structure_id INT UNSIGNED DEFAULT NULL AFTER student_id,
  ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED DEFAULT NULL AFTER academic_year;

-- Backfill academic_year_id
UPDATE payments p
JOIN academic_years ay ON ay.name = p.academic_year
SET p.academic_year_id = ay.id
WHERE p.academic_year_id IS NULL;

ALTER TABLE payments
  ADD CONSTRAINT fk_pay_fee_structure FOREIGN KEY IF NOT EXISTS (fee_structure_id) REFERENCES fee_structures(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_pay_ay            FOREIGN KEY IF NOT EXISTS (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL;

-- ── Backfill sample student data with proper IDs ──────────────
UPDATE students SET
  student_id = CASE grade
    WHEN 'Grade 8'  THEN 'KHS-2024-0184'
    WHEN 'Grade 10' THEN 'KHS-2024-0183'
    WHEN 'Grade 5'  THEN 'KHS-2024-0182'
    WHEN 'Grade 11' THEN 'KHS-2024-0181'
    ELSE student_id END,
  admission_number = CASE grade
    WHEN 'Grade 8'  THEN 'ADM-2024-0184'
    WHEN 'Grade 10' THEN 'ADM-2024-0183'
    WHEN 'Grade 5'  THEN 'ADM-2024-0182'
    WHEN 'Grade 11' THEN 'ADM-2024-0181'
    ELSE admission_number END
WHERE grade IN ('Grade 8','Grade 10','Grade 5','Grade 11')
  AND (admission_number IS NULL OR admission_number = '');

-- Assign classes to sample students
UPDATE students s
JOIN classes c ON c.name = CONCAT(s.grade,'A') AND c.academic_year_id = (SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)
SET s.current_class_id = c.id
WHERE s.current_class_id IS NULL AND s.grade IS NOT NULL;

SELECT 'Migration complete.' AS status;
SELECT 'students' tbl, COUNT(*) rows FROM students
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'current_grade_id set', COUNT(*) FROM students WHERE current_grade_id IS NOT NULL
UNION ALL SELECT 'academic_year_id set', COUNT(*) FROM payments WHERE academic_year_id IS NOT NULL;

-- ── applications table new columns ───────────────────────────
ALTER TABLE applications
  ADD COLUMN IF NOT EXISTS user_id             INT UNSIGNED DEFAULT NULL AFTER id,
  ADD COLUMN IF NOT EXISTS grade_id            INT UNSIGNED DEFAULT NULL AFTER grade_applying_for,
  ADD COLUMN IF NOT EXISTS academic_year_id    INT UNSIGNED DEFAULT NULL AFTER academic_year,
  ADD COLUMN IF NOT EXISTS entrance_score      DECIMAL(6,2) DEFAULT NULL AFTER final_decision,
  ADD COLUMN IF NOT EXISTS entrance_passed     TINYINT(1)   DEFAULT NULL AFTER entrance_score,
  ADD COLUMN IF NOT EXISTS reviewed_by         INT UNSIGNED DEFAULT NULL AFTER entrance_passed,
  ADD COLUMN IF NOT EXISTS reviewed_at         DATETIME     DEFAULT NULL AFTER reviewed_by,
  ADD COLUMN IF NOT EXISTS decision_by         INT UNSIGNED DEFAULT NULL AFTER reviewed_at,
  ADD COLUMN IF NOT EXISTS decision_at         DATETIME     DEFAULT NULL AFTER decision_by,
  ADD COLUMN IF NOT EXISTS entrance_exam_date  DATE         DEFAULT NULL AFTER decision_at,
  ADD COLUMN IF NOT EXISTS entrance_exam_time  VARCHAR(20)  DEFAULT NULL AFTER entrance_exam_date,
  ADD COLUMN IF NOT EXISTS entrance_letter_ref VARCHAR(30)  DEFAULT NULL AFTER entrance_exam_time,
  ADD COLUMN IF NOT EXISTS internal_notes      TEXT         DEFAULT NULL AFTER entrance_letter_ref;

UPDATE applications a JOIN academic_years ay ON ay.name=a.academic_year SET a.academic_year_id=ay.id WHERE a.academic_year_id IS NULL;
UPDATE applications a JOIN grades g ON g.name=a.grade_applying_for SET a.grade_id=g.id WHERE a.grade_id IS NULL;
