-- ============================================================
-- STUDENT LIFECYCLE SEED
-- Students enter 2023/2024, progress through each year
-- promotions, transfers, graduations, transcripts
-- All passwords: 1234
-- ============================================================
USE karnhighschool;
SET FOREIGN_KEY_CHECKS = 0;
SET @pw = '$2y$10$lpPLPh39oWD5Geze1FXGtO6WjILnivbXvhczHwjrNvek/VLfM2upC';

-- Year ID helpers
SET @y23 = (SELECT id FROM academic_years WHERE name='2023/2024');
SET @y24 = (SELECT id FROM academic_years WHERE name='2024/2025');
SET @y25 = (SELECT id FROM academic_years WHERE name='2025/2026');
SET @y26 = (SELECT id FROM academic_years WHERE name='2026/2027');

-- Grade ID helpers
SET @g7  = (SELECT id FROM grades WHERE name='Grade 7');
SET @g8  = (SELECT id FROM grades WHERE name='Grade 8');
SET @g9  = (SELECT id FROM grades WHERE name='Grade 9');
SET @g10 = (SELECT id FROM grades WHERE name='Grade 10');
SET @g11 = (SELECT id FROM grades WHERE name='Grade 11');
SET @g12 = (SELECT id FROM grades WHERE name='Grade 12');

-- Class helpers (current year 2026/2027 A sections)
SET @c7a26  = (SELECT c.id FROM classes c WHERE c.grade_id=@g7  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c8a26  = (SELECT c.id FROM classes c WHERE c.grade_id=@g8  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c9a26  = (SELECT c.id FROM classes c WHERE c.grade_id=@g9  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c10a26 = (SELECT c.id FROM classes c WHERE c.grade_id=@g10 AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c11a26 = (SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c12a26 = (SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c12b26 = (SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y26 AND c.section='B' LIMIT 1);

-- Corresponding 2023/2024 classes
SET @c9a23  = (SELECT c.id FROM classes c WHERE c.grade_id=@g9  AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c10a23 = (SELECT c.id FROM classes c WHERE c.grade_id=@g10 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c11a23 = (SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c12a23 = (SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);

SET @c10a24 = (SELECT c.id FROM classes c WHERE c.grade_id=@g10 AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);
SET @c11a24 = (SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);
SET @c12a24 = (SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);

SET @c11a25 = (SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c12a25 = (SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);

SET @approvedBy = (SELECT id FROM users WHERE email='vprincipal@karnhighschool.edu.lr' LIMIT 1);
SET @enteredBy  = (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr'   LIMIT 1);
SET @regUser    = (SELECT id FROM users WHERE email='registrar@karnhighschool.edu.lr' LIMIT 1);

-- ============================================================
-- COHORT A: Entered Grade 9 in 2023/2024
-- These students have been with the school 4 years
-- Current status: Grade 12 (2026/2027) — graduating
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2023-2001','ADM-2023-2001','Kortu',    'Jr.','Jerry',     'Male',  '2007-04-15','+231 886 201 001',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-2002','ADM-2023-2002','Blessing', 'N.', 'Jones',     'Female','2007-07-22','+231 886 201 002',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-2003','ADM-2023-2003','Gbelly',   '',   'Francis',   'Male',  '2007-03-08','+231 886 201 003',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-2004','ADM-2023-2004','Tennie',   'M.', 'Wleh',      'Female','2007-09-30','+231 886 201 004',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-2005','ADM-2023-2005','Saye',     'K.', 'Kollie',    'Male',  '2007-06-18','+231 886 201 005',@g12,@c12b26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-2006','ADM-2023-2006','Nowai',    '',   'Toe',       'Female','2007-11-05','+231 886 201 006',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-2007','ADM-2023-2007','Pewu',     'G.', 'Konneh',    'Male',  '2007-01-28','+231 886 201 007',@g12,@c12b26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-2008','ADM-2023-2008','Lorpu',    '',   'Flomo',     'Female','2007-08-14','+231 886 201 008',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba');

-- ============================================================
-- COHORT B: Entered Grade 10 in 2023/2024
-- Current: Grade 11 or still Grade 11 (one repeating)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2023-3001','ADM-2023-3001','Trokon',   'A.', 'Sumo',      'Male',  '2008-02-11','+231 886 203 001',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-3002','ADM-2023-3002','Weade',    '',   'Nimley',    'Female','2008-05-27','+231 886 203 002',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-3003','ADM-2023-3003','Dorwohn',  'J.', 'Freeman',   'Male',  '2008-09-13','+231 886 203 003',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
-- One student repeating Grade 11 (failed 2025/2026)
('KHS-2023-3004','ADM-2023-3004','Youga',    '',   'Harris',    'Female','2008-07-04','+231 886 203 004',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba');

-- ============================================================
-- COHORT C: Entered Grade 11 in 2023/2024
-- Current: Grade 12 (2026/2027) — graduating
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2023-4001','ADM-2023-4001','Nyanquoi', 'E.', 'Cooper',    'Male',  '2006-03-15','+231 886 204 001',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-4002','ADM-2023-4002','Yeanoh',   '',   'Kamara',    'Female','2006-06-22','+231 886 204 002',@g12,@c12b26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-4003','ADM-2023-4003','Musa',     'K.', 'Kollie',    'Male',  '2006-10-08','+231 886 204 003',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba');

-- ============================================================
-- COHORT D: Graduated in 2023/2024 (were in Grade 12)
-- Status: Graduated, no longer active
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,graduation_date,county) VALUES
('KHS-2020-5001','ADM-2020-5001','Boima',    'T.', 'Wea',       'Male',  '2004-01-20','+231 886 205 001',@g12,@c12a23,@y23,'Graduated','2020-09-07','2024-06-28','Nimba'),
('KHS-2020-5002','ADM-2020-5002','Wede',     '',   'Cooper',    'Female','2004-04-15','+231 886 205 002',@g12,@c12a23,@y23,'Graduated','2020-09-07','2024-06-28','Nimba'),
('KHS-2020-5003','ADM-2020-5003','Flahn',    'M.', 'Kollie',    'Male',  '2004-07-30','+231 886 205 003',@g12,@c12a23,@y23,'Graduated','2020-09-07','2024-06-28','Nimba'),
('KHS-2020-5004','ADM-2020-5004','Pewee',    '',   'Nimley',    'Female','2004-11-12','+231 886 205 004',@g12,@c12a23,@y23,'Graduated','2020-09-07','2024-06-28','Nimba'),
('KHS-2020-5005','ADM-2020-5005','Garway',   'J.', 'Freeman',   'Male',  '2004-03-25','+231 886 205 005',@g12,@c12a23,@y23,'Graduated','2020-09-07','2024-06-28','Nimba');

-- ============================================================
-- COHORT E: Graduated in 2024/2025
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,graduation_date,county) VALUES
('KHS-2021-6001','ADM-2021-6001','Zuah',     'A.', 'Harris',    'Male',  '2005-02-18','+231 886 206 001',@g12,@c12a24,@y24,'Graduated','2021-08-23','2025-06-27','Nimba'),
('KHS-2021-6002','ADM-2021-6002','Yatta',    '',   'Sumo',      'Female','2005-05-09','+231 886 206 002',@g12,@c12a24,@y24,'Graduated','2021-08-23','2025-06-27','Nimba'),
('KHS-2021-6003','ADM-2021-6003','Minnah',   'K.', 'Flomo',     'Male',  '2005-08-23','+231 886 206 003',@g12,@c12a24,@y24,'Graduated','2021-08-23','2025-06-27','Nimba'),
('KHS-2021-6004','ADM-2021-6004','Korto',    '',   'Konneh',    'Female','2005-11-30','+231 886 206 004',@g12,@c12a24,@y24,'Graduated','2021-08-23','2025-06-27','Nimba'),
('KHS-2021-6005','ADM-2021-6005','Siah',     'T.', 'Kamara',    'Male',  '2005-01-14','+231 886 206 005',@g12,@c12a24,@y24,'Graduated','2021-08-23','2025-06-27','Nimba');

-- ============================================================
-- COHORT F: Graduated in 2025/2026
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,graduation_date,county) VALUES
('KHS-2022-7001','ADM-2022-7001','Flumo',    'B.', 'Doe',       'Male',  '2006-03-07','+231 886 207 001',@g12,@c12a25,@y25,'Graduated','2022-08-22','2026-06-26','Nimba'),
('KHS-2022-7002','ADM-2022-7002','Korlu',    '',   'Williams',  'Female','2006-06-19','+231 886 207 002',@g12,@c12a25,@y25,'Graduated','2022-08-22','2026-06-26','Nimba'),
('KHS-2022-7003','ADM-2022-7003','Gbaa',     'J.', 'Johnson',   'Male',  '2006-09-04','+231 886 207 003',@g12,@c12a25,@y25,'Graduated','2022-08-22','2026-06-26','Nimba'),
('KHS-2022-7004','ADM-2022-7004','Paye',     '',   'Kollie',    'Female','2006-12-22','+231 886 207 004',@g12,@c12a25,@y25,'Graduated','2022-08-22','2026-06-26','Nimba'),
('KHS-2022-7005','ADM-2022-7005','Wesseh',   'A.', 'Cooper',    'Male',  '2006-04-11','+231 886 207 005',@g12,@c12a25,@y25,'Graduated','2022-08-22','2026-06-26','Nimba'),
('KHS-2022-7006','ADM-2022-7006','Kumba',    '',   'Freeman',   'Female','2006-07-28','+231 886 207 006',@g12,@c12a25,@y25,'Graduated','2022-08-22','2026-06-26','Nimba');

-- ============================================================
-- COHORT G: Transferred IN from another school (2025/2026)
-- Now in Grade 11 or 12
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2025-8001','ADM-2025-8001','Varney',   'C.', 'Kamara',    'Male',  '2007-05-16','+231 886 208 001',@g12,@c12a26,@y26,'Active','2025-08-18','Lofa'),
('KHS-2025-8002','ADM-2025-8002','Tenneh',   '',   'Sumo',      'Female','2008-02-24','+231 886 208 002',@g11,@c11a26,@y26,'Active','2025-08-18','Bong'),
('KHS-2025-8003','ADM-2025-8003','Mulbah',   'G.', 'Harris',    'Male',  '2008-08-11','+231 886 208 003',@g11,@c11a26,@y26,'Active','2025-08-18','Nimba');

-- ============================================================
-- COHORT H: Transferred OUT (left during 2024/2025)
-- Status: Transferred
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2022-9001','ADM-2022-9001','Gbanyan',  'P.', 'Wea',       'Male',  '2007-09-03','+231 886 209 001',@g11,@c11a24,@y24,'Transferred','2022-08-22','Nimba'),
('KHS-2022-9002','ADM-2022-9002','Karmen',   '',   'Nimley',    'Female','2008-01-17','+231 886 209 002',@g10,@c10a24,@y24,'Transferred','2022-08-22','Nimba');

-- ============================================================
-- CREATE PORTAL USERS FOR NEW STUDENTS
-- ============================================================
INSERT IGNORE INTO users (name,email,phone,password_hash,role_id)
SELECT CONCAT(s.first_name,' ',s.last_name),
       LOWER(CONCAT(s.student_id,'@student.karnhighschool.edu.lr')),
       s.phone, @pw,
       (SELECT id FROM roles WHERE name='student')
FROM students s
WHERE s.student_id LIKE 'KHS-2023-%' OR s.student_id LIKE 'KHS-2020-%'
   OR s.student_id LIKE 'KHS-2021-%' OR s.student_id LIKE 'KHS-2022-%'
   OR s.student_id LIKE 'KHS-2025-8%';

UPDATE students s
JOIN users u ON u.email=LOWER(CONCAT(s.student_id,'@student.karnhighschool.edu.lr'))
SET s.user_id=u.id WHERE s.user_id IS NULL;

-- ============================================================
-- PROMOTION RECORDS
-- ============================================================
-- Create student_promotions table for promotion history
CREATE TABLE IF NOT EXISTS student_promotions (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id       INT UNSIGNED NOT NULL,
  movement_type    VARCHAR(30)  NOT NULL, -- promoted|repeated|graduated
  from_grade_id    INT UNSIGNED NULL,
  to_grade_id      INT UNSIGNED NULL,
  from_year_id     INT UNSIGNED NULL,
  to_year_id       INT UNSIGNED NULL,
  effective_date   DATE         NOT NULL,
  reason           TEXT         NULL,
  approved_by      INT UNSIGNED NULL,
  notes            TEXT         NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── PROMOTIONS: Cohort A (Gr9→10→11→12 each year) ──────────
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g9,@g10,@y23,@y24,'2024-07-01',@regUser,'Promoted from Grade 9 to Grade 10 — academic year 2023/2024'
FROM students s WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008');

INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g10,@g11,@y24,@y25,'2025-07-01',@regUser,'Promoted from Grade 10 to Grade 11 — academic year 2024/2025'
FROM students s WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008');

INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g11,@g12,@y25,@y26,'2026-07-01',@regUser,'Promoted from Grade 11 to Grade 12 — academic year 2025/2026'
FROM students s WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008');

-- ── PROMOTIONS: Cohort B (Gr10→11→Gr11 repeat for Youga) ───
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g10,@g11,@y23,@y24,'2024-07-01',@regUser,'Promoted from Grade 10 to Grade 11'
FROM students s WHERE s.student_id IN ('KHS-2023-3001','KHS-2023-3002','KHS-2023-3003','KHS-2023-3004');

INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g11,@g12,@y24,@y25,'2025-07-01',@regUser,'Promoted from Grade 11 to Grade 12'
FROM students s WHERE s.student_id IN ('KHS-2023-3001','KHS-2023-3002','KHS-2023-3003');
-- Youga Harris FAILED — repeating Grade 11
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'repeated',@g11,@g11,@y24,@y25,'2025-07-01',@regUser,'Did not meet promotion requirements — repeating Grade 11 (2025/2026)'
FROM students s WHERE s.student_id='KHS-2023-3004';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'repeated',@g11,@g11,@y25,@y26,'2026-07-01',@regUser,'Repeating Grade 11 for second year — additional academic support required'
FROM students s WHERE s.student_id='KHS-2023-3004';

-- ── PROMOTIONS: Cohort C (Gr11→12) ─────────────────────────
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g11,@g12,@y23,@y24,'2024-07-01',@regUser,'Promoted from Grade 11 to Grade 12'
FROM students s WHERE s.student_id IN ('KHS-2023-4001','KHS-2023-4002','KHS-2023-4003');
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g12,@g12,@y24,@y25,'2025-07-01',@regUser,'Continued Grade 12 — 2025/2026'
FROM students s WHERE s.student_id IN ('KHS-2023-4001','KHS-2023-4002','KHS-2023-4003');
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g12,@g12,@y25,@y26,'2026-07-01',@regUser,'Final year Grade 12 — 2026/2027'
FROM students s WHERE s.student_id IN ('KHS-2023-4001','KHS-2023-4002','KHS-2023-4003');

-- ── GRADUATION: 2023/2024 cohort ────────────────────────────
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,effective_date,approved_by,notes)
SELECT s.id,'graduated',@g12,NULL,@y23,'2024-06-28',@regUser,'Graduated — Class of 2023/2024'
FROM students s WHERE s.student_id IN ('KHS-2020-5001','KHS-2020-5002','KHS-2020-5003','KHS-2020-5004','KHS-2020-5005');

-- ── GRADUATION: 2024/2025 cohort ────────────────────────────
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,effective_date,approved_by,notes)
SELECT s.id,'graduated',@g12,NULL,@y24,'2025-06-27',@regUser,'Graduated — Class of 2024/2025'
FROM students s WHERE s.student_id IN ('KHS-2021-6001','KHS-2021-6002','KHS-2021-6003','KHS-2021-6004','KHS-2021-6005');

-- ── GRADUATION: 2025/2026 cohort ────────────────────────────
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,effective_date,approved_by,notes)
SELECT s.id,'graduated',@g12,NULL,@y25,'2026-06-26',@regUser,'Graduated — Class of 2025/2026'
FROM students s WHERE s.student_id IN ('KHS-2022-7001','KHS-2022-7002','KHS-2022-7003','KHS-2022-7004','KHS-2022-7005','KHS-2022-7006');

-- ── TRANSFER IN: Cohort G (using student_movements actual schema) ──────
INSERT IGNORE INTO student_movements (student_id,movement_type,effective_date,destination_school,reason,status,requested_by,approved_by,approved_at,notes,academic_year_id)
SELECT s.id,'transfer_in','2025-08-18','Ganta United School, Nimba','Student transferring from Ganta United School. Grade 12 placement.','completed',@regUser,@regUser,'2025-08-20','Transcripts verified. Placed in Grade 12A.',@y26
FROM students s WHERE s.student_id='KHS-2025-8001';
INSERT IGNORE INTO student_movements (student_id,movement_type,effective_date,destination_school,reason,status,requested_by,approved_by,approved_at,notes,academic_year_id)
SELECT s.id,'transfer_in','2025-08-18','Lofa County School','Student transferring from Lofa. Grade 11 placement.','completed',@regUser,@regUser,'2025-08-20','Previous school records verified.',@y26
FROM students s WHERE s.student_id='KHS-2025-8002';
INSERT IGNORE INTO student_movements (student_id,movement_type,effective_date,destination_school,reason,status,requested_by,approved_by,approved_at,notes,academic_year_id)
SELECT s.id,'transfer_in','2025-08-18','Sanniquellie School','Student transferring from Nimba. Grade 11 placement.','completed',@regUser,@regUser,'2025-08-20','All documents in order.',@y26
FROM students s WHERE s.student_id='KHS-2025-8003';

-- ── TRANSFER OUT: Cohort H ───────────────────────────────────
INSERT IGNORE INTO student_movements (student_id,movement_type,effective_date,destination_school,reason,status,requested_by,approved_by,approved_at,notes,academic_year_id)
SELECT s.id,'transfer_out','2025-01-15','C.D.B. King High School, Monrovia','Family relocated to Monrovia','completed',@regUser,@regUser,'2025-01-20','Transfer certificate issued.',@y24
FROM students s WHERE s.student_id='KHS-2022-9001';
INSERT IGNORE INTO student_movements (student_id,movement_type,effective_date,destination_school,reason,status,requested_by,approved_by,approved_at,notes,academic_year_id)
SELECT s.id,'transfer_out','2025-02-28','Yekepa Community School','Family relocation to Yekepa','completed',@regUser,@regUser,'2025-03-05','Transfer documents issued.',@y24
FROM students s WHERE s.student_id='KHS-2022-9002';

-- ============================================================
-- GRADUATION RECORDS for all graduated cohorts
-- ============================================================
-- 2023/2024 graduates
INSERT IGNORE INTO graduation_records (student_id,academic_year_id,graduation_date,ceremony_date,status,yearly_average,honours,certificate_no,notes)
SELECT s.id,@y23,'2024-06-28','2024-06-28','Graduated',
  ROUND(72+RAND()*20,1),
  CASE WHEN RAND()>0.6 THEN 'Cum Laude' WHEN RAND()>0.8 THEN 'Magna Cum Laude' ELSE NULL END,
  CONCAT('KHS-2024-GRAD-',LPAD(ROW_NUMBER() OVER (ORDER BY s.id),4,'0')),
  'Karn High School Graduation Ceremony — Class of 2023/2024. Karnplay City Hall.'
FROM students s WHERE s.student_id IN ('KHS-2020-5001','KHS-2020-5002','KHS-2020-5003','KHS-2020-5004','KHS-2020-5005');

-- 2024/2025 graduates
INSERT IGNORE INTO graduation_records (student_id,academic_year_id,graduation_date,ceremony_date,status,yearly_average,honours,certificate_no,notes)
SELECT s.id,@y24,'2025-06-27','2025-06-27','Graduated',
  ROUND(74+RAND()*22,1),
  CASE WHEN RAND()>0.5 THEN 'Cum Laude' WHEN RAND()>0.75 THEN 'Magna Cum Laude' ELSE NULL END,
  CONCAT('KHS-2025-GRAD-',LPAD(ROW_NUMBER() OVER (ORDER BY s.id),4,'0')),
  'Karn High School Graduation Ceremony — Class of 2024/2025.'
FROM students s WHERE s.student_id IN ('KHS-2021-6001','KHS-2021-6002','KHS-2021-6003','KHS-2021-6004','KHS-2021-6005');

-- 2025/2026 graduates
INSERT IGNORE INTO graduation_records (student_id,academic_year_id,graduation_date,ceremony_date,status,yearly_average,honours,certificate_no,notes)
SELECT s.id,@y25,'2026-06-26','2026-06-26','Graduated',
  ROUND(76+RAND()*20,1),
  CASE WHEN RAND()>0.45 THEN 'Cum Laude' WHEN RAND()>0.7 THEN 'Magna Cum Laude' ELSE NULL END,
  CONCAT('KHS-2026-GRAD-',LPAD(ROW_NUMBER() OVER (ORDER BY s.id),4,'0')),
  'Karn High School Graduation Ceremony — Class of 2025/2026.'
FROM students s WHERE s.student_id IN ('KHS-2022-7001','KHS-2022-7002','KHS-2022-7003','KHS-2022-7004','KHS-2022-7005','KHS-2022-7006');

-- ============================================================
-- ASSESSMENT SCORES — historical multi-year transcript data
-- ============================================================
-- Cohort A: Grade 9 scores in 2023/2024
SET @c9a23x = COALESCE(@c9a23, @c9a26);
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c9a23,@c9a26), sub.id, ac.id, @y23,
  ROUND(60+RAND()*35,1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 1060 DAY),
  DATE_SUB(NOW(),INTERVAL 1058 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008')
  AND sub.code IN ('MAT','ENG','BIO','HIS','EGR')
  AND ac.academic_year_id=@y23;

-- Cohort A: Grade 10 scores in 2024/2025
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c10a24,@c10a26), sub.id, ac.id, @y24,
  ROUND(62+RAND()*33,1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 700 DAY),
  DATE_SUB(NOW(),INTERVAL 698 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008')
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id=@y24;

-- Cohort A: Grade 11 scores in 2025/2026
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c11a25,@c11a26), sub.id, ac.id, @y25,
  ROUND(65+RAND()*30,1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 340 DAY),
  DATE_SUB(NOW(),INTERVAL 338 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008')
  AND sub.code IN ('ENG','MAT','BIO','CHM','PHY')
  AND ac.academic_year_id=@y25;

-- Cohort A: Grade 12 scores in 2026/2027 (current — in progress)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, @c12a26, sub.id, ac.id, @y26,
  ROUND(68+RAND()*28,1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 10 DAY),
  DATE_SUB(NOW(),INTERVAL 8 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008')
  AND sub.code IN ('ENG','MAT','BIO','CHM','PHY')
  AND ac.academic_year_id=@y26
  AND s.current_class_id=@c12a26;

-- Cohort B Grade 11 repeat (Youga Harris — lower marks, failed 2024/2025)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c11a24,@c11a26), sub.id, ac.id, @y24,
  ROUND(35+RAND()*30,1), ac.max_marks, -- below 70, hence repeat
  @enteredBy, DATE_SUB(NOW(),INTERVAL 700 DAY),
  DATE_SUB(NOW(),INTERVAL 698 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id='KHS-2023-3004'
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id=@y24;

-- Cohort D — Historical transcript scores (2023/2024 graduates)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c12a23,@c12a26), sub.id, ac.id, @y23,
  ROUND(70+RAND()*25,1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 1040 DAY),
  DATE_SUB(NOW(),INTERVAL 1038 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2020-5001','KHS-2020-5002','KHS-2020-5003','KHS-2020-5004','KHS-2020-5005')
  AND sub.code IN ('ENG','MAT','BIO','CHM','HIS')
  AND ac.academic_year_id=@y23;

-- Cohort E — 2024/2025 graduates
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c12a24,@c12a26), sub.id, ac.id, @y24,
  ROUND(72+RAND()*23,1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 680 DAY),
  DATE_SUB(NOW(),INTERVAL 678 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2021-6001','KHS-2021-6002','KHS-2021-6003','KHS-2021-6004','KHS-2021-6005')
  AND sub.code IN ('ENG','MAT','BIO','CHM','HIS')
  AND ac.academic_year_id=@y24;

-- Cohort F — 2025/2026 graduates
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c12a25,@c12a26), sub.id, ac.id, @y25,
  ROUND(74+RAND()*21,1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 320 DAY),
  DATE_SUB(NOW(),INTERVAL 318 DAY), @approvedBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2022-7001','KHS-2022-7002','KHS-2022-7003','KHS-2022-7004','KHS-2022-7005','KHS-2022-7006')
  AND sub.code IN ('ENG','MAT','BIO','CHM','HIS')
  AND ac.academic_year_id=@y25;

-- ============================================================
-- REPORT CARDS — historical published gradesheets
-- ============================================================
-- 2023/2024 — Cohort A (Grade 9)
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c9a23,@c9a26), @y23,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(64+RAND()*28,1),
  ELT(1+FLOOR(RAND()*4),'Excellent','Very Good','Good','Satisfactory'),
  ELT(1+FLOOR(RAND()*4),'Shows excellent potential.','Hardworking and dedicated.','Good academic progress.','Consistent effort throughout the year.'),
  'Promoted to Grade 10. Well done!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 1000 DAY), DATE_SUB(NOW(),INTERVAL 998 DAY), @approvedBy
FROM students s WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008')
ON DUPLICATE KEY UPDATE status='published';

-- 2024/2025 — Cohort A (Grade 10)
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c10a24,@c10a26), @y24,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(87+RAND()*12,1), ROUND(66+RAND()*26,1),
  ELT(1+FLOOR(RAND()*4),'Excellent','Very Good','Good','Satisfactory'),
  ELT(1+FLOOR(RAND()*3),'Outstanding performance.','Remarkable improvement this year.','Performed well in all subjects.'),
  'Promoted to Grade 11. Keep up the great work!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 640 DAY), DATE_SUB(NOW(),INTERVAL 638 DAY), @approvedBy
FROM students s WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008')
ON DUPLICATE KEY UPDATE status='published';

-- 2025/2026 — Cohort A (Grade 11)
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c11a25,@c11a26), @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(88+RAND()*11,1), ROUND(68+RAND()*24,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Ready for final year.','Strong academic foundation.','Excellent preparation for Grade 12.'),
  'Promoted to Grade 12. Best wishes for your final year!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 280 DAY), DATE_SUB(NOW(),INTERVAL 278 DAY), @approvedBy
FROM students s WHERE s.student_id IN ('KHS-2023-2001','KHS-2023-2002','KHS-2023-2003','KHS-2023-2004','KHS-2023-2005','KHS-2023-2006','KHS-2023-2007','KHS-2023-2008')
ON DUPLICATE KEY UPDATE status='published';

-- Youga Harris REPEAT report card (failed 2024/2025)
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c11a24,@c11a26), @y24,
  12, 7, 3, 63.2, 48.6, 'Fair',
  'Youga struggled this year. Additional academic support is recommended.',
  'Due to insufficient academic performance, Youga will repeat Grade 11. A support plan will be provided.',
  'Repeating', 'published',
  DATE_SUB(NOW(),INTERVAL 640 DAY), DATE_SUB(NOW(),INTERVAL 638 DAY), @approvedBy
FROM students s WHERE s.student_id='KHS-2023-3004'
ON DUPLICATE KEY UPDATE status='published';

-- 2023/2024 Graduated cohort D report cards (final year)
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c12a23,@c12a26), @y23,
  18+FLOOR(RAND()*2), FLOOR(RAND()*2), 0,
  ROUND(90+RAND()*9,1), ROUND(72+RAND()*20,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'A model student. Congratulations!','An outstanding member of our school community.','Best wishes for a bright future.'),
  'Congratulations on your graduation! KHS is proud of you.',
  'Graduated', 'published',
  DATE_SUB(NOW(),INTERVAL 1000 DAY), DATE_SUB(NOW(),INTERVAL 998 DAY), @approvedBy
FROM students s WHERE s.student_id IN ('KHS-2020-5001','KHS-2020-5002','KHS-2020-5003','KHS-2020-5004','KHS-2020-5005')
ON DUPLICATE KEY UPDATE status='published';

-- 2024/2025 Graduated cohort E
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c12a24,@c12a26), @y24,
  18+FLOOR(RAND()*2), FLOOR(RAND()*2), 0,
  ROUND(89+RAND()*10,1), ROUND(74+RAND()*20,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*2),'A pleasure to teach. We are proud of your achievement.','Outstanding work ethic and academic commitment.'),
  'Congratulations, Class of 2024/2025! KHS wishes you success.',
  'Graduated', 'published',
  DATE_SUB(NOW(),INTERVAL 640 DAY), DATE_SUB(NOW(),INTERVAL 638 DAY), @approvedBy
FROM students s WHERE s.student_id IN ('KHS-2021-6001','KHS-2021-6002','KHS-2021-6003','KHS-2021-6004','KHS-2021-6005')
ON DUPLICATE KEY UPDATE status='published';

-- 2025/2026 Graduated cohort F
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c12a25,@c12a26), @y25,
  18+FLOOR(RAND()*2), FLOOR(RAND()*2), 0,
  ROUND(90+RAND()*9,1), ROUND(76+RAND()*18,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*2),'You have made KHS proud. Best wishes ahead.','A dedicated and hardworking student throughout.'),
  'Well done, Class of 2025/2026. May you shine wherever you go!',
  'Graduated', 'published',
  DATE_SUB(NOW(),INTERVAL 280 DAY), DATE_SUB(NOW(),INTERVAL 278 DAY), @approvedBy
FROM students s WHERE s.student_id IN ('KHS-2022-7001','KHS-2022-7002','KHS-2022-7003','KHS-2022-7004','KHS-2022-7005','KHS-2022-7006')
ON DUPLICATE KEY UPDATE status='published';

-- ============================================================
-- TRANSCRIPT DOCUMENTS (graduation_records for all cohorts)
-- Update certificate numbers and yearly averages from scores
-- ============================================================
UPDATE graduation_records gr
JOIN (
  SELECT student_id, ROUND(AVG(marks_obtained/max_marks*100),1) avg_pct
  FROM assessment_scores
  WHERE status='approved' AND max_marks>0
  GROUP BY student_id
) sc ON sc.student_id=gr.student_id
SET gr.yearly_average=sc.avg_pct
WHERE gr.yearly_average IS NULL;

-- Apply honours based on actual averages
UPDATE graduation_records
SET honours=CASE
  WHEN yearly_average>=95 THEN 'Summa Cum Laude'
  WHEN yearly_average>=90 THEN 'Magna Cum Laude'
  WHEN yearly_average>=85 THEN 'Cum Laude'
  WHEN yearly_average>=80 THEN 'With Distinction'
  WHEN yearly_average>=75 THEN 'With Merit'
  ELSE NULL
END
WHERE honours IS NULL AND yearly_average IS NOT NULL;

-- ============================================================
-- ANNUAL RESULTS (for transcript queries)
-- ============================================================
-- Build annual_results for cohort A across all 3 completed years
INSERT IGNORE INTO annual_results (student_id,class_id,subject_id,academic_year_id,sem1_average,sem2_average,yearly_average,grade_letter,passed,status)
SELECT
  asc2.student_id,
  asc2.class_id,
  asc2.subject_id,
  asc2.academic_year_id,
  ROUND(AVG(CASE WHEN ac.sequence<=4 THEN asc2.marks_obtained/asc2.max_marks*100 ELSE NULL END),1),
  ROUND(AVG(CASE WHEN ac.sequence> 4 THEN asc2.marks_obtained/asc2.max_marks*100 ELSE NULL END),1),
  ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1),
  CASE
    WHEN AVG(asc2.marks_obtained/asc2.max_marks*100)>=90 THEN 'A'
    WHEN AVG(asc2.marks_obtained/asc2.max_marks*100)>=80 THEN 'B'
    WHEN AVG(asc2.marks_obtained/asc2.max_marks*100)>=70 THEN 'C'
    WHEN AVG(asc2.marks_obtained/asc2.max_marks*100)>=60 THEN 'D'
    ELSE 'F'
  END,
  CASE WHEN AVG(asc2.marks_obtained/asc2.max_marks*100)>=70 THEN 1 ELSE 0 END,
  'published'
FROM assessment_scores asc2
JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
WHERE asc2.status='approved' AND asc2.max_marks>0
  AND asc2.student_id IN (
    SELECT id FROM students WHERE student_id LIKE 'KHS-2023-2%'
    UNION SELECT id FROM students WHERE student_id LIKE 'KHS-2020-5%'
    UNION SELECT id FROM students WHERE student_id LIKE 'KHS-2021-6%'
    UNION SELECT id FROM students WHERE student_id LIKE 'KHS-2022-7%'
  )
GROUP BY asc2.student_id, asc2.class_id, asc2.subject_id, asc2.academic_year_id
ON DUPLICATE KEY UPDATE
  yearly_average=VALUES(yearly_average),
  grade_letter=VALUES(grade_letter),
  passed=VALUES(passed),
  status='published';

-- Class positions for each year/class
SET @rank_var=0;
UPDATE annual_results ar
JOIN (
  SELECT id,
    ROW_NUMBER() OVER (PARTITION BY academic_year_id,class_id ORDER BY yearly_average DESC) AS rn
  FROM annual_results WHERE status='published'
) ranked ON ranked.id=ar.id
SET ar.class_position=ranked.rn;

-- ============================================================
-- ATTENDANCE — historical for graduated cohorts (sample)
-- ============================================================
INSERT IGNORE INTO attendance (student_id,class_id,academic_year_id,date,status,recorded_by)
SELECT s.id, COALESCE(@c12a23,@c12a26), @y23,
  DATE_SUB('2024-06-28', INTERVAL n.n DAY),
  ELT(1+FLOOR(RAND()*10),'Present','Present','Present','Present','Present','Present','Present','Present','Absent','Late'),
  @enteredBy
FROM students s
CROSS JOIN (
  SELECT 1 n UNION SELECT 3 UNION SELECT 5 UNION SELECT 8 UNION SELECT 10
  UNION SELECT 12 UNION SELECT 15 UNION SELECT 17 UNION SELECT 20 UNION SELECT 22
) n
WHERE s.student_id IN ('KHS-2020-5001','KHS-2020-5002','KHS-2020-5003')
  AND DAYOFWEEK(DATE_SUB('2024-06-28', INTERVAL n.n DAY)) BETWEEN 2 AND 6
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ============================================================
-- TRANSFER DOCUMENTS — log in approval_requests
-- ============================================================
INSERT IGNORE INTO approval_requests (module,record_type,record_id,requested_by,status,priority,title,description,decision_at)
VALUES
('admissions','student_transfer',
 (SELECT id FROM students WHERE student_id='KHS-2022-9001' LIMIT 1),
 @regUser,'approved','normal',
 'Transfer Out: Gbanyan Wea — Grade 11',
 'Student relocating to Monrovia with family. Transfer to C.D.B. King High School, Monrovia. Transfer certificate issued.',
 '2025-01-20'),
('admissions','student_transfer',
 (SELECT id FROM students WHERE student_id='KHS-2022-9002' LIMIT 1),
 @regUser,'approved','normal',
 'Transfer Out: Karmen Nimley — Grade 10',
 'Family relocation to Yekepa. Transfer to Yekepa Community School.',
 '2025-03-05'),
('admissions','student_transfer',
 (SELECT id FROM students WHERE student_id='KHS-2025-8001' LIMIT 1),
 @regUser,'approved','normal',
 'Transfer In: Varney Kamara — Grade 12',
 'Student transferring from Ganta United School. Transcripts verified. Placed in Grade 12A.',
 '2025-08-20'),
('admissions','student_transfer',
 (SELECT id FROM students WHERE student_id='KHS-2025-8002' LIMIT 1),
 @regUser,'approved','normal',
 'Transfer In: Tenneh Sumo — Grade 11',
 'Student transferring from Lofa County. Previous school records verified.',
 '2025-08-20');

-- ============================================================
-- ANNOUNCEMENTS for milestone events
-- ============================================================
SET @prinUser=(SELECT id FROM users WHERE email='principal@karnhighschool.edu.lr' LIMIT 1);
INSERT IGNORE INTO announcements (title,message,target,is_public,created_by,published_at) VALUES
('Class of 2023/2024 — Graduation Results Published',
 'We are pleased to announce that the 2023/2024 academic year results and report cards for Grade 12 are now available. Congratulations to all graduating students!',
 'students',1,@prinUser,'2024-06-25 08:00:00'),
('Class of 2024/2025 — Graduation Ceremony',
 'The graduation ceremony for the Class of 2024/2025 will be held on June 27, 2025 at Karnplay City Hall. All graduates and their families are invited.',
 'all',1,@prinUser,'2025-06-01 08:00:00'),
('Promotion Results — Academic Year 2023/2024',
 'Promotion results for all grades have been published. Students who have been promoted should report to their new class on the first day of the 2024/2025 academic year.',
 'all',1,@prinUser,'2024-07-05 08:00:00'),
('Student Transfer Policy Update',
 'Students transferring in or out of KHS must submit completed transfer request forms to the Registrar at least 2 weeks before the intended transfer date.',
 'all',0,@regUser,'2025-01-10 08:00:00');

SET FOREIGN_KEY_CHECKS = 1;

-- ── Verification ──────────────────────────────────────────────
SELECT 'LIFECYCLE SEED COMPLETE:' AS '';
SELECT t,n FROM (
  SELECT 'total_students'       t, COUNT(*) n FROM students
  UNION ALL SELECT 'active_students',      COUNT(*) FROM students WHERE status='Active'
  UNION ALL SELECT 'graduated_students',   COUNT(*) FROM students WHERE status='Graduated'
  UNION ALL SELECT 'transferred_out',      COUNT(*) FROM students WHERE status='Transferred'
  UNION ALL SELECT 'graduation_records',   COUNT(*) FROM graduation_records
  UNION ALL SELECT 'student_movements',    COUNT(*) FROM student_movements
  UNION ALL SELECT 'annual_results',       COUNT(*) FROM annual_results
  UNION ALL SELECT 'total_report_cards',   COUNT(*) FROM report_cards
  UNION ALL SELECT 'published_report_cards',COUNT(*) FROM report_cards WHERE status='published'
  UNION ALL SELECT 'total_assessment_scores',COUNT(*) FROM assessment_scores
) s;

SELECT 'GRADUATING STUDENTS (2026/2027):' AS '';
SELECT s.student_id, CONCAT(s.first_name,' ',s.last_name) name, s.status,
  g.name grade, ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) current_avg
FROM students s
JOIN grades g ON g.id=s.current_grade_id
LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id AND asc2.academic_year_id=(SELECT id FROM academic_years WHERE is_current=1) AND asc2.status='approved' AND asc2.max_marks>0
WHERE g.name='Grade 12'
GROUP BY s.id ORDER BY current_avg DESC;
