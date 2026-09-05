-- ============================================================
-- KHSMIS — Complete Demo / Test Data Seed
-- Run in phpMyAdmin or: pipe to mysql
-- ============================================================
USE karnhighschool;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Helpers ──────────────────────────────────────────────────
-- We use the existing academic year id=1 (2026/2027)
-- We use existing grades, classes, subjects, teachers

-- ============================================================
-- 1. EXTRA TEACHERS (total → 8)
-- ============================================================
INSERT IGNORE INTO teachers (teacher_id,first_name,last_name,gender,phone,email,qualification,specialization,employment_date,status) VALUES
('TCH-005','Agnes',    'Konneh',  'Female','+231 880 100 105','akonneh@karnhighschool.edu.lr',  'B.Sc. Biology',      'Sciences',        '2021-09-01','Active'),
('TCH-006','Joseph',   'Freeman', 'Male',  '+231 880 100 106','jfreeman@karnhighschool.edu.lr', 'B.A. History',       'Social Studies',  '2020-09-01','Active'),
('TCH-007','Patricia', 'Harris',  'Female','+231 880 100 107','pharris@karnhighschool.edu.lr',  'B.Ed. English',      'English Language','2022-09-01','Active'),
('TCH-008','Daniel',   'Sumo',    'Male',  '+231 880 100 108','dsumo@karnhighschool.edu.lr',    'B.Sc. Chemistry',    'Sciences',        '2019-09-01','Active');

-- Link teacher users to teacher records (existing)
UPDATE teachers t JOIN users u ON u.email=t.email SET t.user_id=u.id WHERE t.user_id IS NULL;

-- ============================================================
-- 2. CLASSES — assign teachers, ensure 6 classes for senior
-- ============================================================
-- Assign class teachers to classes
UPDATE classes c
JOIN grades g ON g.id=c.grade_id
JOIN teachers t ON t.teacher_id='TCH-001'
SET c.teacher_id=t.id
WHERE g.name='Grade 8' AND c.section='A';

UPDATE classes c
JOIN grades g ON g.id=c.grade_id
JOIN teachers t ON t.teacher_id='TCH-002'
SET c.teacher_id=t.id
WHERE g.name='Grade 10' AND c.section='A';

UPDATE classes c
JOIN grades g ON g.id=c.grade_id
JOIN teachers t ON t.teacher_id='TCH-003'
SET c.teacher_id=t.id
WHERE g.name='Grade 11' AND c.section='A';

UPDATE classes c
JOIN grades g ON g.id=c.grade_id
JOIN teachers t ON t.teacher_id='TCH-004'
SET c.teacher_id=t.id
WHERE g.name='Grade 12' AND c.section='A';

-- ============================================================
-- 3. STUDENTS (30 total across grades 7–12)
-- ============================================================
-- We'll add 26 more students across the classes
-- Get class IDs dynamically using subqueries

INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
-- Grade 7A
('KHS-2026-0010','ADM-2026-0010','Fatu',    'M.',   'Kollie',   'Female','2014-03-12','+231 880 201 001',(SELECT id FROM grades WHERE name='Grade 7'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 7' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0011','ADM-2026-0011','James',   'K.',   'Nimley',   'Male',  '2014-07-22','+231 880 201 002',(SELECT id FROM grades WHERE name='Grade 7'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 7' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0012','ADM-2026-0012','Mary',    '',     'Flomo',    'Female','2014-01-05',NULL              ,(SELECT id FROM grades WHERE name='Grade 7'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 7' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 8A (has existing students, add more)
('KHS-2026-0013','ADM-2026-0013','Samuel',  'T.',   'Wea',      'Male',  '2013-05-17','+231 880 201 003',(SELECT id FROM grades WHERE name='Grade 8'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0014','ADM-2026-0014','Rebecca', '',     'Sumo',     'Female','2013-09-30',NULL              ,(SELECT id FROM grades WHERE name='Grade 8'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0015','ADM-2026-0015','Thomas',  'E.',   'Kamara',   'Male',  '2013-11-08','+231 880 201 005',(SELECT id FROM grades WHERE name='Grade 8'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 9A
('KHS-2026-0016','ADM-2026-0016','Patience','A.',   'Freeman',  'Female','2012-06-14','+231 880 201 006',(SELECT id FROM grades WHERE name='Grade 9'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0017','ADM-2026-0017','Moses',   '',     'Cooper',   'Male',  '2012-02-27',NULL              ,(SELECT id FROM grades WHERE name='Grade 9'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0018','ADM-2026-0018','Hawa',    'J.',   'Kollie',   'Female','2012-10-03','+231 880 201 008',(SELECT id FROM grades WHERE name='Grade 9'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0019','ADM-2026-0019','Peter',   'A.',   'Toe',      'Male',  '2012-04-19',NULL              ,(SELECT id FROM grades WHERE name='Grade 9'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 10A (has existing Samuel Kollie, add more)
('KHS-2026-0020','ADM-2026-0020','Grace',   '',     'Williams', 'Female','2011-08-11','+231 880 201 010',(SELECT id FROM grades WHERE name='Grade 10'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0021','ADM-2026-0021','David',   'M.',   'Flomo',    'Male',  '2011-12-25',NULL              ,(SELECT id FROM grades WHERE name='Grade 10'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0022','ADM-2026-0022','Ruth',    'E.',   'Konneh',   'Female','2011-03-07','+231 880 201 012',(SELECT id FROM grades WHERE name='Grade 10'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 10B
('KHS-2026-0023','ADM-2026-0023','Paul',    'J.',   'Harris',   'Male',  '2011-06-18',NULL              ,(SELECT id FROM grades WHERE name='Grade 10'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.section='B' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0024','ADM-2026-0024','Esther',  '',     'Nimley',   'Female','2011-09-22','+231 880 201 014',(SELECT id FROM grades WHERE name='Grade 10'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.section='B' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 11A (has existing Emmanuel Toe)
('KHS-2026-0025','ADM-2026-0025','Agnes',   'K.',   'Wea',      'Female','2010-01-30','+231 880 201 015',(SELECT id FROM grades WHERE name='Grade 11'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0026','ADM-2026-0026','Joseph',  'T.',   'Sumo',     'Male',  '2010-07-14',NULL              ,(SELECT id FROM grades WHERE name='Grade 11'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0027','ADM-2026-0027','Comfort', '',     'Freeman',  'Female','2010-11-05','+231 880 201 017',(SELECT id FROM grades WHERE name='Grade 11'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 11B
('KHS-2026-0028','ADM-2026-0028','Michael', 'A.',   'Kollie',   'Male',  '2010-04-23',NULL              ,(SELECT id FROM grades WHERE name='Grade 11'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.section='B' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0029','ADM-2026-0029','Sarah',   'J.',   'Cooper',   'Female','2010-08-09','+231 880 201 019',(SELECT id FROM grades WHERE name='Grade 11'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.section='B' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 12A
('KHS-2026-0030','ADM-2026-0030','Daniel',  'E.',   'Harris',   'Male',  '2009-02-11','+231 880 201 020',(SELECT id FROM grades WHERE name='Grade 12'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0031','ADM-2026-0031','Priscilla','M.',  'Flomo',    'Female','2009-06-27',NULL              ,(SELECT id FROM grades WHERE name='Grade 12'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0032','ADM-2026-0032','Anthony', 'K.',   'Konneh',   'Male',  '2009-10-14','+231 880 201 022',(SELECT id FROM grades WHERE name='Grade 12'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.section='A' LIMIT 1),1,'Active','2026-08-18','Nimba'),
-- Grade 12B
('KHS-2026-0033','ADM-2026-0033','Victoria','',     'Toe',      'Female','2009-03-08',NULL              ,(SELECT id FROM grades WHERE name='Grade 12'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.section='B' LIMIT 1),1,'Active','2026-08-18','Nimba'),
('KHS-2026-0034','ADM-2026-0034','Philip',  'A.',   'Nimley',   'Male',  '2009-08-30','+231 880 201 024',(SELECT id FROM grades WHERE name='Grade 12'),(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.section='B' LIMIT 1),1,'Active','2026-08-18','Nimba');

-- Fix existing students: link to correct classes
UPDATE students SET current_class_id=(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8'  AND c.section='A' LIMIT 1) WHERE student_id='KHS-2024-0184';
UPDATE students SET current_class_id=(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.section='A' LIMIT 1) WHERE student_id='KHS-2024-0183';
UPDATE students SET current_class_id=(SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.section='A' LIMIT 1) WHERE student_id='KHS-2024-0181';

-- ============================================================
-- 4. GUARDIANS & LINKS
-- ============================================================
INSERT IGNORE INTO guardians (first_name,last_name,relationship,phone,email) VALUES
('Mary',    'Kollie',  'Mother',  '+231 880 301 001', 'mkollie@gmail.com'),
('John',    'Nimley',  'Father',  '+231 880 301 002', NULL),
('Agnes',   'Freeman', 'Mother',  '+231 880 301 003', NULL),
('Peter',   'Kamara',  'Father',  '+231 880 301 004', 'pkamara@gmail.com'),
('Ruth',    'Sumo',    'Mother',  '+231 880 301 005', NULL),
('James',   'Cooper',  'Father',  '+231 880 301 006', NULL),
('Comfort', 'Harris',  'Mother',  '+231 880 301 007', 'charris@gmail.com'),
('Thomas',  'Wea',     'Father',  '+231 880 301 008', NULL);

-- Link guardians to students (first 8 new students)
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id, g.id, 1
FROM students s, guardians g
WHERE s.student_id='KHS-2026-0010' AND g.first_name='Mary' AND g.last_name='Kollie' LIMIT 1;
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id, g.id, 1
FROM students s, guardians g
WHERE s.student_id='KHS-2026-0011' AND g.first_name='John' AND g.last_name='Nimley' LIMIT 1;

-- ============================================================
-- 5. TEACHER ASSIGNMENTS (teachers → subjects → classes)
-- ============================================================
-- Sarah Williams (TCH-001) → Mathematics → Grade 8A, 9A, 10A
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-001' AND s.code='MAT' AND g.name IN ('Grade 8','Grade 9','Grade 10') AND c.section='A';

-- Robert Brown (TCH-002) → English Language → Grade 10A, 11A, 12A
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-002' AND s.code='ENG' AND g.name IN ('Grade 10','Grade 11','Grade 12') AND c.section='A';

-- Grace Flomo / TCH-003 → Biology → Grade 10A, 11A, 12A
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-003' AND s.code='BIO' AND g.name IN ('Grade 10','Grade 11','Grade 12') AND c.section='A';

-- Emmanuel Konneh / TCH-004 → History → Grade 9A, 10A, 11A
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-004' AND s.code='HIS' AND g.name IN ('Grade 9','Grade 10','Grade 11') AND c.section='A';

-- Agnes Konneh / TCH-005 → Chemistry → Grade 10A,10B, 11A, 12A
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-005' AND s.code='CHM' AND g.name IN ('Grade 10','Grade 11','Grade 12');

-- Joseph Freeman / TCH-006 → Civics → Grade 8A, 9A
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-006' AND s.code='CIV' AND g.name IN ('Grade 8','Grade 9') AND c.section='A';

-- Patricia Harris / TCH-007 → English Grammar → Grade 7A, 8A, 9A
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-007' AND s.code='EGR' AND g.name IN ('Grade 7','Grade 8','Grade 9') AND c.section='A';

-- Daniel Sumo / TCH-008 → Physics → Grade 11A, 11B, 12A, 12B
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id, s.id, c.id, 1
FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-008' AND s.code='PHY' AND g.name IN ('Grade 11','Grade 12');

-- ============================================================
-- 6. TIMETABLE (Grade 10A — Monday–Friday, 8 periods)
-- ============================================================
-- Get IDs once
SET @cls10a = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.section='A' AND c.academic_year_id=1 LIMIT 1);
SET @mat    = (SELECT id FROM subjects WHERE code='MAT' LIMIT 1);
SET @eng    = (SELECT id FROM subjects WHERE code='ENG' LIMIT 1);
SET @bio    = (SELECT id FROM subjects WHERE code='BIO' LIMIT 1);
SET @chm    = (SELECT id FROM subjects WHERE code='CHM' LIMIT 1);
SET @his    = (SELECT id FROM subjects WHERE code='HIS' LIMIT 1);
SET @civ    = (SELECT id FROM subjects WHERE code='CIV' LIMIT 1);
SET @phy    = (SELECT id FROM subjects WHERE code='PHY' LIMIT 1);
SET @phe    = (SELECT id FROM subjects WHERE code='PHE' LIMIT 1);
SET @t1     = (SELECT id FROM teachers WHERE teacher_id='TCH-001' LIMIT 1);
SET @t2     = (SELECT id FROM teachers WHERE teacher_id='TCH-002' LIMIT 1);
SET @t3     = (SELECT id FROM teachers WHERE teacher_id='TCH-003' LIMIT 1);
SET @t4     = (SELECT id FROM teachers WHERE teacher_id='TCH-004' LIMIT 1);
SET @t5     = (SELECT id FROM teachers WHERE teacher_id='TCH-005' LIMIT 1);
SET @t8     = (SELECT id FROM teachers WHERE teacher_id='TCH-008' LIMIT 1);

INSERT IGNORE INTO timetable (class_id,subject_id,teacher_id,day_of_week,period_slot,start_time,end_time,academic_year_id) VALUES
-- Monday
(@cls10a,@mat,@t1,1,1,'08:00','08:45',1),
(@cls10a,@eng,@t2,1,2,'08:45','09:30',1),
(@cls10a,@bio,@t3,1,3,'09:45','10:30',1),
(@cls10a,@chm,@t5,1,4,'10:30','11:15',1),
(@cls10a,@his,@t4,1,5,'11:30','12:15',1),
(@cls10a,@phy,@t8,1,6,'12:15','13:00',1),
(@cls10a,@phe,NULL,1,7,'14:00','14:45',1),
(@cls10a,@civ,@t4,1,8,'14:45','15:30',1),
-- Tuesday
(@cls10a,@bio,@t3,2,1,'08:00','08:45',1),
(@cls10a,@mat,@t1,2,2,'08:45','09:30',1),
(@cls10a,@chm,@t5,2,3,'09:45','10:30',1),
(@cls10a,@eng,@t2,2,4,'10:30','11:15',1),
(@cls10a,@phy,@t8,2,5,'11:30','12:15',1),
(@cls10a,@his,@t4,2,6,'12:15','13:00',1),
-- Wednesday
(@cls10a,@mat,@t1,3,1,'08:00','08:45',1),
(@cls10a,@chm,@t5,3,2,'08:45','09:30',1),
(@cls10a,@eng,@t2,3,3,'09:45','10:30',1),
(@cls10a,@bio,@t3,3,4,'10:30','11:15',1),
(@cls10a,@phe,NULL,3,5,'11:30','12:15',1),
-- Thursday
(@cls10a,@his,@t4,4,1,'08:00','08:45',1),
(@cls10a,@phy,@t8,4,2,'08:45','09:30',1),
(@cls10a,@mat,@t1,4,3,'09:45','10:30',1),
(@cls10a,@civ,@t4,4,4,'10:30','11:15',1),
(@cls10a,@eng,@t2,4,5,'11:30','12:15',1),
(@cls10a,@chm,@t5,4,6,'12:15','13:00',1),
-- Friday
(@cls10a,@bio,@t3,5,1,'08:00','08:45',1),
(@cls10a,@mat,@t1,5,2,'08:45','09:30',1),
(@cls10a,@phe,NULL,5,3,'09:45','10:30',1),
(@cls10a,@his,@t4,5,4,'10:30','11:15',1),
(@cls10a,@phy,@t8,5,5,'11:30','12:15',1),
(@cls10a,@eng,@t2,5,6,'12:15','13:00',1);

-- ============================================================
-- 7. ASSESSMENT SCORES (marks for Grade 10A — all configs)
-- ============================================================
-- Subjects to mark in Grade 10A: MAT, ENG, BIO, CHM, HIS
-- Students in Grade 10A: existing + new ones

-- Helper: insert marks for each student in Grade 10A per config
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, @cls10a, sub.id, ac.id, 1,
  ROUND(55 + (RAND() * 40), 1),  -- random mark 55–95
  ac.max_marks,
  (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr' LIMIT 1),
  DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*30) DAY),
  (SELECT id FROM users WHERE email='vp@karnhighschool.edu.lr' LIMIT 1),
  'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_class_id = @cls10a
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id = 1
  AND ac.type = 'period';  -- periods only (not exam)

-- Semester exam marks (slightly different range)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, @cls10a, sub.id, ac.id, 1,
  ROUND(50 + (RAND() * 45), 1),
  ac.max_marks,
  (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr' LIMIT 1),
  DATE_SUB(NOW(), INTERVAL FLOOR(RAND()*15) DAY),
  (SELECT id FROM users WHERE email='vp@karnhighschool.edu.lr' LIMIT 1),
  'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_class_id = @cls10a
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id = 1
  AND ac.type = 'exam';

-- Draft marks for teacher to submit (Grade 8A — submitted, awaiting approval)
SET @cls8a = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8' AND c.section='A' AND c.academic_year_id=1 LIMIT 1);

INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,status)
SELECT s.id, @cls8a, sub.id, ac.id, 1,
  ROUND(45 + (RAND() * 50), 1),
  ac.max_marks,
  (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr' LIMIT 1),
  DATE_SUB(NOW(), INTERVAL 2 DAY),
  'submitted'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_class_id = @cls8a
  AND sub.code IN ('MAT','EGR')
  AND ac.academic_year_id = 1
  AND ac.sequence <= 2;

-- ============================================================
-- 8. ATTENDANCE (last 10 school days — Grade 10A & 8A)
-- ============================================================
-- Generate attendance for the past 10 weekdays
INSERT IGNORE INTO attendance (student_id,class_id,academic_year_id,date,status,recorded_by)
SELECT s.id, s.current_class_id, 1,
  DATE_SUB(CURDATE(), INTERVAL n.n DAY),
  ELT(1 + FLOOR(RAND()*10),
    'Present','Present','Present','Present','Present','Present','Present',
    'Absent','Late','Excused'),
  (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr' LIMIT 1)
FROM students s
CROSS JOIN (
  SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
  UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
) n
WHERE s.current_class_id IN (@cls10a, @cls8a)
  AND DAYOFWEEK(DATE_SUB(CURDATE(), INTERVAL n.n DAY)) BETWEEN 2 AND 6
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ============================================================
-- 9. REPORT CARDS (Grade 10A — generated & published)
-- ============================================================
INSERT IGNORE INTO report_cards
  (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT
  s.id, @cls10a, 1,
  8 + FLOOR(RAND()*2),   -- 8–9 days present
  1 + FLOOR(RAND()*2),   -- 1–2 absent
  FLOOR(RAND()*2),        -- 0–1 tardy
  ROUND(80 + RAND()*18,1),
  ROUND(62 + RAND()*30,1), -- average 62–92
  ELT(1+FLOOR(RAND()*5),'Excellent','Very Good','Good','Good','Satisfactory'),
  ELT(1+FLOOR(RAND()*4),
    'A hardworking and dedicated student.',
    'Shows great improvement this term.',
    'Performs well with consistent effort.',
    'Good attitude toward learning.'),
  'Keep up the excellent work and continue striving for the best.',
  'Promoted',
  'published',
  NOW(),
  NOW(),
  (SELECT id FROM users WHERE email='vp@karnhighschool.edu.lr' LIMIT 1)
FROM students s
WHERE s.current_class_id = @cls10a
ON DUPLICATE KEY UPDATE status='published';

-- ============================================================
-- 10. PAYMENTS (varied amounts, methods, dates)
-- ============================================================
INSERT IGNORE INTO payments (receipt_number,student_id,fee_structure_id,amount,currency,payment_method,payment_date,academic_year_id,notes,recorded_by)
SELECT
  CONCAT('REC-2026-', LPAD(ROW_NUMBER() OVER (ORDER BY s.id, fs.id), 5,'0')),
  s.id,
  fs.id,
  fs.amount,
  fs.currency,
  ELT(1+FLOOR(RAND()*4),'Cash','Mobile money','Bank transfer','Cash'),
  DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND()*60) DAY),
  1,
  NULL,
  (SELECT id FROM users WHERE email='accountant@karnhighschool.edu.lr' LIMIT 1)
FROM students s
CROSS JOIN fee_structures fs
WHERE fs.academic_year_id=1 AND fs.is_mandatory=1
  AND s.status='Active'
  AND s.id <= (SELECT MIN(id)+14 FROM students WHERE status='Active');

-- ============================================================
-- 11. DISCIPLINE RECORDS
-- ============================================================
INSERT IGNORE INTO discipline_records (student_id,incident_date,category,description,action_taken,details,resolved,recorded_by) VALUES
((SELECT id FROM students WHERE student_id='KHS-2026-0013' LIMIT 1),
 DATE_SUB(CURDATE(),INTERVAL 15 DAY),'Misconduct','Student was disruptive during Mathematics class.','Warning','Teacher warned student verbally and logged the incident.',0,
 (SELECT id FROM users WHERE email='discipline@karnhighschool.edu.lr' LIMIT 1)),

((SELECT id FROM students WHERE student_id='KHS-2026-0017' LIMIT 1),
 DATE_SUB(CURDATE(),INTERVAL 8 DAY),'Absence','Student was absent 3 consecutive days without excuse.','Parent Meeting','Parent was contacted and attended a meeting with the class teacher.',1,
 (SELECT id FROM users WHERE email='discipline@karnhighschool.edu.lr' LIMIT 1)),

((SELECT id FROM students WHERE student_id='KHS-2026-0025' LIMIT 1),
 DATE_SUB(CURDATE(),INTERVAL 5 DAY),'Cheating','Student was found with notes during a class test.','Suspension','Student was suspended for 2 days. Parents notified.',0,
 (SELECT id FROM users WHERE email='discipline@karnhighschool.edu.lr' LIMIT 1)),

((SELECT id FROM students WHERE student_id='KHS-2026-0020' LIMIT 1),
 DATE_SUB(CURDATE(),INTERVAL 20 DAY),'Misconduct','Student used abusive language toward a classmate.','Counseling','Student attended 2 counseling sessions.',1,
 (SELECT id FROM users WHERE email='discipline@karnhighschool.edu.lr' LIMIT 1)),

((SELECT id FROM students WHERE student_id='KHS-2026-0030' LIMIT 1),
 DATE_SUB(CURDATE(),INTERVAL 3 DAY),'Vandalism','Student damaged a school bench.','Community Service','Student assigned to clean the school compound for 3 days.',0,
 (SELECT id FROM users WHERE email='discipline@karnhighschool.edu.lr' LIMIT 1));

-- ============================================================
-- 12. LIBRARY TRANSACTIONS
-- ============================================================
INSERT IGNORE INTO library_transactions (book_id,student_id,issued_by,due_date,returned_at,status,fine_amount)
SELECT
  lb.id,
  s.id,
  (SELECT id FROM users WHERE email='librarian@karnhighschool.edu.lr' LIMIT 1),
  DATE_ADD(CURDATE(), INTERVAL 7 DAY),
  NULL,
  'Issued',
  0.00
FROM library_books lb, students s
WHERE lb.title LIKE 'English Grammar%'
  AND s.student_id='KHS-2026-0020'
LIMIT 1;

INSERT IGNORE INTO library_transactions (book_id,student_id,issued_by,due_date,returned_at,status,fine_amount)
SELECT
  lb.id,
  s.id,
  (SELECT id FROM users WHERE email='librarian@karnhighschool.edu.lr' LIMIT 1),
  DATE_SUB(CURDATE(), INTERVAL 5 DAY),  -- overdue
  NULL,
  'Issued',
  0.00
FROM library_books lb, students s
WHERE lb.title LIKE 'Oxford Mathematics%'
  AND s.student_id='KHS-2026-0022'
LIMIT 1;

INSERT IGNORE INTO library_transactions (book_id,student_id,issued_by,issued_at,due_date,returned_at,status,fine_amount)
SELECT
  lb.id,
  s.id,
  (SELECT id FROM users WHERE email='librarian@karnhighschool.edu.lr' LIMIT 1),
  DATE_SUB(CURDATE(),INTERVAL 20 DAY),
  DATE_SUB(CURDATE(),INTERVAL 6 DAY),
  DATE_SUB(CURDATE(),INTERVAL 8 DAY),
  'Returned',
  0.00
FROM library_books lb, students s
WHERE lb.title LIKE 'Liberia%'
  AND s.student_id='KHS-2026-0030'
LIMIT 1;

-- ============================================================
-- 13. MORE ANNOUNCEMENTS
-- ============================================================
INSERT IGNORE INTO announcements (title,message,target,is_public,created_by,published_at,expires_at) VALUES
('Mid-Term Examination Schedule',
 'Mid-term examinations for Semester 1 will be held from October 6–10, 2026. All students are required to be present. Timetables will be distributed by class teachers.',
 'students',1,(SELECT id FROM users WHERE email='principal@karnhighschool.edu.lr' LIMIT 1),
 DATE_SUB(NOW(),INTERVAL 5 DAY), DATE_ADD(NOW(),INTERVAL 30 DAY)),

('PTA Meeting — October 2026',
 'The Parent-Teacher Association meeting for the first semester will be held on Saturday, October 18, 2026 at 10:00 AM in the school hall. All parents and guardians are encouraged to attend.',
 'parents',1,(SELECT id FROM users WHERE email='principal@karnhighschool.edu.lr' LIMIT 1),
 DATE_SUB(NOW(),INTERVAL 3 DAY), DATE_ADD(NOW(),INTERVAL 20 DAY)),

('Teacher Professional Development Day',
 'There will be a school closure on Friday, September 26, 2026 for a teacher professional development workshop. No classes will be held on this day.',
 'teachers',0,(SELECT id FROM users WHERE email='vp@karnhighschool.edu.lr' LIMIT 1),
 DATE_SUB(NOW(),INTERVAL 10 DAY), NULL),

('Library Hours Extended',
 'Starting September 15, 2026, the school library will be open until 5:00 PM on weekdays to support student research and study. Students are welcome to borrow up to 2 books at a time.',
 'all',1,(SELECT id FROM users WHERE email='librarian@karnhighschool.edu.lr' LIMIT 1),
 DATE_SUB(NOW(),INTERVAL 14 DAY), NULL),

('Fee Payment Reminder — Semester 1',
 'This is a reminder that Semester 1 school fees are due by October 31, 2026. Parents who have not yet paid are urged to visit the bursar''s office. Outstanding fees may affect student examinations.',
 'parents',0,(SELECT id FROM users WHERE email='accountant@karnhighschool.edu.lr' LIMIT 1),
 DATE_SUB(NOW(),INTERVAL 2 DAY), DATE_ADD(NOW(),INTERVAL 45 DAY));

-- ============================================================
-- 14. MORE EVENTS
-- ============================================================
INSERT IGNORE INTO events (title,description,event_date,start_time,end_time,venue,category,is_public,created_by) VALUES
('Mid-Term Examination Week',
 'Semester 1 mid-term examinations for all grades. Students should arrive 30 minutes early.',
 DATE_ADD(CURDATE(),INTERVAL 14 DAY),'07:30:00','15:30:00','All Classrooms','academic',1,
 (SELECT id FROM users WHERE email='principal@karnhighschool.edu.lr' LIMIT 1)),

('Parent-Teacher Association Meeting',
 'Quarterly PTA meeting open to all parents and guardians.',
 DATE_ADD(CURDATE(),INTERVAL 21 DAY),'10:00:00','13:00:00','School Hall','community',1,
 (SELECT id FROM users WHERE email='principal@karnhighschool.edu.lr' LIMIT 1)),

('Annual Sports Day',
 'KHS Annual Inter-House Sports Day. Students compete in athletics, football, and other sports. Families welcome.',
 DATE_ADD(CURDATE(),INTERVAL 35 DAY),'08:00:00','17:00:00','School Sports Ground','sports',1,
 (SELECT id FROM users WHERE email='schooladmin@karnhighschool.edu.lr' LIMIT 1)),

('Semester 1 Final Examinations',
 'End-of-semester examinations for all grades. Detailed timetable to follow.',
 DATE_ADD(CURDATE(),INTERVAL 56 DAY),'07:30:00','15:30:00','All Classrooms','academic',1,
 (SELECT id FROM users WHERE email='principal@karnhighschool.edu.lr' LIMIT 1)),

('Prize Giving Day',
 'Annual prize giving ceremony recognizing academic excellence, leadership, and community service.',
 DATE_ADD(CURDATE(),INTERVAL 90 DAY),'09:00:00','14:00:00','School Hall','cultural',1,
 (SELECT id FROM users WHERE email='schooladmin@karnhighschool.edu.lr' LIMIT 1));

-- ============================================================
-- 15. ADDITIONAL APPLICATIONS (pending → full workflow demo)
-- ============================================================
INSERT IGNORE INTO applications (application_number,first_name,middle_name,last_name,date_of_birth,gender,nationality,phone,current_address,county,grade_applying_for,grade_id,academic_year_id,academic_year,guardian_name,guardian_relationship,guardian_phone,status,document_status,entrance_status) VALUES
('KHS-2026-000200','Abraham',  'K.','Kollie',  '2011-04-12','Male',  'Liberian','+231 881 200 001','Karnplay, Nimba','Nimba','Grade 9', (SELECT id FROM grades WHERE name='Grade 9'  LIMIT 1),1,'2026/2027','Sarah Kollie',   'Mother', '+231 881 200 002','Application Submitted','Pending','Not scheduled'),
('KHS-2026-000201','Blessing', '', 'Freeman',  '2009-08-25','Female','Liberian','+231 881 200 003','Ganta, Nimba',   'Nimba','Grade 11',(SELECT id FROM grades WHERE name='Grade 11' LIMIT 1),1,'2026/2027','James Freeman',  'Father', '+231 881 200 004','Under Review',         'Pending','Not scheduled'),
('KHS-2026-000202','George',   'A.','Sumo',    '2013-01-17','Male',  'Liberian','+231 881 200 005','Karnplay, Nimba','Nimba','Grade 7', (SELECT id FROM grades WHERE name='Grade 7'  LIMIT 1),1,'2026/2027','Agnes Sumo',     'Mother', '+231 881 200 006','Documents needed',     'Pending','Not scheduled'),
('KHS-2026-000203','Mariama',  '', 'Konneh',   '2010-11-30','Female','Liberian','+231 881 200 007','Sanniquellie',  'Nimba','Grade 10',(SELECT id FROM grades WHERE name='Grade 10' LIMIT 1),1,'2026/2027','Thomas Konneh',  'Father', '+231 881 200 008','Approved for entrance', 'Verified','Not scheduled');

UPDATE applications SET entrance_letter_ref='KEL-2026-00203' WHERE application_number='KHS-2026-000203';

-- More realistic applications
INSERT IGNORE INTO applications (application_number,first_name,last_name,date_of_birth,gender,nationality,phone,current_address,county,grade_applying_for,grade_id,academic_year_id,academic_year,guardian_name,guardian_relationship,guardian_phone,status,entrance_letter_ref,entrance_exam_date) VALUES
('KHS-2026-000204','Emmanuel','Harris',  '2012-06-09','Male',  'Liberian','+231 881 200 009','Karnplay','Nimba','Grade 8', (SELECT id FROM grades WHERE name='Grade 8'  LIMIT 1),1,'2026/2027','Paul Harris',    'Father','+231 881 200 010','Entrance scheduled','KEL-2026-00204',DATE_ADD(CURDATE(),INTERVAL 7 DAY)),
('KHS-2026-000205','Patience','Williams','2008-03-14','Female','Liberian','+231 881 200 011','Ganta, Nimba','Nimba','Grade 12',(SELECT id FROM grades WHERE name='Grade 12' LIMIT 1),1,'2026/2027','Mary Williams',  'Mother','+231 881 200 012','Admitted',NULL,NULL),
('KHS-2026-000206','Daniel',  'Flomo',   '2011-09-20','Male',  'Liberian','+231 881 200 013','Karnplay','Nimba','Grade 9', (SELECT id FROM grades WHERE name='Grade 9'  LIMIT 1),1,'2026/2027','Agnes Flomo',   'Mother','+231 881 200 014','Rejected',NULL,NULL);

-- ============================================================
-- 16. GRADING SCALE (ensure exists)
-- ============================================================
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT 1,'A', 90,100, 4.00,'Excellent',   1 WHERE NOT EXISTS (SELECT 1 FROM grading_scales WHERE academic_year_id=1 AND grade_letter='A');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT 1,'B', 80,89.99,3.00,'Very Good',  1 WHERE NOT EXISTS (SELECT 1 FROM grading_scales WHERE academic_year_id=1 AND grade_letter='B');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT 1,'C', 70,79.99,2.00,'Good',       1 WHERE NOT EXISTS (SELECT 1 FROM grading_scales WHERE academic_year_id=1 AND grade_letter='C');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT 1,'D', 60,69.99,1.00,'Satisfactory',1 WHERE NOT EXISTS (SELECT 1 FROM grading_scales WHERE academic_year_id=1 AND grade_letter='D');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT 1,'F', 0, 59.99,0.00,'Fail',       0 WHERE NOT EXISTS (SELECT 1 FROM grading_scales WHERE academic_year_id=1 AND grade_letter='F');

-- ============================================================
-- 17. APPROVAL REQUESTS (for demo of approval workflow)
-- ============================================================
INSERT IGNORE INTO approval_requests (module,record_type,record_id,requested_by,status,priority,title,description) VALUES
('marks','assessment_batch',1,
 (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr' LIMIT 1),
 'pending','normal','Marks Submitted: Grade 8A — Mathematics (1st Period)',
 'Teacher has submitted marks for Grade 8A Mathematics. Please review and approve.'),

('admissions','application',
 (SELECT id FROM applications WHERE application_number='KHS-2026-000201' LIMIT 1),
 (SELECT id FROM users WHERE email='registrar@karnhighschool.edu.lr' LIMIT 1),
 'pending','high','Admission Recommendation: Blessing Freeman — Grade 11',
 'Application reviewed and documents verified. Recommending for entrance examination.'),

('discipline','discipline_record',
 (SELECT id FROM discipline_records WHERE action_taken='Suspension' LIMIT 1),
 (SELECT id FROM users WHERE email='discipline@karnhighschool.edu.lr' LIMIT 1),
 'pending','high','Suspension Approval Required: Grade 11 Student',
 'Student found cheating during class test. Recommending 2-day suspension. Requires principal approval.');

-- ============================================================
-- 18. AUDIT LOGS (sample activity)
-- ============================================================
INSERT IGNORE INTO audit_logs (user_name,action,module,record_type,record_id,old_value,new_value,ip_address,created_at) VALUES
('Mary Kollie',    'create',        'admissions','application',1, NULL,'Application Submitted','192.168.1.10', DATE_SUB(NOW(),INTERVAL 5 DAY)),
('Mary Kollie',    'update_status', 'admissions','application',2, 'Application Submitted','Under Review','192.168.1.10', DATE_SUB(NOW(),INTERVAL 4 DAY)),
('Sarah Williams', 'submit_marks',  'marks','assessment_score',1, 'draft','submitted','192.168.1.11', DATE_SUB(NOW(),INTERVAL 2 DAY)),
('Grace Flomo',    'approve_marks', 'marks','assessment_score',1, 'submitted','approved','192.168.1.12', DATE_SUB(NOW(),INTERVAL 1 DAY)),
('Moses Johnson',  'create',        'finance','payment',1, NULL,'LRD 15000','192.168.1.13', DATE_SUB(NOW(),INTERVAL 3 DAY)),
('John Cooper',    'login',         'auth','user',3, NULL,'','192.168.1.14', DATE_SUB(NOW(),INTERVAL 6 HOUR)),
('System Admin',   'update',        'system','settings',1, 'hero_headline','Updated headline','192.168.1.1',DATE_SUB(NOW(),INTERVAL 7 DAY));

-- ============================================================
-- 19. FAQ (if empty)
-- ============================================================
INSERT IGNORE INTO faq (question,answer,category,sort_order,is_active) VALUES
('What are the school hours?',            'School hours are Monday to Friday, 8:00 AM to 3:30 PM. Office hours for parents and visitors are 8:00 AM to 4:00 PM.','general',1,1),
('How do I apply for admission?',         'Visit our website and click "Apply for Admission". Complete the multi-step online form. You will receive a unique application number immediately upon submission.','admissions',2,1),
('What documents are required for admission?','Required documents include: previous school report card, birth certificate or age verification document, and one passport-sized photograph. These can be submitted online or in person.','admissions',3,1),
('Is there an entrance examination?',     'Yes. After your application is reviewed and documents verified, you will receive an Entrance Eligibility Letter inviting you to take the entrance examination.','admissions',4,1),
('What subjects does KHS offer?',         'KHS offers a comprehensive curriculum including English Language, Mathematics, Biology, Chemistry, Physics, History, Geography, Economics, Computer Science, French, and more.','academics',5,1),
('How are report cards distributed?',     'Report cards are published on the student and parent portals at the end of each semester. Physical copies can be collected from the school office.','academics',6,1),
('How do I pay school fees?',             'Fees can be paid in cash, via mobile money, or bank transfer. Visit the Bursar office during school hours. Your child''s student ID is required for all payments.','finance',7,1),
('What is the school''s fee structure?',  'Fee structures vary by grade level. Please log in to the parent or student portal to view the specific fee schedule for your child''s grade.','finance',8,1),
('How can parents monitor their child''s progress?','Parents can log in to the parent portal using their registered email or phone number. The portal shows attendance, marks, report cards, and announcements.','parents',9,1),
('Who should I contact if I have a concern?','For academic matters, contact the Vice Principal. For admissions, contact the Registrar. For fees, contact the Bursar. For general enquiries, call +231 886 417 711.','general',10,1);

SET FOREIGN_KEY_CHECKS = 1;

-- ── Verification summary ──────────────────────────────────────
SELECT 'students'              t, COUNT(*) n FROM students
UNION ALL SELECT 'teachers',             COUNT(*) FROM teachers
UNION ALL SELECT 'classes',              COUNT(*) FROM classes
UNION ALL SELECT 'teacher_assignments',  COUNT(*) FROM teacher_assignments
UNION ALL SELECT 'timetable_slots',      COUNT(*) FROM timetable
UNION ALL SELECT 'applications',         COUNT(*) FROM applications
UNION ALL SELECT 'assessment_scores',    COUNT(*) FROM assessment_scores
UNION ALL SELECT 'attendance_records',   COUNT(*) FROM attendance
UNION ALL SELECT 'payments',             COUNT(*) FROM payments
UNION ALL SELECT 'report_cards',         COUNT(*) FROM report_cards
UNION ALL SELECT 'discipline_records',   COUNT(*) FROM discipline_records
UNION ALL SELECT 'library_transactions', COUNT(*) FROM library_transactions
UNION ALL SELECT 'announcements',        COUNT(*) FROM announcements
UNION ALL SELECT 'events',               COUNT(*) FROM events
UNION ALL SELECT 'approval_requests',    COUNT(*) FROM approval_requests
UNION ALL SELECT 'audit_logs',           COUNT(*) FROM audit_logs
UNION ALL SELECT 'faq',                  COUNT(*) FROM faq;
