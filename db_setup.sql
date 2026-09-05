-- ============================================================
-- KARN HIGH SCHOOL MANAGEMENT & INFORMATION SYSTEM (KHSMIS)
-- Full Database Schema — MySQL / MariaDB (XAMPP)
-- Run once in phpMyAdmin or: mysql -u root < db_setup.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS karnhighschool
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE karnhighschool;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. SCHOOL SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS school_settings (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key   VARCHAR(80)  NOT NULL UNIQUE,
  setting_value TEXT         DEFAULT NULL,
  setting_group VARCHAR(40)  NOT NULL DEFAULT 'general',
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 2. ROLES & PERMISSIONS (RBAC)
-- ============================================================
CREATE TABLE IF NOT EXISTS roles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(40)  NOT NULL UNIQUE,
  label       VARCHAR(80)  NOT NULL,
  description TEXT         DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL UNIQUE,
  label       VARCHAR(120) NOT NULL,
  module      VARCHAR(40)  NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3. USERS (unified — staff + students + parents + applicants)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(120) DEFAULT NULL,
  phone         VARCHAR(30)  DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id       INT UNSIGNED NOT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    DATETIME     DEFAULT NULL,
  photo         VARCHAR(255) DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_email (email),
  FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 4. ACADEMIC YEARS
-- ============================================================
CREATE TABLE IF NOT EXISTS academic_years (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(20)  NOT NULL UNIQUE,   -- e.g. 2026/2027
  start_date  DATE         NOT NULL,
  end_date    DATE         NOT NULL,
  is_current  TINYINT(1)   NOT NULL DEFAULT 0,
  status      VARCHAR(20)  NOT NULL DEFAULT 'upcoming', -- upcoming|active|closed
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 5. SEMESTERS
-- ============================================================
CREATE TABLE IF NOT EXISTS semesters (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  name             VARCHAR(40)  NOT NULL,  -- Semester 1 | Semester 2
  sequence         TINYINT      NOT NULL DEFAULT 1,
  start_date       DATE         DEFAULT NULL,
  end_date         DATE         DEFAULT NULL,
  is_current       TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 6. PERIODS (assessment periods within a semester)
-- ============================================================
CREATE TABLE IF NOT EXISTS periods (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  semester_id INT UNSIGNED NOT NULL,
  name        VARCHAR(40)  NOT NULL,  -- 1st Period | 2nd Period | Semester Exam
  sequence    TINYINT      NOT NULL DEFAULT 1,
  type        VARCHAR(20)  NOT NULL DEFAULT 'period', -- period|exam
  is_current  TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. GRADES (ABC/KG through Grade 12)
-- ============================================================
CREATE TABLE IF NOT EXISTS grades (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(30)  NOT NULL UNIQUE,  -- Grade 1, Grade 10, ABC/KG
  sequence   TINYINT      NOT NULL DEFAULT 0,
  level      VARCHAR(20)  NOT NULL DEFAULT 'secondary', -- early|primary|junior|senior
  is_active  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. CLASSES (Grade + Section)
-- ============================================================
CREATE TABLE IF NOT EXISTS classes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  grade_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  name             VARCHAR(40)  NOT NULL,  -- Grade 10A
  section          VARCHAR(10)  DEFAULT NULL,
  teacher_id       INT UNSIGNED DEFAULT NULL, -- class teacher (FK to teachers)
  room             VARCHAR(30)  DEFAULT NULL,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (grade_id)         REFERENCES grades(id),
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 9. SUBJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS subjects (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(20)  DEFAULT NULL,
  name       VARCHAR(100) NOT NULL,
  short_name VARCHAR(20)  DEFAULT NULL,
  category   VARCHAR(40)  DEFAULT NULL,  -- core|elective|extracurricular
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. TEACHERS
-- ============================================================
CREATE TABLE IF NOT EXISTS teachers (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED DEFAULT NULL,
  teacher_id       VARCHAR(30)  NOT NULL UNIQUE,
  first_name       VARCHAR(80)  NOT NULL,
  last_name        VARCHAR(80)  NOT NULL,
  gender           VARCHAR(20)  DEFAULT NULL,
  phone            VARCHAR(30)  DEFAULT NULL,
  email            VARCHAR(120) DEFAULT NULL,
  photo            VARCHAR(255) DEFAULT NULL,
  qualification    VARCHAR(150) DEFAULT NULL,
  specialization   VARCHAR(150) DEFAULT NULL,
  employment_date  DATE         DEFAULT NULL,
  status           VARCHAR(20)  NOT NULL DEFAULT 'Active',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- link class teacher
ALTER TABLE classes ADD CONSTRAINT fk_class_teacher
  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;

-- ============================================================
-- 11. TEACHER ASSIGNMENTS (teacher ↔ subject ↔ class)
-- ============================================================
CREATE TABLE IF NOT EXISTS teacher_assignments (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id       INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_assignment (teacher_id, subject_id, class_id, academic_year_id),
  FOREIGN KEY (teacher_id)       REFERENCES teachers(id)       ON DELETE CASCADE,
  FOREIGN KEY (subject_id)       REFERENCES subjects(id)       ON DELETE CASCADE,
  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. CLASS SUBJECTS (subjects offered in a class)
-- ============================================================
CREATE TABLE IF NOT EXISTS class_subjects (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id         INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_class_subject (class_id, subject_id, academic_year_id),
  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE CASCADE,
  FOREIGN KEY (subject_id)       REFERENCES subjects(id)       ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 13. GUARDIANS
-- ============================================================
CREATE TABLE IF NOT EXISTS guardians (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED DEFAULT NULL,
  first_name   VARCHAR(80)  NOT NULL,
  last_name    VARCHAR(80)  NOT NULL,
  relationship VARCHAR(40)  NOT NULL DEFAULT 'Guardian',
  phone        VARCHAR(30)  NOT NULL,
  email        VARCHAR(120) DEFAULT NULL,
  address      TEXT         DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 14. STUDENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED DEFAULT NULL,
  student_id       VARCHAR(30)  NOT NULL UNIQUE,
  admission_number VARCHAR(30)  DEFAULT NULL UNIQUE,
  first_name       VARCHAR(80)  NOT NULL,
  middle_name      VARCHAR(80)  DEFAULT NULL,
  last_name        VARCHAR(80)  NOT NULL,
  gender           VARCHAR(20)  NOT NULL DEFAULT 'Male',
  date_of_birth    DATE         DEFAULT NULL,
  nationality      VARCHAR(60)  NOT NULL DEFAULT 'Liberian',
  photo            VARCHAR(255) DEFAULT NULL,
  phone            VARCHAR(30)  DEFAULT NULL,
  email            VARCHAR(120) DEFAULT NULL,
  address          TEXT         DEFAULT NULL,
  community        VARCHAR(100) DEFAULT NULL,
  county           VARCHAR(60)  NOT NULL DEFAULT 'Nimba',
  district         VARCHAR(60)  DEFAULT NULL,
  current_class_id INT UNSIGNED DEFAULT NULL,
  current_grade_id INT UNSIGNED DEFAULT NULL,
  academic_year_id INT UNSIGNED DEFAULT NULL,
  status           VARCHAR(20)  NOT NULL DEFAULT 'Active',
  admission_date   DATE         DEFAULT NULL,
  graduation_date  DATE         DEFAULT NULL,
  application_id   INT UNSIGNED DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)          REFERENCES users(id)          ON DELETE SET NULL,
  FOREIGN KEY (current_class_id) REFERENCES classes(id)        ON DELETE SET NULL,
  FOREIGN KEY (current_grade_id) REFERENCES grades(id)         ON DELETE SET NULL,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
  INDEX idx_grade  (current_grade_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 15. STUDENT–GUARDIAN LINK
-- ============================================================
CREATE TABLE IF NOT EXISTS student_guardians (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id   INT UNSIGNED NOT NULL,
  guardian_id  INT UNSIGNED NOT NULL,
  is_primary   TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_sg (student_id, guardian_id),
  FOREIGN KEY (student_id)  REFERENCES students(id)  ON DELETE CASCADE,
  FOREIGN KEY (guardian_id) REFERENCES guardians(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 16. STUDENT DOCUMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS student_documents (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id  INT UNSIGNED NOT NULL,
  doc_type    VARCHAR(60)  NOT NULL,   -- birth_certificate|report_card|photo|other
  file_name   VARCHAR(255) NOT NULL,
  file_path   VARCHAR(500) NOT NULL,
  file_size   INT UNSIGNED DEFAULT NULL,
  mime_type   VARCHAR(80)  DEFAULT NULL,
  uploaded_by INT UNSIGNED DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 17. APPLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS applications (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_number     VARCHAR(30)  NOT NULL UNIQUE,
  user_id                INT UNSIGNED DEFAULT NULL,   -- applicant portal user
  first_name             VARCHAR(80)  NOT NULL,
  middle_name            VARCHAR(80)  DEFAULT NULL,
  last_name              VARCHAR(80)  NOT NULL,
  date_of_birth          DATE         NOT NULL,
  gender                 VARCHAR(20)  NOT NULL,
  nationality            VARCHAR(60)  NOT NULL DEFAULT 'Liberian',
  phone                  VARCHAR(30)  NOT NULL,
  email                  VARCHAR(120) DEFAULT NULL,
  current_address        TEXT         NOT NULL,
  community              VARCHAR(100) DEFAULT NULL,
  county                 VARCHAR(60)  NOT NULL DEFAULT 'Nimba',
  district               VARCHAR(60)  DEFAULT NULL,
  previous_school        VARCHAR(150) DEFAULT NULL,
  last_grade_completed   VARCHAR(30)  DEFAULT NULL,
  grade_applying_for     VARCHAR(30)  NOT NULL,
  grade_id               INT UNSIGNED DEFAULT NULL,
  academic_year_id       INT UNSIGNED DEFAULT NULL,
  academic_year          VARCHAR(20)  NOT NULL DEFAULT '2026/2027',
  guardian_name          VARCHAR(150) NOT NULL,
  guardian_relationship  VARCHAR(60)  NOT NULL,
  guardian_phone         VARCHAR(30)  NOT NULL,
  emergency_contact      VARCHAR(30)  DEFAULT NULL,
  status                 VARCHAR(60)  NOT NULL DEFAULT 'Application Submitted',
  document_status        VARCHAR(40)  NOT NULL DEFAULT 'Pending',
  entrance_status        VARCHAR(40)  NOT NULL DEFAULT 'Not scheduled',
  final_decision         VARCHAR(40)  NOT NULL DEFAULT 'Pending',
  entrance_score         DECIMAL(6,2) DEFAULT NULL,
  entrance_passed        TINYINT(1)   DEFAULT NULL,
  reviewed_by            INT UNSIGNED DEFAULT NULL,
  reviewed_at            DATETIME     DEFAULT NULL,
  decision_by            INT UNSIGNED DEFAULT NULL,
  decision_at            DATETIME     DEFAULT NULL,
  entrance_exam_date     DATE         DEFAULT NULL,
  entrance_exam_time     VARCHAR(20)  DEFAULT NULL,
  entrance_letter_ref    VARCHAR(30)  DEFAULT NULL,
  internal_notes         TEXT         DEFAULT NULL,
  created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (grade_id)         REFERENCES grades(id)         ON DELETE SET NULL,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
  INDEX idx_status  (status),
  INDEX idx_grade   (grade_applying_for),
  INDEX idx_phone   (phone),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 18. APPLICATION DOCUMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS application_documents (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  doc_type       VARCHAR(60)  NOT NULL,
  file_name      VARCHAR(255) NOT NULL,
  file_path      VARCHAR(500) NOT NULL,
  file_size      INT UNSIGNED DEFAULT NULL,
  mime_type      VARCHAR(80)  DEFAULT NULL,
  status         VARCHAR(20)  NOT NULL DEFAULT 'Pending', -- Pending|Verified|Rejected
  verified_by    INT UNSIGNED DEFAULT NULL,
  verified_at    DATETIME     DEFAULT NULL,
  notes          TEXT         DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 19. APPLICATION STATUS HISTORY
-- ============================================================
CREATE TABLE IF NOT EXISTS application_status_history (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  old_status     VARCHAR(60)  DEFAULT NULL,
  new_status     VARCHAR(60)  NOT NULL,
  changed_by     INT UNSIGNED DEFAULT NULL,
  notes          TEXT         DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 20. ENTRANCE EXAM CONFIGURATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS entrance_exams (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(150) NOT NULL,
  grade_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  duration_minutes INT          NOT NULL DEFAULT 60,
  total_questions  INT          NOT NULL DEFAULT 60,
  passing_score    DECIMAL(5,2) NOT NULL DEFAULT 70.00,
  start_datetime   DATETIME     DEFAULT NULL,
  end_datetime     DATETIME     DEFAULT NULL,
  randomize_q      TINYINT(1)   NOT NULL DEFAULT 1,
  randomize_a      TINYINT(1)   NOT NULL DEFAULT 1,
  show_result      VARCHAR(20)  NOT NULL DEFAULT 'immediate', -- immediate|manual
  allowed_attempts TINYINT      NOT NULL DEFAULT 1,
  security_level   VARCHAR(20)  NOT NULL DEFAULT 'standard', -- basic|standard|strict
  is_online        TINYINT(1)   NOT NULL DEFAULT 1,
  status           VARCHAR(20)  NOT NULL DEFAULT 'draft',   -- draft|active|closed
  instructions     TEXT         DEFAULT NULL,
  created_by       INT UNSIGNED DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (grade_id)         REFERENCES grades(id)         ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 21. ENTRANCE EXAM SECTIONS (per subject within an exam)
-- ============================================================
CREATE TABLE IF NOT EXISTS entrance_exam_sections (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_id        INT UNSIGNED NOT NULL,
  subject_id     INT UNSIGNED DEFAULT NULL,
  title          VARCHAR(100) NOT NULL,
  num_questions  INT          NOT NULL DEFAULT 20,
  marks_each     DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  sequence       TINYINT      NOT NULL DEFAULT 1,
  FOREIGN KEY (exam_id)    REFERENCES entrance_exams(id)   ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 22. QUESTION BANK
-- ============================================================
CREATE TABLE IF NOT EXISTS entrance_questions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id   INT UNSIGNED DEFAULT NULL,
  exam_id      INT UNSIGNED DEFAULT NULL,
  subject_id   INT UNSIGNED DEFAULT NULL,
  grade_id     INT UNSIGNED DEFAULT NULL,
  question     TEXT         NOT NULL,
  q_type       VARCHAR(20)  NOT NULL DEFAULT 'mcq', -- mcq|truefalse|short|essay
  difficulty   VARCHAR(20)  NOT NULL DEFAULT 'medium',
  marks        DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  correct_answer TEXT       DEFAULT NULL,
  explanation  TEXT         DEFAULT NULL,
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (section_id) REFERENCES entrance_exam_sections(id) ON DELETE SET NULL,
  FOREIGN KEY (exam_id)    REFERENCES entrance_exams(id)         ON DELETE SET NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id)               ON DELETE SET NULL,
  FOREIGN KEY (grade_id)   REFERENCES grades(id)                 ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 23. QUESTION OPTIONS (for MCQ)
-- ============================================================
CREATE TABLE IF NOT EXISTS entrance_question_options (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  option_text TEXT         NOT NULL,
  is_correct  TINYINT(1)   NOT NULL DEFAULT 0,
  sequence    TINYINT      NOT NULL DEFAULT 1,
  FOREIGN KEY (question_id) REFERENCES entrance_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 24. EXAM ATTEMPTS
-- ============================================================
CREATE TABLE IF NOT EXISTS entrance_exam_attempts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_id        INT UNSIGNED NOT NULL,
  application_id INT UNSIGNED NOT NULL,
  started_at     DATETIME     DEFAULT NULL,
  submitted_at   DATETIME     DEFAULT NULL,
  ip_address     VARCHAR(45)  DEFAULT NULL,
  user_agent     VARCHAR(500) DEFAULT NULL,
  score          DECIMAL(6,2) DEFAULT NULL,
  max_score      DECIMAL(6,2) DEFAULT NULL,
  percentage     DECIMAL(5,2) DEFAULT NULL,
  passed         TINYINT(1)   DEFAULT NULL,
  attempt_number TINYINT      NOT NULL DEFAULT 1,
  status         VARCHAR(20)  NOT NULL DEFAULT 'pending', -- pending|in_progress|submitted|graded
  UNIQUE KEY uq_attempt (exam_id, application_id, attempt_number),
  FOREIGN KEY (exam_id)        REFERENCES entrance_exams(id)  ON DELETE CASCADE,
  FOREIGN KEY (application_id) REFERENCES applications(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 25. EXAM ANSWERS
-- ============================================================
CREATE TABLE IF NOT EXISTS entrance_answers (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id  INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  answer_text TEXT         DEFAULT NULL,
  option_id   INT UNSIGNED DEFAULT NULL,
  is_correct  TINYINT(1)   DEFAULT NULL,
  marks_earned DECIMAL(5,2) DEFAULT NULL,
  saved_at    DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_answer (attempt_id, question_id),
  FOREIGN KEY (attempt_id)  REFERENCES entrance_exam_attempts(id)    ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES entrance_questions(id)        ON DELETE CASCADE,
  FOREIGN KEY (option_id)   REFERENCES entrance_question_options(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 26. ASSESSMENT CONFIGURATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS assessment_configs (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  grade_id         INT UNSIGNED DEFAULT NULL,  -- NULL = applies to all grades
  name             VARCHAR(80)  NOT NULL,       -- 1st Period, Semester Exam, etc.
  type             VARCHAR(20)  NOT NULL DEFAULT 'period', -- period|exam|annual
  sequence         TINYINT      NOT NULL DEFAULT 1,
  period_id        INT UNSIGNED DEFAULT NULL,
  max_marks        DECIMAL(6,2) NOT NULL DEFAULT 100.00,
  weight_percent   DECIMAL(5,2) NOT NULL DEFAULT 100.00,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
  FOREIGN KEY (grade_id)         REFERENCES grades(id)         ON DELETE SET NULL,
  FOREIGN KEY (period_id)        REFERENCES periods(id)        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 27. ASSESSMENT SCORES (marks entered by teachers)
-- ============================================================
CREATE TABLE IF NOT EXISTS assessment_scores (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id           INT UNSIGNED NOT NULL,
  class_id             INT UNSIGNED NOT NULL,
  subject_id           INT UNSIGNED NOT NULL,
  assessment_config_id INT UNSIGNED NOT NULL,
  academic_year_id     INT UNSIGNED NOT NULL,
  marks_obtained       DECIMAL(6,2) DEFAULT NULL,
  max_marks            DECIMAL(6,2) NOT NULL DEFAULT 100.00,
  remarks              VARCHAR(255) DEFAULT NULL,
  entered_by           INT UNSIGNED DEFAULT NULL,  -- teacher user_id
  submitted_at         DATETIME     DEFAULT NULL,
  approved_by          INT UNSIGNED DEFAULT NULL,
  approved_at          DATETIME     DEFAULT NULL,
  status               VARCHAR(20)  NOT NULL DEFAULT 'draft', -- draft|submitted|approved
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_score (student_id, subject_id, assessment_config_id, academic_year_id),
  FOREIGN KEY (student_id)           REFERENCES students(id)            ON DELETE CASCADE,
  FOREIGN KEY (class_id)             REFERENCES classes(id)             ON DELETE CASCADE,
  FOREIGN KEY (subject_id)           REFERENCES subjects(id)            ON DELETE CASCADE,
  FOREIGN KEY (assessment_config_id) REFERENCES assessment_configs(id)  ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id)     REFERENCES academic_years(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 28. GRADING SCALES
-- ============================================================
CREATE TABLE IF NOT EXISTS grading_scales (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  grade_letter     VARCHAR(5)   NOT NULL,
  min_percent      DECIMAL(5,2) NOT NULL,
  max_percent      DECIMAL(5,2) NOT NULL,
  grade_point      DECIMAL(4,2) NOT NULL DEFAULT 0.00,
  description      VARCHAR(60)  DEFAULT NULL,
  is_pass          TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 29. SEMESTER RESULTS
-- ============================================================
CREATE TABLE IF NOT EXISTS semester_results (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  semester_id      INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  period1          DECIMAL(6,2) DEFAULT NULL,
  period2          DECIMAL(6,2) DEFAULT NULL,
  period3          DECIMAL(6,2) DEFAULT NULL,
  sem_exam         DECIMAL(6,2) DEFAULT NULL,
  sem_average      DECIMAL(6,2) DEFAULT NULL,
  grade_letter     VARCHAR(5)   DEFAULT NULL,
  remarks          VARCHAR(100) DEFAULT NULL,
  status           VARCHAR(20)  NOT NULL DEFAULT 'draft',
  UNIQUE KEY uq_sem_result (student_id, subject_id, semester_id, academic_year_id),
  FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE CASCADE,
  FOREIGN KEY (subject_id)       REFERENCES subjects(id)       ON DELETE CASCADE,
  FOREIGN KEY (semester_id)      REFERENCES semesters(id)      ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 30. ANNUAL RESULTS
-- ============================================================
CREATE TABLE IF NOT EXISTS annual_results (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  subject_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  sem1_average     DECIMAL(6,2) DEFAULT NULL,
  sem2_average     DECIMAL(6,2) DEFAULT NULL,
  yearly_average   DECIMAL(6,2) DEFAULT NULL,
  grade_letter     VARCHAR(5)   DEFAULT NULL,
  class_position   INT UNSIGNED DEFAULT NULL,
  subject_position INT UNSIGNED DEFAULT NULL,
  passed           TINYINT(1)   DEFAULT NULL,
  status           VARCHAR(20)  NOT NULL DEFAULT 'draft',
  UNIQUE KEY uq_annual (student_id, subject_id, academic_year_id),
  FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE CASCADE,
  FOREIGN KEY (subject_id)       REFERENCES subjects(id)       ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 31. ATTENDANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  date             DATE         NOT NULL,
  status           VARCHAR(20)  NOT NULL DEFAULT 'Present', -- Present|Absent|Late|Excused
  remarks          VARCHAR(255) DEFAULT NULL,
  recorded_by      INT UNSIGNED DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_attendance (student_id, date),
  FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
  INDEX idx_date (date),
  INDEX idx_class_date (class_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 32. REPORT CARDS
-- ============================================================
CREATE TABLE IF NOT EXISTS report_cards (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  class_id         INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  days_present     INT          DEFAULT NULL,
  days_absent      INT          DEFAULT NULL,
  days_tardy       INT          DEFAULT NULL,
  attendance_pct   DECIMAL(5,2) DEFAULT NULL,
  yearly_average   DECIMAL(6,2) DEFAULT NULL,
  overall_position INT UNSIGNED DEFAULT NULL,
  class_position   INT UNSIGNED DEFAULT NULL,
  conduct          VARCHAR(40)  DEFAULT NULL,
  teacher_comment  TEXT         DEFAULT NULL,
  principal_comment TEXT        DEFAULT NULL,
  promotion_status VARCHAR(30)  NOT NULL DEFAULT 'Pending', -- Promoted|Not Promoted|Repeating|Graduated
  generated_at     DATETIME     DEFAULT NULL,
  published_at     DATETIME     DEFAULT NULL,
  status           VARCHAR(20)  NOT NULL DEFAULT 'draft',
  generated_by     INT UNSIGNED DEFAULT NULL,
  UNIQUE KEY uq_rc (student_id, academic_year_id),
  FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 33. PROMOTION RECORDS
-- ============================================================
CREATE TABLE IF NOT EXISTS promotion_records (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  from_grade_id    INT UNSIGNED NOT NULL,
  to_grade_id      INT UNSIGNED DEFAULT NULL,
  from_class_id    INT UNSIGNED DEFAULT NULL,
  to_class_id      INT UNSIGNED DEFAULT NULL,
  status           VARCHAR(30)  NOT NULL, -- Promoted|Not Promoted|Repeating|Graduated|Transferred|Withdrawn
  yearly_average   DECIMAL(6,2) DEFAULT NULL,
  processed_by     INT UNSIGNED DEFAULT NULL,
  processed_at     DATETIME     DEFAULT NULL,
  notes            TEXT         DEFAULT NULL,
  FOREIGN KEY (student_id)       REFERENCES students(id)  ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
  FOREIGN KEY (from_grade_id)    REFERENCES grades(id),
  FOREIGN KEY (to_grade_id)      REFERENCES grades(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 34. FEE STRUCTURES
-- ============================================================
CREATE TABLE IF NOT EXISTS fee_structures (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  grade_id         INT UNSIGNED DEFAULT NULL, -- NULL = all grades
  fee_type         VARCHAR(60)  NOT NULL,   -- Tuition|Registration|Exam|Development|Computer|Library|Uniform|Other
  amount           DECIMAL(12,2) NOT NULL,
  currency         VARCHAR(5)   NOT NULL DEFAULT 'LRD',
  due_date         DATE         DEFAULT NULL,
  description      TEXT         DEFAULT NULL,
  is_mandatory     TINYINT(1)   NOT NULL DEFAULT 1,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
  FOREIGN KEY (grade_id)         REFERENCES grades(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 35. PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS payments (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_number VARCHAR(30)  NOT NULL UNIQUE,
  student_id     INT UNSIGNED NOT NULL,
  fee_structure_id INT UNSIGNED DEFAULT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  currency       VARCHAR(5)   NOT NULL DEFAULT 'LRD',
  payment_method VARCHAR(40)  NOT NULL DEFAULT 'Cash',
  payment_date   DATE         NOT NULL,
  academic_year_id INT UNSIGNED DEFAULT NULL,
  term           VARCHAR(30)  DEFAULT NULL,
  notes          TEXT         DEFAULT NULL,
  recorded_by    INT UNSIGNED DEFAULT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id)        REFERENCES students(id)        ON DELETE CASCADE,
  FOREIGN KEY (fee_structure_id)  REFERENCES fee_structures(id)  ON DELETE SET NULL,
  FOREIGN KEY (academic_year_id)  REFERENCES academic_years(id)  ON DELETE SET NULL,
  INDEX idx_student (student_id),
  INDEX idx_date    (payment_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 36. LIBRARY BOOKS
-- ============================================================
CREATE TABLE IF NOT EXISTS library_books (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  isbn        VARCHAR(30)  DEFAULT NULL,
  title       VARCHAR(200) NOT NULL,
  author      VARCHAR(150) DEFAULT NULL,
  category    VARCHAR(60)  DEFAULT NULL,
  publisher   VARCHAR(100) DEFAULT NULL,
  year        YEAR         DEFAULT NULL,
  total_copies INT         NOT NULL DEFAULT 1,
  available   INT          NOT NULL DEFAULT 1,
  location    VARCHAR(60)  DEFAULT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 37. LIBRARY TRANSACTIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS library_transactions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id      INT UNSIGNED NOT NULL,
  student_id   INT UNSIGNED NOT NULL,
  issued_by    INT UNSIGNED DEFAULT NULL,
  issued_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_date     DATE         NOT NULL,
  returned_at  DATETIME     DEFAULT NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'Issued', -- Issued|Returned|Overdue
  fine_amount  DECIMAL(8,2) DEFAULT 0.00,
  notes        TEXT         DEFAULT NULL,
  FOREIGN KEY (book_id)    REFERENCES library_books(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 38. DISCIPLINE RECORDS
-- ============================================================
CREATE TABLE IF NOT EXISTS discipline_records (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id   INT UNSIGNED NOT NULL,
  incident_date DATE        NOT NULL,
  category     VARCHAR(60)  NOT NULL,   -- Misconduct|Absence|Cheating|Other
  description  TEXT         NOT NULL,
  action_taken VARCHAR(60)  NOT NULL,   -- Warning|Suspension|Counseling|Other
  details      TEXT         DEFAULT NULL,
  resolved     TINYINT(1)   NOT NULL DEFAULT 0,
  recorded_by  INT UNSIGNED DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 39. ANNOUNCEMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  message      TEXT         NOT NULL,
  target       VARCHAR(20)  NOT NULL DEFAULT 'all', -- all|students|parents|teachers|grade|class
  target_id    INT UNSIGNED DEFAULT NULL,  -- grade_id or class_id if targeted
  published_at DATETIME     DEFAULT NULL,
  expires_at   DATETIME     DEFAULT NULL,
  is_public    TINYINT(1)   NOT NULL DEFAULT 0,
  attachment   VARCHAR(255) DEFAULT NULL,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 40. EVENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS events (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(200) NOT NULL,
  description  TEXT         DEFAULT NULL,
  event_date   DATE         NOT NULL,
  end_date     DATE         DEFAULT NULL,
  start_time   TIME         DEFAULT NULL,
  end_time     TIME         DEFAULT NULL,
  venue        VARCHAR(150) DEFAULT NULL,
  category     VARCHAR(40)  NOT NULL DEFAULT 'general',
  is_public    TINYINT(1)   NOT NULL DEFAULT 1,
  created_by   INT UNSIGNED NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 41. NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  type        VARCHAR(40)  NOT NULL,
  title       VARCHAR(200) NOT NULL,
  message     TEXT         NOT NULL,
  link        VARCHAR(255) DEFAULT NULL,
  is_read     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 42. AUDIT LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED DEFAULT NULL,
  user_name    VARCHAR(150) DEFAULT NULL,
  action       VARCHAR(80)  NOT NULL,
  module       VARCHAR(40)  NOT NULL,
  record_type  VARCHAR(60)  DEFAULT NULL,
  record_id    INT UNSIGNED DEFAULT NULL,
  old_value    TEXT         DEFAULT NULL,
  new_value    TEXT         DEFAULT NULL,
  ip_address   VARCHAR(45)  DEFAULT NULL,
  user_agent   VARCHAR(500) DEFAULT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_module (module),
  INDEX idx_user   (user_id),
  INDEX idx_date   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 43. EXTERNAL EXAM RECORDS (WASSCE / WAEC / BECE)
-- ============================================================
CREATE TABLE IF NOT EXISTS external_exams (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  exam_type        VARCHAR(40)  NOT NULL DEFAULT 'WASSCE', -- WASSCE|WAEC|BECE|Other
  exam_year        YEAR         DEFAULT NULL,
  center_number    VARCHAR(30)  DEFAULT NULL,
  candidate_number VARCHAR(30)  DEFAULT NULL,
  results          TEXT         DEFAULT NULL,  -- JSON string of subject results
  overall_grade    VARCHAR(20)  DEFAULT NULL,
  passed           TINYINT(1)   DEFAULT NULL,
  notes            TEXT         DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 44. TIMETABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS timetable (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id     INT UNSIGNED NOT NULL,
  subject_id   INT UNSIGNED NOT NULL,
  teacher_id   INT UNSIGNED DEFAULT NULL,
  day_of_week  TINYINT      NOT NULL,  -- 1=Mon … 5=Fri
  period_slot  TINYINT      NOT NULL,  -- 1..8
  start_time   TIME         DEFAULT NULL,
  end_time     TIME         DEFAULT NULL,
  room         VARCHAR(30)  DEFAULT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  FOREIGN KEY (class_id)         REFERENCES classes(id)        ON DELETE CASCADE,
  FOREIGN KEY (subject_id)       REFERENCES subjects(id)       ON DELETE CASCADE,
  FOREIGN KEY (teacher_id)       REFERENCES teachers(id)       ON DELETE SET NULL,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 45. GRADUATION RECORDS
-- ============================================================
CREATE TABLE IF NOT EXISTS graduation_records (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL,
  graduation_date  DATE         DEFAULT NULL,
  ceremony_date    DATE         DEFAULT NULL,
  certificate_no   VARCHAR(40)  DEFAULT NULL,
  yearly_average   DECIMAL(6,2) DEFAULT NULL,
  honours          VARCHAR(60)  DEFAULT NULL,
  status           VARCHAR(20)  NOT NULL DEFAULT 'Eligible', -- Eligible|Graduated|Deferred
  notes            TEXT         DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 46. NEWS / CMS PAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS news_posts (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(250) NOT NULL,
  slug        VARCHAR(250) NOT NULL UNIQUE,
  excerpt     TEXT         DEFAULT NULL,
  body        LONGTEXT     NOT NULL,
  category    VARCHAR(40)  NOT NULL DEFAULT 'general',
  image       VARCHAR(255) DEFAULT NULL,
  is_published TINYINT(1)  NOT NULL DEFAULT 0,
  published_at DATETIME    DEFAULT NULL,
  created_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 47. GALLERY
-- ============================================================
CREATE TABLE IF NOT EXISTS gallery (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(150) NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  caption    TEXT         DEFAULT NULL,
  category   VARCHAR(40)  DEFAULT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 48. FAQ
-- ============================================================
CREATE TABLE IF NOT EXISTS faq (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question   TEXT         NOT NULL,
  answer     TEXT         NOT NULL,
  category   VARCHAR(40)  DEFAULT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Roles
INSERT IGNORE INTO roles (name, label) VALUES
('super_admin',  'Super Administrator'),
('principal',    'Principal'),
('vice_principal','Vice Principal'),
('registrar',    'Registrar'),
('academic_dean','Academic Dean'),
('teacher',      'Teacher'),
('accountant',   'Accountant / Bursar'),
('librarian',    'Librarian'),
('parent',       'Parent / Guardian'),
('student',      'Student'),
('applicant',    'Applicant');

-- School settings
INSERT IGNORE INTO school_settings (setting_key, setting_value, setting_group) VALUES
('school_name',        'KARN HIGH SCHOOL',                'general'),
('school_tagline',     'Building Knowledge, Character and a Better Future', 'general'),
('school_address',     'Karnplay, Nimba County, Liberia', 'general'),
('school_phone',       '+231 886 417 711',                'general'),
('school_phone2',      '+231 777 417 711',                'general'),
('school_email',       'info@karnhighschool.edu.lr',      'general'),
('school_website',     'www.karnhighschool.edu.lr',       'general'),
('school_founded',     '1985',                            'general'),
('school_motto',       'Excellence in Education',         'general'),
('current_academic_year', '2026/2027',                   'academic'),
('admission_open',     '1',                               'admissions'),
('admission_year',     '2026/2027',                       'admissions'),
('currency_primary',   'LRD',                             'finance'),
('currency_secondary', 'USD',                             'finance'),
('passing_grade',      '70',                              'academic'),
('office_hours',       'Monday–Friday, 8:00am–4:00pm',   'general'),
('hero_headline',      'Building Knowledge, Character and a Better Future.', 'website'),
('hero_subtext',       'Where curiosity is nurtured, potential is discovered, and every student is prepared to make a meaningful difference.', 'website'),
('welcome_message',    'At Karn High School, education goes beyond the classroom. We are a vibrant community where every learner is known, supported and inspired to reach higher.', 'website'),
('stats_students',     '1,240+',                          'website'),
('stats_teachers',     '48',                              'website'),
('stats_grades',       '14',                              'website'),
('stats_years',        '39',                              'website');

-- Academic year 2026/2027
INSERT IGNORE INTO academic_years (name, start_date, end_date, is_current, status)
VALUES ('2026/2027', '2026-08-18', '2027-06-26', 1, 'active');

-- Semesters for 2026/2027
INSERT IGNORE INTO semesters (academic_year_id, name, sequence, start_date, end_date, is_current)
SELECT id, 'Semester 1', 1, '2026-08-18', '2026-12-19', 1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO semesters (academic_year_id, name, sequence, start_date, end_date, is_current)
SELECT id, 'Semester 2', 2, '2027-01-06', '2027-06-26', 0 FROM academic_years WHERE name='2026/2027';

-- Periods — Semester 1
INSERT IGNORE INTO periods (semester_id, name, sequence, type)
SELECT s.id, '1st Period', 1, 'period'
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id, name, sequence, type)
SELECT s.id, '2nd Period', 2, 'period'
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id, name, sequence, type)
SELECT s.id, '3rd Period', 3, 'period'
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id, name, sequence, type, is_current)
SELECT s.id, 'Semester 1 Examination', 4, 'exam', 1
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=1;

-- Periods — Semester 2
INSERT IGNORE INTO periods (semester_id, name, sequence, type)
SELECT s.id, '4th Period', 1, 'period'
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id, name, sequence, type)
SELECT s.id, '5th Period', 2, 'period'
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id, name, sequence, type)
SELECT s.id, '6th Period', 3, 'period'
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id, name, sequence, type)
SELECT s.id, 'Semester 2 Examination', 4, 'exam'
FROM semesters s JOIN academic_years ay ON s.academic_year_id=ay.id
WHERE ay.name='2026/2027' AND s.sequence=2;

-- Grades
INSERT IGNORE INTO grades (name, sequence, level) VALUES
('ABC/KG',  1,  'early'),
('Grade 1', 2,  'primary'),
('Grade 2', 3,  'primary'),
('Grade 3', 4,  'primary'),
('Grade 4', 5,  'primary'),
('Grade 5', 6,  'primary'),
('Grade 6', 7,  'primary'),
('Grade 7', 8,  'junior'),
('Grade 8', 9,  'junior'),
('Grade 9', 10, 'junior'),
('Grade 10',11, 'senior'),
('Grade 11',12, 'senior'),
('Grade 12',13, 'senior');

-- Subjects
INSERT IGNORE INTO subjects (code, name, short_name, category) VALUES
('ENG', 'English Language',       'English',   'core'),
('EGR', 'English Grammar',        'Grammar',   'core'),
('LIT', 'Literature',             'Lit.',      'core'),
('MAT', 'Mathematics',            'Math',      'core'),
('BIO', 'Biology',                'Biology',   'core'),
('CHM', 'Chemistry',              'Chem.',     'core'),
('PHY', 'Physics',                'Physics',   'core'),
('GSC', 'General Science',        'G.Science', 'core'),
('GEO', 'Geography',              'Geo.',      'core'),
('HIS', 'History',                'History',   'core'),
('ECO', 'Economics',              'Econ.',     'core'),
('CIV', 'Civics/Government',      'Civics',    'core'),
('FRE', 'French',                 'French',    'elective'),
('CSC', 'Computer Science',       'Computer',  'elective'),
('AGR', 'Agriculture',            'Agric.',    'elective'),
('ACC', 'Accounting',             'Acctg.',    'elective'),
('PHE', 'Physical Education',     'P.E.',      'extracurricular'),
('MRE', 'Moral/Religious Educ.',  'M.R.E.',   'core'),
('ART', 'Arts & Craft',           'Art',       'elective'),
('MUS', 'Music & Dance',          'Music',     'elective'),
('RC',  'Reading & Composition',  'Reading',   'core');

-- Default grading scale for 2026/2027
INSERT IGNORE INTO grading_scales (academic_year_id, grade_letter, min_percent, max_percent, grade_point, description, is_pass)
SELECT id, 'A',  90, 100,  4.00, 'Excellent',  1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO grading_scales (academic_year_id, grade_letter, min_percent, max_percent, grade_point, description, is_pass)
SELECT id, 'B',  80, 89.99, 3.00, 'Very Good',  1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO grading_scales (academic_year_id, grade_letter, min_percent, max_percent, grade_point, description, is_pass)
SELECT id, 'C',  70, 79.99, 2.00, 'Good',        1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO grading_scales (academic_year_id, grade_letter, min_percent, max_percent, grade_point, description, is_pass)
SELECT id, 'D',  60, 69.99, 1.00, 'Satisfactory',1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO grading_scales (academic_year_id, grade_letter, min_percent, max_percent, grade_point, description, is_pass)
SELECT id, 'F',   0, 59.99, 0.00, 'Fail',         0 FROM academic_years WHERE name='2026/2027';

-- Users (bcrypt hash of 'admin123' for all demo accounts)
-- Password: admin123  hash: y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC
INSERT IGNORE INTO users (name, email, phone, password_hash, role_id) VALUES
('Principal Admin',   'admin@karnhighschool.edu.lr',       '+231 886 417 711', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='principal')),
('John Cooper',       'principal@karnhighschool.edu.lr',   '+231 880 000 011', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='principal')),
('Mary Kollie',       'registrar@karnhighschool.edu.lr',   '+231 880 000 012', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='registrar')),
('James Doe',         'academic@karnhighschool.edu.lr',    '+231 880 000 013', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='academic_dean')),
('Sarah Williams',    'teacher@karnhighschool.edu.lr',     '+231 880 000 014', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='teacher')),
('Moses Johnson',     'accountant@karnhighschool.edu.lr',  '+231 880 000 015', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='accountant')),
('Demo Student',      'student@karnhighschool.edu.lr',     '+231 880 000 016', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='student')),
('Demo Parent',       'parent@karnhighschool.edu.lr',      '+231 880 000 017', 'y$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC', (SELECT id FROM roles WHERE name='parent'));

-- Demo teachers
INSERT IGNORE INTO teachers (user_id, teacher_id, first_name, last_name, gender, phone, email, qualification, specialization, employment_date, status)
SELECT u.id, 'TCH-001', 'Sarah', 'Williams', 'Female', '+231 880 000 014', 'teacher@karnhighschool.edu.lr', 'B.Ed. Mathematics', 'Mathematics', '2020-09-01', 'Active'
FROM users u WHERE u.email='teacher@karnhighschool.edu.lr';

INSERT IGNORE INTO teachers (teacher_id, first_name, last_name, gender, phone, email, qualification, specialization, employment_date, status) VALUES
('TCH-002', 'Robert',   'Brown',    'Male',   '+231 880 000 018', 'rbrown@karnhighschool.edu.lr',     'B.Ed. English',     'English Language', '2019-09-01', 'Active'),
('TCH-003', 'Grace',    'Flomo',    'Female', '+231 880 000 019', 'gflomo@karnhighschool.edu.lr',     'B.Sc. Biology',     'Sciences',         '2021-09-01', 'Active'),
('TCH-004', 'Emmanuel', 'Konneh',   'Male',   '+231 880 000 020', 'ekonneh@karnhighschool.edu.lr',    'M.A. History',      'Social Studies',   '2018-09-01', 'Active');

-- Classes for 2026/2027
INSERT IGNORE INTO classes (grade_id, academic_year_id, name, section)
SELECT g.id, ay.id, CONCAT(g.name, 'A'), 'A'
FROM grades g, academic_years ay
WHERE ay.name='2026/2027'
  AND g.name IN ('Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12');

INSERT IGNORE INTO classes (grade_id, academic_year_id, name, section)
SELECT g.id, ay.id, CONCAT(g.name, 'B'), 'B'
FROM grades g, academic_years ay
WHERE ay.name='2026/2027'
  AND g.name IN ('Grade 10','Grade 11','Grade 12');

-- Sample students (link to users + grades)
INSERT IGNORE INTO students (student_id, admission_number, first_name, last_name, gender, current_grade_id, academic_year_id, status, admission_date)
SELECT 'KHS-2024-0184', 'ADM-2024-0184', 'Amara',    'Johnson', 'Female', g.id, ay.id, 'Active', '2024-09-01'
FROM grades g, academic_years ay WHERE g.name='Grade 8'  AND ay.name='2026/2027';

INSERT IGNORE INTO students (student_id, admission_number, first_name, last_name, gender, current_grade_id, academic_year_id, status, admission_date)
SELECT 'KHS-2024-0183', 'ADM-2024-0183', 'Samuel',   'Kollie',  'Male',   g.id, ay.id, 'Active', '2024-09-01'
FROM grades g, academic_years ay WHERE g.name='Grade 10' AND ay.name='2026/2027';

INSERT IGNORE INTO students (student_id, admission_number, first_name, last_name, gender, current_grade_id, academic_year_id, status, admission_date)
SELECT 'KHS-2024-0182', 'ADM-2024-0182', 'Martha',   'Doe',     'Female', g.id, ay.id, 'Active', '2024-09-01'
FROM grades g, academic_years ay WHERE g.name='Grade 5'  AND ay.name='2026/2027';

INSERT IGNORE INTO students (student_id, admission_number, first_name, last_name, gender, current_grade_id, academic_year_id, status, admission_date)
SELECT 'KHS-2024-0181', 'ADM-2024-0181', 'Emmanuel', 'Toe',     'Male',   g.id, ay.id, 'Active', '2024-09-01'
FROM grades g, academic_years ay WHERE g.name='Grade 11' AND ay.name='2026/2027';

-- Link demo student user to student record
UPDATE students s
JOIN users u ON u.email='student@karnhighschool.edu.lr'
SET s.user_id=u.id
WHERE s.student_id='KHS-2024-0184';

-- Sample applications
INSERT IGNORE INTO applications (application_number, first_name, last_name, date_of_birth, gender, phone, current_address, grade_applying_for, academic_year, guardian_name, guardian_relationship, guardian_phone, status)
VALUES
('KHS-2026-000184','Amina',    'Kamara',  '2012-03-15','Female','+231 881 001 001','Karnplay, Nimba','Grade 8',  '2026/2027','Mary Kamara',   'Mother','  +231 881 001 002','Application Submitted'),
('KHS-2026-000183','Samuel',   'Kollie',  '2010-07-22','Male',  '+231 881 001 003','Zorzor, Lofa',  'Grade 10', '2026/2027','John Kollie',   'Father',  '+231 881 001 004','Approved for entrance'),
('KHS-2026-000182','Rebecca',  'Nimley',  '2008-11-05','Female','+231 881 001 005','Sanniquellie',  'Grade 12', '2026/2027','Paul Nimley',   'Father',  '+231 881 001 006','Documents needed'),
('KHS-2026-000181','Thomas',   'Sumo',    '2011-01-18','Male',  '+231 881 001 007','Ganta, Nimba',  'Grade 9',  '2026/2027','Agnes Sumo',    'Mother',  '+231 881 001 008','Entrance scheduled'),
('KHS-2026-000180','Patience', 'Freeman', '2013-05-30','Female','+231 881 001 009','Karnplay, Nimba','Grade 7', '2026/2027','Joseph Freeman','Guardian','+231 881 001 010','Under Review');

-- Sample fee structures
INSERT IGNORE INTO fee_structures (academic_year_id, fee_type, amount, currency, is_mandatory)
SELECT id, 'Tuition',      15000.00, 'LRD', 1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO fee_structures (academic_year_id, fee_type, amount, currency, is_mandatory)
SELECT id, 'Registration',  5000.00, 'LRD', 1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO fee_structures (academic_year_id, fee_type, amount, currency, is_mandatory)
SELECT id, 'Examination',   3000.00, 'LRD', 1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO fee_structures (academic_year_id, fee_type, amount, currency, is_mandatory)
SELECT id, 'Development',   2000.00, 'LRD', 1 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO fee_structures (academic_year_id, fee_type, amount, currency, is_mandatory)
SELECT id, 'Computer',      1500.00, 'LRD', 0 FROM academic_years WHERE name='2026/2027';
INSERT IGNORE INTO fee_structures (academic_year_id, fee_type, amount, currency, is_mandatory)
SELECT id, 'Library',        500.00, 'LRD', 0 FROM academic_years WHERE name='2026/2027';

-- Sample payments
INSERT IGNORE INTO payments (receipt_number, student_id, amount, currency, payment_method, payment_date, academic_year_id)
SELECT 'REC-2026-00481', s.id, 45000.00, 'LRD', 'Mobile money', CURDATE(), ay.id
FROM students s, academic_years ay WHERE s.student_id='KHS-2024-0184' AND ay.name='2026/2027';
INSERT IGNORE INTO payments (receipt_number, student_id, amount, currency, payment_method, payment_date, academic_year_id)
SELECT 'REC-2026-00480', s.id, 250.00, 'USD', 'Bank transfer', CURDATE(), ay.id
FROM students s, academic_years ay WHERE s.student_id='KHS-2024-0183' AND ay.name='2026/2027';
INSERT IGNORE INTO payments (receipt_number, student_id, amount, currency, payment_method, payment_date, academic_year_id)
SELECT 'REC-2026-00479', s.id, 32500.00, 'LRD', 'Cash', DATE_SUB(CURDATE(),INTERVAL 1 DAY), ay.id
FROM students s, academic_years ay WHERE s.student_id='KHS-2024-0182' AND ay.name='2026/2027';

-- Sample library books
INSERT IGNORE INTO library_books (isbn, title, author, category, total_copies, available) VALUES
('978-0-06-112008-4','English Grammar in Use',     'Raymond Murphy', 'Reference', 5, 4),
('978-0-19-953492-3','Oxford Mathematics D1',       'Various',        'Mathematics',3,3),
('978-0-00-000001-0','General Science for Africa',  'Various',        'Science',    4,3),
('978-0-00-000002-0','Liberia: A History',           'Various',        'History',    2,2);

-- Sample announcements
INSERT IGNORE INTO announcements (title, message, target, is_public, created_by, published_at)
SELECT 'Welcome to the 2026/2027 Academic Year',
       'We warmly welcome all students, parents and staff to the new academic year. Classes begin Monday, August 18, 2026.',
       'all', 1, u.id, NOW()
FROM users u WHERE u.email='admin@karnhighschool.edu.lr';

INSERT IGNORE INTO announcements (title, message, target, is_public, created_by, published_at)
SELECT '2026/2027 Admissions Now Open',
       'Applications for the 2026/2027 academic year are now open. Apply online at our school website.',
       'all', 1, u.id, NOW()
FROM users u WHERE u.email='admin@karnhighschool.edu.lr';

-- Sample events
INSERT IGNORE INTO events (title, description, event_date, start_time, category, is_public, created_by)
SELECT 'Opening Ceremony — New Academic Year',
       'Official opening of the 2026/2027 academic year at KHS.', '2026-08-18', '09:00:00', 'academic', 1, u.id
FROM users u WHERE u.email='admin@karnhighschool.edu.lr';

INSERT IGNORE INTO events (title, description, event_date, start_time, category, is_public, created_by)
SELECT 'PTA Meeting — First Term',
       'First PTA meeting of the academic year. All parents and guardians are welcome.', '2026-09-20', '10:00:00', 'community', 1, u.id
FROM users u WHERE u.email='admin@karnhighschool.edu.lr';

-- Sample FAQs
INSERT IGNORE INTO faq (question, answer, category, sort_order) VALUES
('How do I apply for admission?',       'Visit our Admissions page and click "Apply Now". Complete the multi-step online application form. You will receive an application number upon submission.',                                        'admissions', 1),
('What documents are required?',        'Required documents include: previous school report card, birth certificate or age verification, and a passport photo. Documents can be uploaded online or submitted in person.',                 'admissions', 2),
('What grades does KHS offer?',         'Karn High School offers education from ABC/KG (Kindergarten) through Grade 12, covering early childhood, primary, junior high, and senior high levels.',                                       'academics',  3),
('Is there an entrance examination?',   'Yes. After your application is reviewed and approved, you will be invited to take an entrance examination. Eligible applicants receive an official Entrance Eligibility Letter.',               'admissions', 4),
('What are the school fees?',           'Fee structures vary by grade. Please contact the school office or log in to the parent/student portal for detailed fee information for the current academic year.',                             'finance',    5),
('When does the academic year begin?',  'The 2026/2027 academic year begins on August 18, 2026. Please check the school website or contact the office for the most current calendar information.',                                      'academics',  6),
('How can I check my application status?','Visit the Application Status page and enter your application number along with your phone number to track the progress of your application.',                                                 'admissions', 7),
('Do you offer financial assistance?',  'Please contact the school office directly to discuss financial assistance options available for eligible students.',                                                                           'finance',    8);

-- admin_users compatibility shim (for existing code that still references this table)
CREATE TABLE IF NOT EXISTS admin_users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(30)  NOT NULL DEFAULT 'staff',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  last_login    DATETIME     DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO admin_users (name, email, password_hash, role)
SELECT name, email, password_hash, r.name
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name IN ('principal','registrar','academic_dean','teacher','accountant');

-- Contact messages (keep for backward compat)
CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120) NOT NULL,
  phone      VARCHAR(30)  DEFAULT NULL,
  message    TEXT         NOT NULL,
  is_read    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Assessment configs (auto-seeded from periods) ─────────────
INSERT IGNORE INTO assessment_configs (academic_year_id, name, type, sequence, max_marks, weight_percent, period_id)
SELECT
  ay.id,
  p.name,
  p.type,
  p.sequence + (CASE WHEN s.sequence=2 THEN 4 ELSE 0 END),
  100.00,
  CASE WHEN p.type='exam' THEN 40.00 ELSE 20.00 END,
  p.id
FROM periods p
JOIN semesters s ON s.id = p.semester_id
JOIN academic_years ay ON ay.id = s.academic_year_id
WHERE ay.is_current = 1;
