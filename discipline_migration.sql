-- ============================================================
-- DISCIPLINE PORTAL MIGRATION
-- Extends discipline_records with portal columns and
-- creates missing auxiliary tables.
-- Safe to run multiple times (IF NOT EXISTS / IF NOT COLUMN).
-- ============================================================

-- 1. Extend discipline_records --------------------------------
ALTER TABLE discipline_records
  ADD COLUMN IF NOT EXISTS date_occurred         DATE         NULL,
  ADD COLUMN IF NOT EXISTS violation_type        VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS severity              VARCHAR(20)  NOT NULL DEFAULT 'minor',
  ADD COLUMN IF NOT EXISTS location              VARCHAR(150) NULL,
  ADD COLUMN IF NOT EXISTS witnesses             TEXT         NULL,
  ADD COLUMN IF NOT EXISTS status                VARCHAR(30)  NOT NULL DEFAULT 'open',
  ADD COLUMN IF NOT EXISTS reported_by           INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS parent_notified       TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS parent_notification_date  DATETIME NULL,
  ADD COLUMN IF NOT EXISTS parent_notification_notes TEXT     NULL,
  ADD COLUMN IF NOT EXISTS investigation_notes   TEXT         NULL,
  ADD COLUMN IF NOT EXISTS investigation_officer VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS investigation_started DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS investigation_closed  DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS escalated_to          VARCHAR(30)  NULL,
  ADD COLUMN IF NOT EXISTS escalated_at          DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS updated_at            DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  ADD INDEX IF NOT EXISTS idx_dr_status   (status),
  ADD INDEX IF NOT EXISTS idx_dr_severity (severity);

-- Back-fill date_occurred from incident_date where empty
UPDATE discipline_records
SET date_occurred = incident_date
WHERE date_occurred IS NULL AND incident_date IS NOT NULL;

-- Back-fill violation_type from category where empty
UPDATE discipline_records
SET violation_type = category
WHERE violation_type IS NULL AND category IS NOT NULL;

-- Back-fill status from resolved flag
UPDATE discipline_records
SET status = IF(resolved=1,'resolved','open')
WHERE status = 'open' AND resolved = 1;

-- 2. discipline_warnings -------------------------------------
CREATE TABLE IF NOT EXISTS discipline_warnings (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  incident_id      INT UNSIGNED NULL,
  warning_type     VARCHAR(60)  NOT NULL DEFAULT 'Verbal Warning',
  level            TINYINT      NOT NULL DEFAULT 1,
  description      TEXT         NOT NULL,
  action_required  TEXT         NULL,
  follow_up_date   DATE         NULL,
  issued_by        INT UNSIGNED NOT NULL,
  issued_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved         TINYINT(1)   NOT NULL DEFAULT 0,
  resolved_at      DATETIME     NULL,
  resolution_notes TEXT         NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_dw_student (student_id),
  INDEX idx_dw_ay      (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. discipline_actions (detention, counselling, suspension, etc.)
CREATE TABLE IF NOT EXISTS discipline_actions (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  incident_id      INT UNSIGNED NULL,
  action_type      VARCHAR(60)  NOT NULL,
  -- e.g. Warning|Detention|Counselling|Suspension|Community Service|Written Apology|Expulsion|Other
  description      TEXT         NULL,
  start_date       DATE         NULL,
  end_date         DATE         NULL,
  duration_days    INT          NULL,
  assigned_by      INT UNSIGNED NOT NULL,
  assigned_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status           VARCHAR(20)  NOT NULL DEFAULT 'active',
  completed_at     DATETIME     NULL,
  notes            TEXT         NULL,
  INDEX idx_da_student (student_id),
  INDEX idx_da_ay      (academic_year_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. discipline_case_notes -----------------------------------
CREATE TABLE IF NOT EXISTS discipline_case_notes (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  incident_id INT UNSIGNED NOT NULL,
  note_type   VARCHAR(40)  NOT NULL DEFAULT 'general',
  -- general|witness|evidence|investigation|resolution
  note        TEXT         NOT NULL,
  added_by    INT UNSIGNED NOT NULL,
  added_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_private  TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (incident_id) REFERENCES discipline_records(id) ON DELETE CASCADE,
  INDEX idx_dcn_incident (incident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. discipline_recommendations ------------------------------
CREATE TABLE IF NOT EXISTS discipline_recommendations (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id          INT UNSIGNED NOT NULL,
  academic_year_id    INT UNSIGNED NOT NULL,
  incident_id         INT UNSIGNED NULL,
  recommendation_type VARCHAR(60)  NOT NULL,
  description         TEXT         NOT NULL,
  urgency             VARCHAR(20)  NOT NULL DEFAULT 'medium',
  recommended_by      INT UNSIGNED NOT NULL,
  reviewed_by         INT UNSIGNED NULL,
  status              VARCHAR(20)  NOT NULL DEFAULT 'pending',
  decision_notes      TEXT         NULL,
  decided_at          DATETIME     NULL,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_drec_student (student_id),
  INDEX idx_drec_ay      (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. parent_notifications ------------------------------------
CREATE TABLE IF NOT EXISTS parent_notifications (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id        INT UNSIGNED NOT NULL,
  academic_year_id  INT UNSIGNED NOT NULL,
  incident_id       INT UNSIGNED NULL,
  notification_type VARCHAR(40)  NOT NULL DEFAULT 'call',
  message           TEXT         NULL,
  parent_response   TEXT         NULL,
  meeting_date      DATETIME     NULL,
  meeting_notes     TEXT         NULL,
  meeting_attended  TINYINT(1)   NULL,
  sent_by           INT UNSIGNED NOT NULL,
  sent_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at      DATETIME     NULL,
  INDEX idx_pn_student  (student_id),
  INDEX idx_pn_incident (incident_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
