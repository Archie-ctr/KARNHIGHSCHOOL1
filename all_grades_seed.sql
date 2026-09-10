-- ============================================================
-- ALL GRADES MULTI-YEAR SEED
-- Seeds students in EVERY grade for 2023/2024 → 2024/2025 → 2025/2026
-- with promotions, report cards, scores, attendance
-- Then shows them in their current grade in 2026/2027
-- ============================================================
USE karnhighschool;
SET FOREIGN_KEY_CHECKS = 0;
SET @pw     = '$2y$10$lpPLPh39oWD5Geze1FXGtO6WjILnivbXvhczHwjrNvek/VLfM2upC';
SET @y23    = (SELECT id FROM academic_years WHERE name='2023/2024');
SET @y24    = (SELECT id FROM academic_years WHERE name='2024/2025');
SET @y25    = (SELECT id FROM academic_years WHERE name='2025/2026');
SET @y26    = (SELECT id FROM academic_years WHERE name='2026/2027');
SET @appBy  = (SELECT id FROM users WHERE email='vprincipal@karnhighschool.edu.lr' LIMIT 1);
SET @entBy  = (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr'    LIMIT 1);
SET @regBy  = (SELECT id FROM users WHERE email='registrar@karnhighschool.edu.lr'  LIMIT 1);
SET @accBy  = (SELECT id FROM users WHERE email='accountant@karnhighschool.edu.lr' LIMIT 1);

-- ============================================================
-- HELPER: get class id for grade+year+section
-- ============================================================
-- Current-year class IDs (2026/2027)
SET @c7a26  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 7'  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c8a26  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8'  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c9a26  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9'  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c10a26 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c11a26 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c12a26 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);

-- 2025/2026 class IDs
SET @c7a25  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 7'  AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c8a25  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8'  AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c9a25  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9'  AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c10a25 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c11a25 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c12a25 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);

-- 2024/2025 class IDs
SET @c7a24  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 7'  AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);
SET @c8a24  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8'  AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);
SET @c9a24  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9'  AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);
SET @c10a24 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);
SET @c11a24 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);
SET @c12a24 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1);

-- 2023/2024 class IDs
SET @c7a23  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 7'  AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c8a23  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8'  AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c9a23  = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9'  AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c10a23 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c11a23 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);
SET @c12a23 = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1);

-- Grade ID helpers
SET @g7  = (SELECT id FROM grades WHERE name='Grade 7');
SET @g8  = (SELECT id FROM grades WHERE name='Grade 8');
SET @g9  = (SELECT id FROM grades WHERE name='Grade 9');
SET @g10 = (SELECT id FROM grades WHERE name='Grade 10');
SET @g11 = (SELECT id FROM grades WHERE name='Grade 11');
SET @g12 = (SELECT id FROM grades WHERE name='Grade 12');

-- ============================================================
-- BLOCK 1: STUDENTS WHO ENTERED GRADE 7 IN 2023/2024
-- Path: Gr7(23/24)→Gr8(24/25)→Gr9(25/26)→Gr10(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2023-G7-001','ADM-G7-001','Varlee',   'T.','Mulbah',  'Male',  '2012-03-14',@g10,@c10a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G7-002','ADM-G7-002','Zoelay',   '',  'Kollie',  'Female','2012-07-08',@g10,@c10a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G7-003','ADM-G7-003','Tetee',    'K.','Freeman', 'Male',  '2012-11-22',@g10,@c10a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G7-004','ADM-G7-004','Mehnyo',   '',  'Konneh',  'Female','2012-05-17',@g10,@c10a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G7-005','ADM-G7-005','Pewee',    'A.','Cooper',  'Male',  '2012-09-30',@g10,@c10a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G7-006','ADM-G7-006','Nenneh',   '',  'Harris',  'Female','2012-02-05',@g10,@c10a26,@y26,'Active','2023-08-21','Nimba');

-- ============================================================
-- BLOCK 2: STUDENTS WHO ENTERED GRADE 8 IN 2023/2024
-- Path: Gr8(23/24)→Gr9(24/25)→Gr10(25/26)→Gr11(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2023-G8-001','ADM-G8-001','Tokay',    'J.','Wea',     'Male',  '2011-01-19',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G8-002','ADM-G8-002','Yarkpolo', '',  'Toe',     'Female','2011-06-04',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G8-003','ADM-G8-003','Flomo',    'M.','Johnson', 'Male',  '2011-10-27',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G8-004','ADM-G8-004','Dewoma',   '',  'Kamara',  'Female','2011-03-12',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G8-005','ADM-G8-005','Karyee',   'T.','Sumo',    'Male',  '2011-08-25',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G8-006','ADM-G8-006','Reeta',    '',  'Williams','Female','2011-12-09',@g11,@c11a26,@y26,'Active','2023-08-21','Nimba');

-- ============================================================
-- BLOCK 3: ENTERED GRADE 9 IN 2023/2024
-- Path: Gr9(23/24)→Gr10(24/25)→Gr11(25/26)→Gr12(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2023-G9-001','ADM-G9-001','Pewu',     'K.','Flumo',   'Male',  '2010-04-07',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G9-002','ADM-G9-002','Nowai',    '',  'Nimley',  'Female','2010-09-20',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G9-003','ADM-G9-003','Garbah',   'J.','Kollie',  'Male',  '2010-01-15',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G9-004','ADM-G9-004','Jebbeh',   '',  'Freeman', 'Female','2010-06-28',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba'),
('KHS-2023-G9-005','ADM-G9-005','Doryen',   'A.','Harris',  'Male',  '2010-11-03',@g12,@c12a26,@y26,'Active','2023-08-21','Nimba');

-- ============================================================
-- BLOCK 4: ENTERED GRADE 7 IN 2024/2025
-- Path: Gr7(24/25)→Gr8(25/26)→Gr9(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2024-G7-001','ADM-G7-101','Flomo',    'A.','Konneh',  'Male',  '2013-02-11',@g9,@c9a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G7-002','ADM-G7-102','Yatta',    '',  'Cooper',  'Female','2013-07-25',@g9,@c9a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G7-003','ADM-G7-103','Tenseh',   'K.','Wea',     'Male',  '2013-04-19',@g9,@c9a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G7-004','ADM-G7-104','Korpu',    '',  'Sumo',    'Female','2013-10-08',@g9,@c9a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G7-005','ADM-G7-105','Miatta',   'J.','Kamara',  'Female','2013-12-30',@g9,@c9a26,@y26,'Active','2024-08-19','Nimba');

-- ============================================================
-- BLOCK 5: ENTERED GRADE 8 IN 2024/2025
-- Path: Gr8(24/25)→Gr9(25/26)→Gr10(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2024-G8-001','ADM-G8-101','Tokpah',   'T.','Kollie',  'Male',  '2012-03-06',@g10,@c10a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G8-002','ADM-G8-102','Flumo',    '',  'Harris',  'Female','2012-08-17',@g10,@c10a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G8-003','ADM-G8-103','Gbanyan',  'K.','Freeman', 'Male',  '2012-01-29',@g10,@c10a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G8-004','ADM-G8-104','Wesseh',   '',  'Johnson', 'Female','2012-11-14',@g10,@c10a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G8-005','ADM-G8-105','Garway',   'M.','Williams','Male',  '2012-05-22',@g10,@c10a26,@y26,'Active','2024-08-19','Nimba');

-- ============================================================
-- BLOCK 6: ENTERED GRADE 9 IN 2024/2025
-- Path: Gr9(24/25)→Gr10(25/26)→Gr11(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2024-G9-001','ADM-G9-101','Saye',     'A.','Flomo',   'Male',  '2011-06-10',@g11,@c11a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G9-002','ADM-G9-102','Lorpu',    '',  'Konneh',  'Female','2011-09-23',@g11,@c11a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G9-003','ADM-G9-103','Mulbah',   'J.','Cooper',  'Male',  '2011-02-07',@g11,@c11a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G9-004','ADM-G9-104','Zoelay',   '',  'Wea',     'Female','2011-07-18',@g11,@c11a26,@y26,'Active','2024-08-19','Nimba'),
('KHS-2024-G9-005','ADM-G9-105','Pewee',    'T.','Sumo',    'Male',  '2011-11-01',@g11,@c11a26,@y26,'Active','2024-08-19','Nimba');

-- ============================================================
-- BLOCK 7: ENTERED GRADE 7 IN 2025/2026
-- Path: Gr7(25/26)→Gr8(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2025-G7-001','ADM-G7-201','Margibi',  'A.','Kollie',  'Male',  '2014-01-14',@g8,@c8a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G7-002','ADM-G7-202','Fassah',   '',  'Freeman', 'Female','2014-04-28',@g8,@c8a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G7-003','ADM-G7-203','Zawolo',   'K.','Harris',  'Male',  '2014-08-11',@g8,@c8a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G7-004','ADM-G7-204','Quiwon',   '',  'Kamara',  'Female','2014-11-05',@g8,@c8a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G7-005','ADM-G7-205','Binda',    'T.','Johnson', 'Male',  '2014-03-22',@g8,@c8a26,@y26,'Active','2025-08-18','Nimba');

-- ============================================================
-- BLOCK 8: ENTERED GRADE 8 IN 2025/2026
-- Path: Gr8(25/26)→Gr9(26/27-CURRENT)
-- ============================================================
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2025-G8-001','ADM-G8-201','Wuo',      'J.','Flomo',   'Male',  '2013-02-28',@g9,@c9a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G8-002','ADM-G8-202','Mamie',    '',  'Konneh',  'Female','2013-06-14',@g9,@c9a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G8-003','ADM-G8-203','Gonlee',   'A.','Cooper',  'Male',  '2013-09-30',@g9,@c9a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G8-004','ADM-G8-204','Pewee',    '',  'Williams','Female','2013-12-17',@g9,@c9a26,@y26,'Active','2025-08-18','Nimba'),
('KHS-2025-G8-005','ADM-G8-205','Togar',    'K.','Sumo',    'Male',  '2013-04-03',@g9,@c9a26,@y26,'Active','2025-08-18','Nimba');

-- ============================================================
-- CREATE PORTAL USERS FOR ALL NEW STUDENTS
-- ============================================================
INSERT IGNORE INTO users (name,email,phone,password_hash,role_id)
SELECT CONCAT(s.first_name,' ',s.last_name),
       LOWER(CONCAT(s.student_id,'@student.karnhighschool.edu.lr')),
       NULL, @pw,
       (SELECT id FROM roles WHERE name='student')
FROM students s
WHERE s.student_id LIKE 'KHS-202%-G%';

UPDATE students s
JOIN users u ON u.email=LOWER(CONCAT(s.student_id,'@student.karnhighschool.edu.lr'))
SET s.user_id=u.id
WHERE s.user_id IS NULL AND s.student_id LIKE 'KHS-202%-G%';

-- ============================================================
-- PROMOTION RECORDS (student_promotions table)
-- ============================================================

-- BLOCK 1 students: Gr7→8→9→10 (now Grade 10)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g7,@g8,@y23,@y24,'2024-07-01',@regBy,'Promoted Grade 7→8 (2023/2024)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G7-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g8,@g9,@y24,@y25,'2025-07-01',@regBy,'Promoted Grade 8→9 (2024/2025)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G7-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g9,@g10,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 9→10 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G7-%';

-- BLOCK 2 students: Gr8→9→10→11 (now Grade 11)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g8,@g9,@y23,@y24,'2024-07-01',@regBy,'Promoted Grade 8→9 (2023/2024)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G8-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g9,@g10,@y24,@y25,'2025-07-01',@regBy,'Promoted Grade 9→10 (2024/2025)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G8-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g10,@g11,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 10→11 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G8-%';

-- BLOCK 3 students: Gr9→10→11→12 (now Grade 12)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g9,@g10,@y23,@y24,'2024-07-01',@regBy,'Promoted Grade 9→10 (2023/2024)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G9-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g10,@g11,@y24,@y25,'2025-07-01',@regBy,'Promoted Grade 10→11 (2024/2025)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G9-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g11,@g12,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 11→12 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2023-G9-%';

-- BLOCK 4: Gr7→8→9 (now Grade 9)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g7,@g8,@y24,@y25,'2025-07-01',@regBy,'Promoted Grade 7→8 (2024/2025)'
FROM students s WHERE s.student_id LIKE 'KHS-2024-G7-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g8,@g9,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 8→9 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2024-G7-%';

-- BLOCK 5: Gr8→9→10 (now Grade 10)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g8,@g9,@y24,@y25,'2025-07-01',@regBy,'Promoted Grade 8→9 (2024/2025)'
FROM students s WHERE s.student_id LIKE 'KHS-2024-G8-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g9,@g10,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 9→10 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2024-G8-%';

-- BLOCK 6: Gr9→10→11 (now Grade 11)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g9,@g10,@y24,@y25,'2025-07-01',@regBy,'Promoted Grade 9→10 (2024/2025)'
FROM students s WHERE s.student_id LIKE 'KHS-2024-G9-%';
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g10,@g11,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 10→11 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2024-G9-%';

-- BLOCK 7: Gr7→8 (now Grade 8)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g7,@g8,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 7→8 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2025-G7-%';

-- BLOCK 8: Gr8→9 (now Grade 9)
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@g8,@g9,@y25,@y26,'2026-07-01',@regBy,'Promoted Grade 8→9 (2025/2026)'
FROM students s WHERE s.student_id LIKE 'KHS-2025-G8-%';

-- ============================================================
-- ASSESSMENT SCORES — one INSERT per block+year combination
-- Using only confirmed configs and class IDs
-- ============================================================

-- Helper subjects
SET @sMAT=(SELECT id FROM subjects WHERE code='MAT');
SET @sENG=(SELECT id FROM subjects WHERE code='ENG');
SET @sBIO=(SELECT id FROM subjects WHERE code='BIO');
SET @sHIS=(SELECT id FROM subjects WHERE code='HIS');
SET @sEGR=(SELECT id FROM subjects WHERE code='EGR');
SET @sCHM=(SELECT id FROM subjects WHERE code='CHM');

-- BLOCK 1 — was Gr7 in 2023/24
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c7a23,@c7a26), sub.id, ac.id, @y23,
  ROUND(60+RAND()*35,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 1060 DAY), DATE_SUB(NOW(),INTERVAL 1058 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G7-%'
  AND sub.code IN ('MAT','EGR','HIS') AND ac.academic_year_id=@y23;

-- BLOCK 1 — was Gr8 in 2024/25
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c8a24,@c8a26), sub.id, ac.id, @y24,
  ROUND(62+RAND()*33,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 700 DAY), DATE_SUB(NOW(),INTERVAL 698 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G7-%'
  AND sub.code IN ('MAT','ENG','HIS') AND ac.academic_year_id=@y24;

-- BLOCK 1 — was Gr9 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c9a25,@c9a26), sub.id, ac.id, @y25,
  ROUND(64+RAND()*31,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G7-%'
  AND sub.code IN ('MAT','ENG','BIO') AND ac.academic_year_id=@y25;

-- BLOCK 2 — was Gr8 in 2023/24
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c8a23,@c8a26), sub.id, ac.id, @y23,
  ROUND(62+RAND()*33,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 1060 DAY), DATE_SUB(NOW(),INTERVAL 1058 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G8-%'
  AND sub.code IN ('MAT','EGR','HIS') AND ac.academic_year_id=@y23;

-- BLOCK 2 — was Gr9 in 2024/25
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c9a24,@c9a26), sub.id, ac.id, @y24,
  ROUND(64+RAND()*31,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 700 DAY), DATE_SUB(NOW(),INTERVAL 698 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G8-%'
  AND sub.code IN ('MAT','ENG','BIO') AND ac.academic_year_id=@y24;

-- BLOCK 2 — was Gr10 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c10a25,@c10a26), sub.id, ac.id, @y25,
  ROUND(66+RAND()*29,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G8-%'
  AND sub.code IN ('MAT','ENG','BIO','CHM') AND ac.academic_year_id=@y25;

-- BLOCK 3 — was Gr9 in 2023/24
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c9a23,@c9a26), sub.id, ac.id, @y23,
  ROUND(63+RAND()*32,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 1060 DAY), DATE_SUB(NOW(),INTERVAL 1058 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G9-%'
  AND sub.code IN ('MAT','ENG','HIS') AND ac.academic_year_id=@y23;

-- BLOCK 3 — was Gr10 in 2024/25
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c10a24,@c10a26), sub.id, ac.id, @y24,
  ROUND(65+RAND()*30,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 700 DAY), DATE_SUB(NOW(),INTERVAL 698 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G9-%'
  AND sub.code IN ('MAT','ENG','BIO','CHM') AND ac.academic_year_id=@y24;

-- BLOCK 3 — was Gr11 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c11a25,@c11a26), sub.id, ac.id, @y25,
  ROUND(67+RAND()*28,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2023-G9-%'
  AND sub.code IN ('ENG','MAT','BIO','CHM') AND ac.academic_year_id=@y25;

-- BLOCK 4 — was Gr7 in 2024/25
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c7a24,@c7a26), sub.id, ac.id, @y24,
  ROUND(61+RAND()*34,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 700 DAY), DATE_SUB(NOW(),INTERVAL 698 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2024-G7-%'
  AND sub.code IN ('MAT','EGR','HIS') AND ac.academic_year_id=@y24;

-- BLOCK 4 — was Gr8 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c8a25,@c8a26), sub.id, ac.id, @y25,
  ROUND(63+RAND()*32,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2024-G7-%'
  AND sub.code IN ('MAT','ENG','HIS') AND ac.academic_year_id=@y25;

-- BLOCK 5 — was Gr8 in 2024/25
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c8a24,@c8a26), sub.id, ac.id, @y24,
  ROUND(62+RAND()*33,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 700 DAY), DATE_SUB(NOW(),INTERVAL 698 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2024-G8-%'
  AND sub.code IN ('MAT','EGR','HIS') AND ac.academic_year_id=@y24;

-- BLOCK 5 — was Gr9 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c9a25,@c9a26), sub.id, ac.id, @y25,
  ROUND(64+RAND()*31,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2024-G8-%'
  AND sub.code IN ('MAT','ENG','BIO') AND ac.academic_year_id=@y25;

-- BLOCK 6 — was Gr9 in 2024/25
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c9a24,@c9a26), sub.id, ac.id, @y24,
  ROUND(63+RAND()*32,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 700 DAY), DATE_SUB(NOW(),INTERVAL 698 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2024-G9-%'
  AND sub.code IN ('MAT','ENG','BIO') AND ac.academic_year_id=@y24;

-- BLOCK 6 — was Gr10 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c10a25,@c10a26), sub.id, ac.id, @y25,
  ROUND(65+RAND()*30,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2024-G9-%'
  AND sub.code IN ('MAT','ENG','BIO','CHM') AND ac.academic_year_id=@y25;

-- BLOCK 7 — was Gr7 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c7a25,@c7a26), sub.id, ac.id, @y25,
  ROUND(60+RAND()*35,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2025-G7-%'
  AND sub.code IN ('MAT','EGR','HIS') AND ac.academic_year_id=@y25;

-- BLOCK 8 — was Gr8 in 2025/26
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, COALESCE(@c8a25,@c8a26), sub.id, ac.id, @y25,
  ROUND(61+RAND()*34,1), 100.00, @entBy,
  DATE_SUB(NOW(),INTERVAL 340 DAY), DATE_SUB(NOW(),INTERVAL 338 DAY), @appBy, 'approved'
FROM students s CROSS JOIN subjects sub CROSS JOIN assessment_configs ac
WHERE s.student_id LIKE 'KHS-2025-G8-%'
  AND sub.code IN ('MAT','EGR','HIS') AND ac.academic_year_id=@y25;

-- ============================================================
-- REPORT CARDS — one per block per completed year
-- ============================================================
-- Macro: insert report card for a group of students for a given class+year
-- BLOCK 1: Gr7 in 23/24
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c7a23,@c7a26), @y23,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(84+RAND()*15,1), ROUND(65+RAND()*27,1),
  ELT(1+FLOOR(RAND()*4),'Excellent','Very Good','Good','Satisfactory'),
  ELT(1+FLOOR(RAND()*3),'Excellent first year.','Great start to school.','Promising student.'),
  'Promoted to Grade 8. Well done!', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 998 DAY), DATE_SUB(NOW(),INTERVAL 997 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G7-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 1: Gr8 in 24/25
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c8a24,@c8a26), @y24,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(67+RAND()*25,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Continued excellence.','Good progress in Grade 8.','Strong academic growth.'),
  'Promoted to Grade 9.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 638 DAY), DATE_SUB(NOW(),INTERVAL 637 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G7-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 1: Gr9 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c9a25,@c9a26), @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*2), FLOOR(RAND()*2),
  ROUND(86+RAND()*13,1), ROUND(68+RAND()*24,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Impressive work in Grade 9.','Ready for senior school.','Excellent preparation for Grade 10.'),
  'Promoted to Grade 10.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G7-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 2: Gr8 in 23/24
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c8a23,@c8a26), @y23,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(84+RAND()*15,1), ROUND(66+RAND()*26,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Good academic performance.','Works well with others.','Consistent effort shown.'),
  'Promoted to Grade 9.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 998 DAY), DATE_SUB(NOW(),INTERVAL 997 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G8-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 2: Gr9 in 24/25
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c9a24,@c9a26), @y24,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(68+RAND()*24,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Fine work throughout the year.','Good discipline and focus.','Above average student.'),
  'Promoted to Grade 10.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 638 DAY), DATE_SUB(NOW(),INTERVAL 637 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G8-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 2: Gr10 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c10a25,@c10a26), @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*2), FLOOR(RAND()*2),
  ROUND(86+RAND()*13,1), ROUND(69+RAND()*23,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Senior school entry ready.','Performed well at Grade 10.','Strong foundation for Grade 11.'),
  'Promoted to Grade 11.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G8-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 3: Gr9 in 23/24
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c9a23,@c9a26), @y23,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(84+RAND()*15,1), ROUND(67+RAND()*25,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Shows great potential.','Hard working and committed.','Outstanding student.'),
  'Promoted to Grade 10.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 998 DAY), DATE_SUB(NOW(),INTERVAL 997 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G9-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 3: Gr10 in 24/25
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c10a24,@c10a26), @y24,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(69+RAND()*23,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Excellent academic progress.','Motivated and diligent.','Sets a good example.'),
  'Promoted to Grade 11.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 638 DAY), DATE_SUB(NOW(),INTERVAL 637 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G9-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 3: Gr11 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c11a25,@c11a26), @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*2), 0,
  ROUND(87+RAND()*12,1), ROUND(70+RAND()*22,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Ready for final year.','Excellent penultimate year performance.','Prepared well for Grade 12.'),
  'Promoted to Grade 12.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2023-G9-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 4: Gr7 in 24/25
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c7a24,@c7a26), @y24,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(83+RAND()*16,1), ROUND(65+RAND()*27,1),
  ELT(1+FLOOR(RAND()*4),'Excellent','Very Good','Good','Satisfactory'),
  'Good first year at KHS.', 'Promoted to Grade 8.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 638 DAY), DATE_SUB(NOW(),INTERVAL 637 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2024-G7-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 4: Gr8 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c8a25,@c8a26), @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(84+RAND()*15,1), ROUND(67+RAND()*25,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  'Continued improvement in Grade 8.', 'Promoted to Grade 9.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2024-G7-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 5: Gr8 in 24/25
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c8a24,@c8a26), @y24,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(83+RAND()*16,1), ROUND(66+RAND()*26,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  'Good performance throughout.', 'Promoted to Grade 9.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 638 DAY), DATE_SUB(NOW(),INTERVAL 637 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2024-G8-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 5: Gr9 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c9a25,@c9a26), @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*2), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(68+RAND()*24,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  'Strong Grade 9 performance.', 'Promoted to Grade 10.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2024-G8-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 6: Gr9 in 24/25
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c9a24,@c9a26), @y24,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(84+RAND()*15,1), ROUND(67+RAND()*25,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  'Good work in Grade 9.', 'Promoted to Grade 10.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 638 DAY), DATE_SUB(NOW(),INTERVAL 637 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2024-G9-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 6: Gr10 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c10a25,@c10a26), @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*2), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(68+RAND()*24,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  'Well prepared for Grade 11.', 'Promoted to Grade 11.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2024-G9-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 7: Gr7 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c7a25,@c7a26), @y25,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(83+RAND()*16,1), ROUND(65+RAND()*27,1),
  ELT(1+FLOOR(RAND()*4),'Excellent','Very Good','Good','Satisfactory'),
  'Good beginning at KHS.', 'Promoted to Grade 8.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2025-G7-%'
ON DUPLICATE KEY UPDATE status='published';

-- BLOCK 8: Gr8 in 25/26
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, COALESCE(@c8a25,@c8a26), @y25,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(83+RAND()*16,1), ROUND(65+RAND()*27,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  'Good year in Grade 8.', 'Promoted to Grade 9.', 'Promoted','published',
  DATE_SUB(NOW(),INTERVAL 278 DAY), DATE_SUB(NOW(),INTERVAL 277 DAY), @appBy
FROM students s WHERE s.student_id LIKE 'KHS-2025-G8-%'
ON DUPLICATE KEY UPDATE status='published';

-- ============================================================
-- ATTENDANCE — past years for all blocks
-- ============================================================
INSERT IGNORE INTO attendance (student_id,class_id,academic_year_id,date,status,recorded_by)
SELECT s.id, s.current_class_id, @y26,
  DATE_SUB(CURDATE(), INTERVAL n.n DAY),
  ELT(1+FLOOR(RAND()*10),'Present','Present','Present','Present','Present','Present','Present','Present','Absent','Late'),
  @entBy
FROM students s
CROSS JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
  UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
  UNION SELECT 14 UNION SELECT 15) n
WHERE s.student_id LIKE 'KHS-202%-G%'
  AND s.status='Active'
  AND DAYOFWEEK(DATE_SUB(CURDATE(), INTERVAL n.n DAY)) BETWEEN 2 AND 6
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ============================================================
-- FEE PAYMENTS — one payment per active student current year
-- ============================================================
INSERT IGNORE INTO payments (receipt_number,student_id,fee_structure_id,amount,currency,payment_method,payment_date,academic_year_id,recorded_by)
SELECT CONCAT('REC-NEW-',LPAD((@rownum:=@rownum+1),5,'0')),
  s.id, fs.id, fs.amount, fs.currency,
  ELT(1+FLOOR(RAND()*3),'Cash','Mobile money','Bank transfer'),
  DATE_SUB(CURDATE(),INTERVAL FLOOR(RAND()*50) DAY),
  @y26, @accBy
FROM students s
CROSS JOIN fee_structures fs
JOIN (SELECT @rownum:=5000) r
WHERE s.student_id LIKE 'KHS-202%-G%'
  AND s.status='Active'
  AND fs.academic_year_id=@y26
  AND fs.is_mandatory=1;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Verification ──────────────────────────────────────────────
SELECT 'ALL-GRADES SEED COMPLETE:' AS '';
SELECT t,n FROM (
  SELECT 'total_students'          t, COUNT(*) n FROM students
  UNION ALL SELECT 'active_students',         COUNT(*) FROM students WHERE status='Active'
  UNION ALL SELECT 'student_promotions',      COUNT(*) FROM student_promotions
  UNION ALL SELECT 'published_report_cards',  COUNT(*) FROM report_cards WHERE status='published'
  UNION ALL SELECT 'total_assessment_scores', COUNT(*) FROM assessment_scores
  UNION ALL SELECT 'attendance_records',      COUNT(*) FROM attendance
  UNION ALL SELECT 'payments',               COUNT(*) FROM payments
) x;

SELECT 'STUDENTS PER GRADE (current year):' AS '';
SELECT g.name grade, COUNT(*) students
FROM students s JOIN grades g ON g.id=s.current_grade_id
WHERE s.status='Active'
GROUP BY g.id ORDER BY g.sequence;
