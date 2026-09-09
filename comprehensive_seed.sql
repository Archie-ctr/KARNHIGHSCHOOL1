-- ============================================================
-- KHSMIS COMPREHENSIVE SEED DATA
-- Academic Years: 2023/2024 → 2026/2027 (current)
-- All passwords: 1234
-- Hash: $2y$10$lpPLPh39oWD5Geze1FXGtO6WjILnivbXvhczHwjrNvek/VLfM2upC
-- Run: mysql -u root karnhighschool < comprehensive_seed.sql
-- ============================================================
USE karnhighschool;
SET FOREIGN_KEY_CHECKS = 0;
SET @pw = '$2y$10$lpPLPh39oWD5Geze1FXGtO6WjILnivbXvhczHwjrNvek/VLfM2upC';

-- ============================================================
-- 1. ENSURE ALL ROLES EXIST
-- ============================================================
INSERT IGNORE INTO roles (name,label) VALUES
('super_admin',       'Super Administrator'),
('sys_admin',         'System Administrator'),
('school_admin',      'School Administrator'),
('principal',         'Principal'),
('vice_principal',    'Vice Principal'),
('registrar',         'Registrar'),
('academic_dean',     'Academic Dean'),
('accountant',        'Accountant / Bursar'),
('teacher',           'Teacher'),
('class_teacher',     'Class Teacher'),
('discipline_officer','Discipline Officer'),
('librarian',         'Librarian'),
('ict_officer',       'ICT Officer'),
('parent',            'Parent / Guardian'),
('student',           'Student'),
('applicant',         'Applicant');

-- ============================================================
-- 2. ACADEMIC YEARS (2023-2026 closed, 2026/2027 current)
-- ============================================================
INSERT IGNORE INTO academic_years (name,start_date,end_date,is_current,status) VALUES
('2023/2024','2023-08-21','2024-06-28',0,'closed'),
('2024/2025','2024-08-19','2025-06-27',0,'closed'),
('2025/2026','2025-08-18','2026-06-26',0,'closed'),
('2026/2027','2026-08-18','2027-06-26',1,'active');

-- Year ID helpers
SET @y23 = (SELECT id FROM academic_years WHERE name='2023/2024');
SET @y24 = (SELECT id FROM academic_years WHERE name='2024/2025');
SET @y25 = (SELECT id FROM academic_years WHERE name='2025/2026');
SET @y26 = (SELECT id FROM academic_years WHERE name='2026/2027');

-- ============================================================
-- 3. SEMESTERS & PERIODS for all years
-- ============================================================
-- 2023/2024
INSERT IGNORE INTO semesters (academic_year_id,name,sequence,start_date,end_date,is_current) VALUES
(@y23,'Semester 1',1,'2023-08-21','2023-12-15',0),
(@y23,'Semester 2',2,'2024-01-08','2024-06-28',0);
INSERT IGNORE INTO semesters (academic_year_id,name,sequence,start_date,end_date,is_current) VALUES
(@y24,'Semester 1',1,'2024-08-19','2024-12-13',0),
(@y24,'Semester 2',2,'2025-01-06','2025-06-27',0);
INSERT IGNORE INTO semesters (academic_year_id,name,sequence,start_date,end_date,is_current) VALUES
(@y25,'Semester 1',1,'2025-08-18','2025-12-12',0),
(@y25,'Semester 2',2,'2026-01-05','2026-06-26',0);
-- 2026/2027 already seeded in db_setup.sql

-- Periods for 2023/2024
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'1st Period',1,'period' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'2nd Period',2,'period' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'3rd Period',3,'period' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'Semester 1 Examination',4,'exam' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'4th Period',1,'period' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'5th Period',2,'period' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'6th Period',3,'period' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'Semester 2 Examination',4,'exam' FROM semesters s WHERE s.academic_year_id=@y23 AND s.sequence=2;
-- Periods for 2024/2025
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'1st Period',1,'period' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'2nd Period',2,'period' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'3rd Period',3,'period' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'Semester 1 Examination',4,'exam' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'4th Period',1,'period' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'5th Period',2,'period' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'6th Period',3,'period' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'Semester 2 Examination',4,'exam' FROM semesters s WHERE s.academic_year_id=@y24 AND s.sequence=2;
-- Periods for 2025/2026
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'1st Period',1,'period' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'2nd Period',2,'period' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'3rd Period',3,'period' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'Semester 1 Examination',4,'exam' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=1;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'4th Period',1,'period' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'5th Period',2,'period' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'6th Period',3,'period' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=2;
INSERT IGNORE INTO periods (semester_id,name,sequence,type) SELECT s.id,'Semester 2 Examination',4,'exam' FROM semesters s WHERE s.academic_year_id=@y25 AND s.sequence=2;

-- Assessment configs for all past years
INSERT IGNORE INTO assessment_configs (academic_year_id,name,type,sequence,max_marks,weight_percent,period_id)
SELECT ay.id, p.name, p.type,
  p.sequence + (CASE WHEN sem.sequence=2 THEN 4 ELSE 0 END),
  100.00,
  CASE WHEN p.type='exam' THEN 40.00 ELSE 20.00 END,
  p.id
FROM periods p
JOIN semesters sem ON sem.id=p.semester_id
JOIN academic_years ay ON ay.id=sem.academic_year_id
WHERE ay.name IN ('2023/2024','2024/2025','2025/2026');

-- Grading scales for all years
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT ay.id,'A', 90,100, 4.00,'Excellent',   1 FROM academic_years ay WHERE ay.name IN ('2023/2024','2024/2025','2025/2026');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT ay.id,'B', 80,89.99,3.00,'Very Good',  1 FROM academic_years ay WHERE ay.name IN ('2023/2024','2024/2025','2025/2026');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT ay.id,'C', 70,79.99,2.00,'Good',       1 FROM academic_years ay WHERE ay.name IN ('2023/2024','2024/2025','2025/2026');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT ay.id,'D', 60,69.99,1.00,'Satisfactory',1 FROM academic_years ay WHERE ay.name IN ('2023/2024','2024/2025','2025/2026');
INSERT IGNORE INTO grading_scales (academic_year_id,grade_letter,min_percent,max_percent,grade_point,description,is_pass)
SELECT ay.id,'F', 0, 59.99,0.00,'Fail',       0 FROM academic_years ay WHERE ay.name IN ('2023/2024','2024/2025','2025/2026');

-- ============================================================
-- 4. FULL STAFF USERS (all password: 1234)
-- ============================================================
INSERT IGNORE INTO users (name,email,phone,password_hash,role_id) VALUES
-- System / Admin
('System Administrator', 'sysadmin@karnhighschool.edu.lr',    '+231 886 000 001',@pw,(SELECT id FROM roles WHERE name='sys_admin')),
('School Administrator', 'schooladmin@karnhighschool.edu.lr', '+231 886 000 002',@pw,(SELECT id FROM roles WHERE name='school_admin')),
-- Principals
('Mr. John T. Cooper',   'principal@karnhighschool.edu.lr',   '+231 886 000 003',@pw,(SELECT id FROM roles WHERE name='principal')),
('Mrs. Alice Konneh',    'vprincipal@karnhighschool.edu.lr',  '+231 886 000 004',@pw,(SELECT id FROM roles WHERE name='vice_principal')),
-- Admin staff
('Miss Mary E. Kollie',  'registrar@karnhighschool.edu.lr',   '+231 886 000 005',@pw,(SELECT id FROM roles WHERE name='registrar')),
('Mr. Moses M. Johnson', 'accountant@karnhighschool.edu.lr',  '+231 886 000 006',@pw,(SELECT id FROM roles WHERE name='accountant')),
('Mr. David K. Flomo',   'librarian@karnhighschool.edu.lr',   '+231 886 000 007',@pw,(SELECT id FROM roles WHERE name='librarian')),
('Miss Agnes T. Wea',    'discipline@karnhighschool.edu.lr',  '+231 886 000 008',@pw,(SELECT id FROM roles WHERE name='discipline_officer')),
('Mr. Emmanuel R. Doe',  'ict@karnhighschool.edu.lr',         '+231 886 000 009',@pw,(SELECT id FROM roles WHERE name='ict_officer')),
-- Teachers
('Mrs. Sarah A. Williams','teacher@karnhighschool.edu.lr',    '+231 886 000 010',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Mr. Robert C. Brown',   'rbrown@karnhighschool.edu.lr',     '+231 886 000 011',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Miss Grace P. Flomo',   'gflomo@karnhighschool.edu.lr',     '+231 886 000 012',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Mr. Emmanuel J. Konneh','ekonneh@karnhighschool.edu.lr',    '+231 886 000 013',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Miss Agnes K. Harris',  'aharris@karnhighschool.edu.lr',    '+231 886 000 014',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Mr. Joseph M. Freeman', 'jfreeman@karnhighschool.edu.lr',   '+231 886 000 015',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Mrs. Patricia L. Sumo', 'psumo@karnhighschool.edu.lr',      '+231 886 000 016',@pw,(SELECT id FROM roles WHERE name='class_teacher')),
('Mr. Daniel T. Cooper',  'dcooper@karnhighschool.edu.lr',    '+231 886 000 017',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Miss Comfort J. Nimley','cnimley@karnhighschool.edu.lr',    '+231 886 000 018',@pw,(SELECT id FROM roles WHERE name='teacher')),
('Mr. Philip A. Kamara',  'pkamara@karnhighschool.edu.lr',    '+231 886 000 019',@pw,(SELECT id FROM roles WHERE name='teacher'));

-- Fix existing users' password hashes (update to 1234)
UPDATE users SET password_hash=@pw WHERE email IN (
  'admin@karnhighschool.edu.lr','student@karnhighschool.edu.lr','parent@karnhighschool.edu.lr'
);

-- ============================================================
-- 5. TEACHERS TABLE (link users to teachers)
-- ============================================================
-- Clear old mismatched teachers and rebuild
INSERT IGNORE INTO teachers (teacher_id,first_name,last_name,gender,phone,email,qualification,specialization,employment_date,status)
SELECT 'TCH-001','Sarah','Williams','Female','+231 886 000 010','teacher@karnhighschool.edu.lr','B.Ed. Mathematics','Mathematics','2019-09-02','Active'
WHERE NOT EXISTS (SELECT 1 FROM teachers WHERE teacher_id='TCH-001');
INSERT IGNORE INTO teachers (teacher_id,first_name,last_name,gender,phone,email,qualification,specialization,employment_date,status) VALUES
('TCH-002','Robert',   'Brown',   'Male',  '+231 886 000 011','rbrown@karnhighschool.edu.lr',   'B.Ed. English','English Language','2018-09-03','Active'),
('TCH-003','Grace',    'Flomo',   'Female','+231 886 000 012','gflomo@karnhighschool.edu.lr',   'B.Sc. Biology','Sciences',        '2020-09-01','Active'),
('TCH-004','Emmanuel', 'Konneh',  'Male',  '+231 886 000 013','ekonneh@karnhighschool.edu.lr',  'M.A. History', 'Social Studies',  '2017-09-04','Active'),
('TCH-005','Agnes',    'Harris',  'Female','+231 886 000 014','aharris@karnhighschool.edu.lr',  'B.Sc. Chemistry','Sciences',      '2021-09-01','Active'),
('TCH-006','Joseph',   'Freeman', 'Male',  '+231 886 000 015','jfreeman@karnhighschool.edu.lr', 'B.A. Economics','Social Studies', '2019-09-05','Active'),
('TCH-007','Patricia', 'Sumo',    'Female','+231 886 000 016','psumo@karnhighschool.edu.lr',    'B.Ed. English','English Grammar', '2022-09-01','Active'),
('TCH-008','Daniel',   'Cooper',  'Male',  '+231 886 000 017','dcooper@karnhighschool.edu.lr',  'B.Sc. Physics','Sciences',       '2020-09-06','Active'),
('TCH-009','Comfort',  'Nimley',  'Female','+231 886 000 018','cnimley@karnhighschool.edu.lr',  'B.Ed. Geography','Geography',    '2023-09-01','Active'),
('TCH-010','Philip',   'Kamara',  'Male',  '+231 886 000 019','pkamara@karnhighschool.edu.lr',  'B.Sc. Computer Science','ICT', '2023-09-01','Active');

-- Link teachers to users
UPDATE teachers t JOIN users u ON u.email=t.email SET t.user_id=u.id WHERE t.user_id IS NULL;

-- ============================================================
-- 6. STAFF TABLE (non-teacher admin staff)
-- ============================================================
CREATE TABLE IF NOT EXISTS staff (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NULL,
  first_name      VARCHAR(80)  NOT NULL,
  last_name       VARCHAR(80)  NOT NULL,
  gender          VARCHAR(20)  DEFAULT NULL,
  phone           VARCHAR(30)  DEFAULT NULL,
  email           VARCHAR(120) DEFAULT NULL,
  department      VARCHAR(80)  DEFAULT NULL,
  role            VARCHAR(60)  DEFAULT NULL,
  employee_id     VARCHAR(30)  DEFAULT NULL,
  employment_date DATE         DEFAULT NULL,
  status          VARCHAR(20)  NOT NULL DEFAULT 'Active',
  photo           VARCHAR(255) DEFAULT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO staff (first_name,last_name,gender,phone,email,department,role,employment_date,status,user_id)
SELECT 'Mary','Kollie','Female','+231 886 000 005','registrar@karnhighschool.edu.lr','Registrar','Registrar','2015-09-01','Active',u.id
FROM users u WHERE u.email='registrar@karnhighschool.edu.lr' LIMIT 1;
INSERT IGNORE INTO staff (first_name,last_name,gender,phone,email,department,role,employment_date,status,user_id)
SELECT 'Moses','Johnson','Male','+231 886 000 006','accountant@karnhighschool.edu.lr','Finance','Accountant','2016-09-01','Active',u.id
FROM users u WHERE u.email='accountant@karnhighschool.edu.lr' LIMIT 1;
INSERT IGNORE INTO staff (first_name,last_name,gender,phone,email,department,role,employment_date,status,user_id)
SELECT 'David','Flomo','Male','+231 886 000 007','librarian@karnhighschool.edu.lr','Library','Librarian','2018-09-01','Active',u.id
FROM users u WHERE u.email='librarian@karnhighschool.edu.lr' LIMIT 1;
INSERT IGNORE INTO staff (first_name,last_name,gender,phone,email,department,role,employment_date,status,user_id)
SELECT 'Agnes','Wea','Female','+231 886 000 008','discipline@karnhighschool.edu.lr','Student Affairs','Discipline Officer','2019-09-01','Active',u.id
FROM users u WHERE u.email='discipline@karnhighschool.edu.lr' LIMIT 1;
INSERT IGNORE INTO staff (first_name,last_name,gender,phone,email,department,role,employment_date,status,user_id)
SELECT 'Emmanuel','Doe','Male','+231 886 000 009','ict@karnhighschool.edu.lr','ICT','ICT Officer','2021-09-01','Active',u.id
FROM users u WHERE u.email='ict@karnhighschool.edu.lr' LIMIT 1;
INSERT IGNORE INTO staff (first_name,last_name,gender,phone,email,department,role,employment_date,status,user_id)
SELECT 'John','Cooper','Male','+231 886 000 003','principal@karnhighschool.edu.lr','Administration','Principal','2010-09-01','Active',u.id
FROM users u WHERE u.email='principal@karnhighschool.edu.lr' LIMIT 1;
INSERT IGNORE INTO staff (first_name,last_name,gender,phone,email,department,role,employment_date,status,user_id)
SELECT 'Alice','Konneh','Female','+231 886 000 004','vprincipal@karnhighschool.edu.lr','Administration','Vice Principal','2012-09-01','Active',u.id
FROM users u WHERE u.email='vprincipal@karnhighschool.edu.lr' LIMIT 1;

-- ============================================================
-- 7. CLASSES for all 4 years (6 grades × 2 sections for senior)
-- ============================================================
-- 2023/2024
INSERT IGNORE INTO classes (grade_id,academic_year_id,name,section)
SELECT g.id,@y23,CONCAT(g.name,'A'),'A' FROM grades g WHERE g.name IN ('Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12');
INSERT IGNORE INTO classes (grade_id,academic_year_id,name,section)
SELECT g.id,@y23,CONCAT(g.name,'B'),'B' FROM grades g WHERE g.name IN ('Grade 10','Grade 11','Grade 12');
-- 2024/2025
INSERT IGNORE INTO classes (grade_id,academic_year_id,name,section)
SELECT g.id,@y24,CONCAT(g.name,'A'),'A' FROM grades g WHERE g.name IN ('Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12');
INSERT IGNORE INTO classes (grade_id,academic_year_id,name,section)
SELECT g.id,@y24,CONCAT(g.name,'B'),'B' FROM grades g WHERE g.name IN ('Grade 10','Grade 11','Grade 12');
-- 2025/2026
INSERT IGNORE INTO classes (grade_id,academic_year_id,name,section)
SELECT g.id,@y25,CONCAT(g.name,'A'),'A' FROM grades g WHERE g.name IN ('Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12');
INSERT IGNORE INTO classes (grade_id,academic_year_id,name,section)
SELECT g.id,@y25,CONCAT(g.name,'B'),'B' FROM grades g WHERE g.name IN ('Grade 10','Grade 11','Grade 12');

-- ============================================================
-- 8. STUDENTS (80 students — realistic Liberian names)
-- ============================================================
SET @g7  = (SELECT id FROM grades WHERE name='Grade 7');
SET @g8  = (SELECT id FROM grades WHERE name='Grade 8');
SET @g9  = (SELECT id FROM grades WHERE name='Grade 9');
SET @g10 = (SELECT id FROM grades WHERE name='Grade 10');
SET @g11 = (SELECT id FROM grades WHERE name='Grade 11');
SET @g12 = (SELECT id FROM grades WHERE name='Grade 12');

-- Class IDs for 2026/2027
SET @c7a  = (SELECT c.id FROM classes c WHERE c.grade_id=@g7  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c8a  = (SELECT c.id FROM classes c WHERE c.grade_id=@g8  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c9a  = (SELECT c.id FROM classes c WHERE c.grade_id=@g9  AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c10a = (SELECT c.id FROM classes c WHERE c.grade_id=@g10 AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c10b = (SELECT c.id FROM classes c WHERE c.grade_id=@g10 AND c.academic_year_id=@y26 AND c.section='B' LIMIT 1);
SET @c11a = (SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c11b = (SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y26 AND c.section='B' LIMIT 1);
SET @c12a = (SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
SET @c12b = (SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y26 AND c.section='B' LIMIT 1);

-- Grade 7A (10 students)
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1001','ADM-1001','Fatu',     'M.', 'Kollie',   'Female','2014-03-12',NULL,           @g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1002','ADM-1002','James',    'K.', 'Nimley',   'Male',  '2014-07-22','+231 880 1002',@g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1003','ADM-1003','Mary',     '',   'Flomo',    'Female','2014-01-05',NULL,           @g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1004','ADM-1004','George',   'A.', 'Sumo',     'Male',  '2013-11-17','+231 880 1004',@g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1005','ADM-1005','Blessing', '',   'Freeman',  'Female','2014-05-09',NULL,           @g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1006','ADM-1006','Abraham',  'K.', 'Kamara',   'Male',  '2014-02-28','+231 880 1006',@g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1007','ADM-1007','Patience', '',   'Konneh',   'Female','2013-09-14',NULL,           @g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1008','ADM-1008','Moses',    'J.', 'Harris',   'Male',  '2014-06-03','+231 880 1008',@g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1009','ADM-1009','Hawa',     '',   'Toe',      'Female','2014-04-21',NULL,           @g7,@c7a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1010','ADM-1010','Peter',    'A.', 'Cooper',   'Male',  '2013-12-08','+231 880 1010',@g7,@c7a,@y26,'Active','2026-08-18','Nimba');

-- Grade 8A (10 students — update existing + add new)
UPDATE students SET current_class_id=@c8a, current_grade_id=@g8, academic_year_id=@y26 WHERE student_id='KHS-2024-0184';
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1011','ADM-1011','Samuel',   'T.', 'Wea',      'Male',  '2013-05-17','+231 880 1011',@g8,@c8a,@y26,'Active','2024-08-19','Nimba'),
('KHS-2026-1012','ADM-1012','Rebecca',  '',   'Johnson',  'Female','2013-09-30',NULL,           @g8,@c8a,@y26,'Active','2024-08-19','Nimba'),
('KHS-2026-1013','ADM-1013','Thomas',   'E.', 'Kollie',   'Male',  '2013-11-08','+231 880 1013',@g8,@c8a,@y26,'Active','2024-08-19','Nimba'),
('KHS-2026-1014','ADM-1014','Comfort',  '',   'Flomo',    'Female','2013-07-25',NULL,           @g8,@c8a,@y26,'Active','2025-08-18','Nimba'),
('KHS-2026-1015','ADM-1015','Daniel',   'A.', 'Sumo',     'Male',  '2013-02-14','+231 880 1015',@g8,@c8a,@y26,'Active','2025-08-18','Nimba'),
('KHS-2026-1016','ADM-1016','Mariama',  '',   'Freeman',  'Female','2013-08-19',NULL,           @g8,@c8a,@y26,'Active','2025-08-18','Nimba'),
('KHS-2026-1017','ADM-1017','Anthony',  'K.', 'Harris',   'Male',  '2013-04-06','+231 880 1017',@g8,@c8a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1018','ADM-1018','Vivian',   '',   'Nimley',   'Female','2013-10-23',NULL,           @g8,@c8a,@y26,'Active','2026-08-18','Nimba'),
('KHS-2026-1019','ADM-1019','Michael',  'M.', 'Konneh',   'Male',  '2014-01-31','+231 880 1019',@g8,@c8a,@y26,'Active','2026-08-18','Nimba');

-- Grade 9A (8 students)
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1021','ADM-1021','Grace',    '',   'Williams', 'Female','2012-06-14','+231 880 1021',@g9,@c9a,@y26,'Active','2023-08-21','Nimba'),
('KHS-2026-1022','ADM-1022','David',    'P.', 'Kamara',   'Male',  '2012-02-27',NULL,           @g9,@c9a,@y26,'Active','2023-08-21','Nimba'),
('KHS-2026-1023','ADM-1023','Hawa',     'J.', 'Cooper',   'Female','2012-10-03','+231 880 1023',@g9,@c9a,@y26,'Active','2023-08-21','Nimba'),
('KHS-2026-1024','ADM-1024','Peter',    'A.', 'Wea',      'Male',  '2012-04-19',NULL,           @g9,@c9a,@y26,'Active','2023-08-21','Nimba'),
('KHS-2026-1025','ADM-1025','Ruth',     '',   'Johnson',  'Female','2012-08-11','+231 880 1025',@g9,@c9a,@y26,'Active','2023-08-21','Nimba'),
('KHS-2026-1026','ADM-1026','Paul',     'J.', 'Nimley',   'Male',  '2012-12-25',NULL,           @g9,@c9a,@y26,'Active','2023-08-21','Nimba'),
('KHS-2026-1027','ADM-1027','Agnes',    '',   'Toe',      'Female','2012-03-07','+231 880 1027',@g9,@c9a,@y26,'Active','2024-08-19','Nimba'),
('KHS-2026-1028','ADM-1028','Emmanuel', 'T.', 'Flomo',    'Male',  '2012-07-14',NULL,           @g9,@c9a,@y26,'Active','2024-08-19','Nimba');

-- Grade 10A (8 students)
UPDATE students SET current_class_id=@c10a, current_grade_id=@g10, academic_year_id=@y26 WHERE student_id='KHS-2024-0183';
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1031','ADM-1031','Victoria', '',   'Harris',   'Female','2011-08-11','+231 880 1031',@g10,@c10a,@y26,'Active','2022-08-22','Nimba'),
('KHS-2026-1032','ADM-1032','Philip',   'A.', 'Kollie',   'Male',  '2011-01-30',NULL,           @g10,@c10a,@y26,'Active','2022-08-22','Nimba'),
('KHS-2026-1033','ADM-1033','Esther',   '',   'Freeman',  'Female','2011-05-22','+231 880 1033',@g10,@c10a,@y26,'Active','2022-08-22','Nimba'),
('KHS-2026-1034','ADM-1034','Joseph',   'M.', 'Konneh',   'Male',  '2011-09-08',NULL,           @g10,@c10a,@y26,'Active','2022-08-22','Nimba'),
('KHS-2026-1035','ADM-1035','Priscilla','K.', 'Sumo',     'Female','2011-11-16','+231 880 1035',@g10,@c10a,@y26,'Active','2023-08-21','Nimba');
-- Grade 10B
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1041','ADM-1041','Abraham',  'L.', 'Williams', 'Male',  '2011-03-18','+231 880 1041',@g10,@c10b,@y26,'Active','2022-08-22','Nimba'),
('KHS-2026-1042','ADM-1042','Blessing', '',   'Cooper',   'Female','2011-06-29',NULL,           @g10,@c10b,@y26,'Active','2022-08-22','Nimba'),
('KHS-2026-1043','ADM-1043','George',   'P.', 'Kamara',   'Male',  '2011-10-14','+231 880 1043',@g10,@c10b,@y26,'Active','2023-08-21','Nimba'),
('KHS-2026-1044','ADM-1044','Mariama',  '',   'Wea',      'Female','2011-04-05',NULL,           @g10,@c10b,@y26,'Active','2023-08-21','Nimba');

-- Grade 11A (6 students)
UPDATE students SET current_class_id=@c11a, current_grade_id=@g11, academic_year_id=@y26 WHERE student_id='KHS-2024-0181';
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1051','ADM-1051','Daniel',   'E.', 'Nimley',   'Male',  '2010-02-11','+231 880 1051',@g11,@c11a,@y26,'Active','2021-08-23','Nimba'),
('KHS-2026-1052','ADM-1052','Patricia', 'M.', 'Flomo',    'Female','2010-06-27',NULL,           @g11,@c11a,@y26,'Active','2021-08-23','Nimba'),
('KHS-2026-1053','ADM-1053','Anthony',  'K.', 'Johnson',  'Male',  '2010-10-14','+231 880 1053',@g11,@c11a,@y26,'Active','2021-08-23','Nimba'),
('KHS-2026-1054','ADM-1054','Grace',    '',   'Harris',   'Female','2010-03-30',NULL,           @g11,@c11a,@y26,'Active','2022-08-22','Nimba');
-- Grade 11B
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1061','ADM-1061','Michael',  'A.', 'Kollie',   'Male',  '2010-07-23','+231 880 1061',@g11,@c11b,@y26,'Active','2021-08-23','Nimba'),
('KHS-2026-1062','ADM-1062','Sarah',    'J.', 'Kamara',   'Female','2010-11-09',NULL,           @g11,@c11b,@y26,'Active','2021-08-23','Nimba'),
('KHS-2026-1063','ADM-1063','James',    'T.', 'Freeman',  'Male',  '2010-01-25','+231 880 1063',@g11,@c11b,@y26,'Active','2022-08-22','Nimba');

-- Grade 12A (6 students — these are graduating)
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1071','ADM-1071','Emmanuel', 'K.', 'Sumo',     'Male',  '2009-04-12','+231 880 1071',@g12,@c12a,@y26,'Active','2020-09-07','Nimba'),
('KHS-2026-1072','ADM-1072','Fatu',     'A.', 'Williams', 'Female','2009-08-25',NULL,           @g12,@c12a,@y26,'Active','2020-09-07','Nimba'),
('KHS-2026-1073','ADM-1073','Joseph',   'L.', 'Cooper',   'Male',  '2009-12-18','+231 880 1073',@g12,@c12a,@y26,'Active','2020-09-07','Nimba'),
('KHS-2026-1074','ADM-1074','Blessing', '',   'Harris',   'Female','2009-05-30',NULL,           @g12,@c12a,@y26,'Active','2020-09-07','Nimba'),
('KHS-2026-1075','ADM-1075','Daniel',   'M.', 'Nimley',   'Male',  '2009-02-14','+231 880 1075',@g12,@c12a,@y26,'Active','2021-08-23','Nimba');
-- Grade 12B
INSERT IGNORE INTO students (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,phone,current_grade_id,current_class_id,academic_year_id,status,admission_date,county) VALUES
('KHS-2026-1081','ADM-1081','Victoria', 'E.', 'Kollie',   'Female','2009-07-08','+231 880 1081',@g12,@c12b,@y26,'Active','2020-09-07','Nimba'),
('KHS-2026-1082','ADM-1082','Philip',   'K.', 'Freeman',  'Male',  '2009-10-21',NULL,           @g12,@c12b,@y26,'Active','2020-09-07','Nimba'),
('KHS-2026-1083','ADM-1083','Agnes',    '',   'Flomo',    'Female','2009-03-16','+231 880 1083',@g12,@c12b,@y26,'Active','2021-08-23','Nimba');

-- ============================================================
-- 9. CREATE STUDENT PORTAL USERS (link user accounts to students)
-- ============================================================
-- Create portal users for all new students (password: 1234)
INSERT IGNORE INTO users (name,email,phone,password_hash,role_id)
SELECT CONCAT(s.first_name,' ',s.last_name), LOWER(CONCAT(s.student_id,'@student.karnhighschool.edu.lr')), s.phone, @pw, (SELECT id FROM roles WHERE name='student')
FROM students s WHERE s.student_id LIKE 'KHS-2026-1%';

-- Link user accounts to student records
UPDATE students s
JOIN users u ON u.email=LOWER(CONCAT(s.student_id,'@student.karnhighschool.edu.lr'))
SET s.user_id=u.id WHERE s.user_id IS NULL AND s.student_id LIKE 'KHS-2026-1%';

-- ============================================================
-- 10. GUARDIANS & PARENT USERS
-- ============================================================
INSERT IGNORE INTO guardians (first_name,last_name,relationship,phone,email) VALUES
('Mary',       'Kollie',   'Mother',   '+231 880 3001','mkollie@gmail.com'),
('John',       'Nimley',   'Father',   '+231 880 3002',NULL),
('Agnes',      'Freeman',  'Mother',   '+231 880 3003',NULL),
('Peter',      'Kamara',   'Father',   '+231 880 3004','pkamara@gmail.com'),
('Ruth',       'Sumo',     'Mother',   '+231 880 3005',NULL),
('James',      'Cooper',   'Father',   '+231 880 3006',NULL),
('Comfort',    'Harris',   'Mother',   '+231 880 3007','charris@gmail.com'),
('Thomas',     'Wea',      'Father',   '+231 880 3008',NULL),
('Martha',     'Johnson',  'Mother',   '+231 880 3009',NULL),
('David',      'Williams', 'Father',   '+231 880 3010','dwilliams@gmail.com'),
('Sarah',      'Flomo',    'Mother',   '+231 880 3011',NULL),
('Emmanuel',   'Konneh',   'Father',   '+231 880 3012',NULL),
('Rebecca',    'Freeman',  'Mother',   '+231 880 3013','rfreeman@gmail.com'),
('Moses',      'Nimley',   'Father',   '+231 880 3014',NULL),
('Grace',      'Harris',   'Mother',   '+231 880 3015',NULL);

-- Create parent portal users
INSERT IGNORE INTO users (name,email,phone,password_hash,role_id) VALUES
('Mary Kollie',    '+231880300001@parent.khs.lr','+231 880 3001',@pw,(SELECT id FROM roles WHERE name='parent')),
('Peter Kamara',   '+231880300004@parent.khs.lr','+231 880 3004',@pw,(SELECT id FROM roles WHERE name='parent')),
('Comfort Harris', '+231880300007@parent.khs.lr','+231 880 3007',@pw,(SELECT id FROM roles WHERE name='parent')),
('David Williams', '+231880300010@parent.khs.lr','+231 880 3010',@pw,(SELECT id FROM roles WHERE name='parent')),
('Rebecca Freeman','+231880300013@parent.khs.lr','+231 880 3013',@pw,(SELECT id FROM roles WHERE name='parent'));

-- Link parent users to guardian records
UPDATE guardians SET user_id=(SELECT id FROM users WHERE phone='+231 880 3001' AND role_id=(SELECT id FROM roles WHERE name='parent') LIMIT 1) WHERE phone='+231 880 3001';
UPDATE guardians SET user_id=(SELECT id FROM users WHERE phone='+231 880 3004' AND role_id=(SELECT id FROM roles WHERE name='parent') LIMIT 1) WHERE phone='+231 880 3004';
UPDATE guardians SET user_id=(SELECT id FROM users WHERE phone='+231 880 3007' AND role_id=(SELECT id FROM roles WHERE name='parent') LIMIT 1) WHERE phone='+231 880 3007';

-- Link guardians to students
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id,g.id,1 FROM students s, guardians g WHERE s.student_id='KHS-2026-1001' AND g.first_name='Mary' AND g.last_name='Kollie';
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id,g.id,1 FROM students s, guardians g WHERE s.student_id='KHS-2026-1002' AND g.first_name='John' AND g.last_name='Nimley';
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id,g.id,1 FROM students s, guardians g WHERE s.student_id='KHS-2026-1011' AND g.first_name='Agnes' AND g.last_name='Freeman';
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id,g.id,1 FROM students s, guardians g WHERE s.student_id='KHS-2026-1021' AND g.first_name='David' AND g.last_name='Williams';
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id,g.id,1 FROM students s, guardians g WHERE s.student_id='KHS-2026-1031' AND g.first_name='Comfort' AND g.last_name='Harris';
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id,g.id,1 FROM students s, guardians g WHERE s.student_id='KHS-2026-1071' AND g.first_name='Martha' AND g.last_name='Johnson';
INSERT IGNORE INTO student_guardians (student_id,guardian_id,is_primary)
SELECT s.id,g.id,1 FROM students s, guardians g WHERE s.student_id='KHS-2026-1072' AND g.first_name='Sarah' AND g.last_name='Flomo';

-- ============================================================
-- 11. TEACHER ASSIGNMENTS (2026/2027)
-- ============================================================
-- Sarah Williams (TCH-001) → Mathematics
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-001' AND s.code='MAT' AND g.name IN ('Grade 7','Grade 8','Grade 9','Grade 10') AND c.academic_year_id=@y26 AND c.section='A';

-- Robert Brown (TCH-002) → English Language
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-002' AND s.code='ENG' AND g.name IN ('Grade 10','Grade 11','Grade 12') AND c.academic_year_id=@y26 AND c.section='A';

-- Grace Flomo (TCH-003) → Biology
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-003' AND s.code='BIO' AND g.name IN ('Grade 10','Grade 11','Grade 12') AND c.academic_year_id=@y26;

-- Emmanuel Konneh (TCH-004) → History
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-004' AND s.code='HIS' AND g.name IN ('Grade 9','Grade 10','Grade 11') AND c.academic_year_id=@y26 AND c.section='A';

-- Agnes Harris (TCH-005) → Chemistry
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-005' AND s.code='CHM' AND g.name IN ('Grade 10','Grade 11','Grade 12') AND c.academic_year_id=@y26;

-- Joseph Freeman (TCH-006) → Economics
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-006' AND s.code='ECO' AND g.name IN ('Grade 10','Grade 11','Grade 12') AND c.academic_year_id=@y26 AND c.section='A';

-- Patricia Sumo (TCH-007) → English Grammar
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-007' AND s.code='EGR' AND g.name IN ('Grade 7','Grade 8','Grade 9') AND c.academic_year_id=@y26 AND c.section='A';

-- Daniel Cooper (TCH-008) → Physics
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-008' AND s.code='PHY' AND g.name IN ('Grade 11','Grade 12') AND c.academic_year_id=@y26;

-- Comfort Nimley (TCH-009) → Geography
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-009' AND s.code='GEO' AND g.name IN ('Grade 8','Grade 9','Grade 10') AND c.academic_year_id=@y26 AND c.section='A';

-- Philip Kamara (TCH-010) → Computer Science
INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id)
SELECT t.id,s.id,c.id,@y26 FROM teachers t, subjects s, classes c JOIN grades g ON g.id=c.grade_id
WHERE t.teacher_id='TCH-010' AND s.code='CSC' AND g.name IN ('Grade 10','Grade 11','Grade 12') AND c.academic_year_id=@y26;

-- ============================================================
-- 12. TIMETABLE (Grade 10A — full week)
-- ============================================================
SET @t1  = (SELECT id FROM teachers WHERE teacher_id='TCH-001');
SET @t2  = (SELECT id FROM teachers WHERE teacher_id='TCH-002');
SET @t3  = (SELECT id FROM teachers WHERE teacher_id='TCH-003');
SET @t4  = (SELECT id FROM teachers WHERE teacher_id='TCH-004');
SET @t5  = (SELECT id FROM teachers WHERE teacher_id='TCH-005');
SET @t6  = (SELECT id FROM teachers WHERE teacher_id='TCH-006');
SET @t8  = (SELECT id FROM teachers WHERE teacher_id='TCH-008');
SET @t10 = (SELECT id FROM teachers WHERE teacher_id='TCH-010');
SET @sMAT=(SELECT id FROM subjects WHERE code='MAT'); SET @sENG=(SELECT id FROM subjects WHERE code='ENG');
SET @sBIO=(SELECT id FROM subjects WHERE code='BIO'); SET @sCHM=(SELECT id FROM subjects WHERE code='CHM');
SET @sHIS=(SELECT id FROM subjects WHERE code='HIS'); SET @sECO=(SELECT id FROM subjects WHERE code='ECO');
SET @sPHY=(SELECT id FROM subjects WHERE code='PHY'); SET @sCSC=(SELECT id FROM subjects WHERE code='CSC');
SET @sPHE=(SELECT id FROM subjects WHERE code='PHE'); SET @sGEO=(SELECT id FROM subjects WHERE code='GEO');

INSERT IGNORE INTO timetable (class_id,subject_id,teacher_id,day_of_week,period_slot,start_time,end_time,academic_year_id) VALUES
-- MON
(@c10a,@sMAT,@t1, 1,1,'08:00','08:45',@y26), (@c10a,@sENG,@t2, 1,2,'08:45','09:30',@y26),
(@c10a,@sBIO,@t3, 1,3,'09:45','10:30',@y26), (@c10a,@sCHM,@t5, 1,4,'10:30','11:15',@y26),
(@c10a,@sHIS,@t4, 1,5,'11:30','12:15',@y26), (@c10a,@sPHY,@t8, 1,6,'12:15','13:00',@y26),
(@c10a,@sPHE,NULL,1,7,'14:00','14:45',@y26), (@c10a,@sECO,@t6, 1,8,'14:45','15:30',@y26),
-- TUE
(@c10a,@sBIO,@t3, 2,1,'08:00','08:45',@y26), (@c10a,@sMAT,@t1, 2,2,'08:45','09:30',@y26),
(@c10a,@sCHM,@t5, 2,3,'09:45','10:30',@y26), (@c10a,@sENG,@t2, 2,4,'10:30','11:15',@y26),
(@c10a,@sPHY,@t8, 2,5,'11:30','12:15',@y26), (@c10a,@sCSC,@t10,2,6,'12:15','13:00',@y26),
-- WED
(@c10a,@sMAT,@t1, 3,1,'08:00','08:45',@y26), (@c10a,@sCHM,@t5, 3,2,'08:45','09:30',@y26),
(@c10a,@sENG,@t2, 3,3,'09:45','10:30',@y26), (@c10a,@sBIO,@t3, 3,4,'10:30','11:15',@y26),
(@c10a,@sPHE,NULL,3,5,'11:30','12:15',@y26), (@c10a,@sHIS,@t4, 3,6,'12:15','13:00',@y26),
-- THU
(@c10a,@sHIS,@t4, 4,1,'08:00','08:45',@y26), (@c10a,@sPHY,@t8, 4,2,'08:45','09:30',@y26),
(@c10a,@sMAT,@t1, 4,3,'09:45','10:30',@y26), (@c10a,@sECO,@t6, 4,4,'10:30','11:15',@y26),
(@c10a,@sENG,@t2, 4,5,'11:30','12:15',@y26), (@c10a,@sCHM,@t5, 4,6,'12:15','13:00',@y26),
-- FRI
(@c10a,@sBIO,@t3, 5,1,'08:00','08:45',@y26), (@c10a,@sMAT,@t1, 5,2,'08:45','09:30',@y26),
(@c10a,@sPHE,NULL,5,3,'09:45','10:30',@y26), (@c10a,@sHIS,@t4, 5,4,'10:30','11:15',@y26),
(@c10a,@sPHY,@t8, 5,5,'11:30','12:15',@y26), (@c10a,@sENG,@t2, 5,6,'12:15','13:00',@y26);

-- Also add 11A timetable
SET @c11a2=(SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y26 AND c.section='A' LIMIT 1);
INSERT IGNORE INTO timetable (class_id,subject_id,teacher_id,day_of_week,period_slot,start_time,end_time,academic_year_id) VALUES
(@c11a2,@sENG,@t2, 1,1,'08:00','08:45',@y26),(@c11a2,@sMAT,@t1, 1,2,'08:45','09:30',@y26),
(@c11a2,@sBIO,@t3, 1,3,'09:45','10:30',@y26),(@c11a2,@sPHY,@t8, 1,4,'10:30','11:15',@y26),
(@c11a2,@sCHM,@t5, 1,5,'11:30','12:15',@y26),(@c11a2,@sECO,@t6, 1,6,'12:15','13:00',@y26),
(@c11a2,@sENG,@t2, 2,1,'08:00','08:45',@y26),(@c11a2,@sBIO,@t3, 2,2,'08:45','09:30',@y26),
(@c11a2,@sMAT,@t1, 2,3,'09:45','10:30',@y26),(@c11a2,@sHIS,@t4, 2,4,'10:30','11:15',@y26),
(@c11a2,@sCHM,@t5, 2,5,'11:30','12:15',@y26),(@c11a2,@sPHY,@t8, 2,6,'12:15','13:00',@y26);

-- ============================================================
-- 13. ASSESSMENT SCORES (Grade 10A, 11A — current year, approved)
-- ============================================================
SET @enteredBy = (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr' LIMIT 1);
SET @approvedBy= (SELECT id FROM users WHERE email='vprincipal@karnhighschool.edu.lr' LIMIT 1);

-- Grade 10A — 5 subjects, all 8 periods/exams
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,@c10a,sub.id,ac.id,@y26,
  ROUND(55+(RAND()*40),1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL FLOOR(5+RAND()*20) DAY),
  DATE_SUB(NOW(),INTERVAL FLOOR(1+RAND()*4)  DAY),
  @approvedBy,'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_class_id=@c10a
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id=@y26;

-- Grade 11A — 5 subjects
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,@c11a,sub.id,ac.id,@y26,
  ROUND(60+(RAND()*38),1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL FLOOR(5+RAND()*18) DAY),
  DATE_SUB(NOW(),INTERVAL FLOOR(1+RAND()*3) DAY),
  @approvedBy,'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_class_id=@c11a
  AND sub.code IN ('ENG','MAT','BIO','CHM','PHY')
  AND ac.academic_year_id=@y26;

-- Grade 12A — 5 subjects (higher marks, seniors)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,@c12a,sub.id,ac.id,@y26,
  ROUND(65+(RAND()*33),1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL FLOOR(3+RAND()*14) DAY),
  DATE_SUB(NOW(),INTERVAL FLOOR(1+RAND()*2) DAY),
  @approvedBy,'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_class_id=@c12a
  AND sub.code IN ('ENG','MAT','BIO','CHM','PHY')
  AND ac.academic_year_id=@y26;

-- Grade 8A — draft/submitted (teacher has entered, awaiting approval)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,status)
SELECT s.id,@c8a,sub.id,ac.id,@y26,
  ROUND(45+(RAND()*52),1), ac.max_marks,
  @enteredBy, DATE_SUB(NOW(),INTERVAL 2 DAY),
  'submitted'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_class_id=@c8a
  AND sub.code IN ('MAT','EGR')
  AND ac.academic_year_id=@y26
  AND ac.sequence<=4;

-- ============================================================
-- 14. HISTORICAL SCORES (2025/2026 — completed year)
-- ============================================================
SET @c10a_25=(SELECT c.id FROM classes c WHERE c.grade_id=@g10 AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c11a_25=(SELECT c.id FROM classes c WHERE c.grade_id=@g11 AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);
SET @c12a_25=(SELECT c.id FROM classes c WHERE c.grade_id=@g12 AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1);

-- Seed historical scores for students who were in these grades in 2025/2026
-- Grade 10A students in 2025/2026 were the current Grade 11A students
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,COALESCE(@c10a_25,@c10a),sub.id,ac.id,@y25,
  ROUND(58+(RAND()*37),1),ac.max_marks,
  @enteredBy,DATE_SUB(NOW(),INTERVAL 280 DAY),DATE_SUB(NOW(),INTERVAL 275 DAY),@approvedBy,'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.student_id IN ('KHS-2026-1051','KHS-2026-1052','KHS-2026-1053','KHS-2026-1054','KHS-2024-0181')
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id=@y25;

-- ============================================================
-- 15. ATTENDANCE (last 20 school days for active classes)
-- ============================================================
INSERT IGNORE INTO attendance (student_id,class_id,academic_year_id,date,status,recorded_by)
SELECT s.id, s.current_class_id, @y26,
  DATE_SUB(CURDATE(), INTERVAL n.n DAY),
  ELT(1+FLOOR(RAND()*10),'Present','Present','Present','Present','Present','Present','Present','Absent','Late','Excused'),
  @enteredBy
FROM students s
CROSS JOIN (
  SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
  UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
  UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18
  UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25
) n
WHERE s.status='Active'
  AND s.current_class_id IN (@c7a,@c8a,@c9a,@c10a,@c11a,@c12a)
  AND DAYOFWEEK(DATE_SUB(CURDATE(), INTERVAL n.n DAY)) BETWEEN 2 AND 6
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ============================================================
-- 16. REPORT CARDS (Grade 10A & 11A — published; Grade 12A — published as graduated)
-- ============================================================
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, s.current_class_id, @y26,
  17+FLOOR(RAND()*3), 1+FLOOR(RAND()*2), FLOOR(RAND()*2),
  ROUND(82+RAND()*16,1), ROUND(65+RAND()*28,1),
  ELT(1+FLOOR(RAND()*5),'Excellent','Very Good','Good','Good','Satisfactory'),
  ELT(1+FLOOR(RAND()*4),'A hardworking and dedicated student.','Shows great improvement this term.','Performs well with consistent effort.','Good attitude toward learning.'),
  'Keep up the excellent work. We are proud of your progress this year.',
  'Promoted', 'published', NOW(), NOW(), @approvedBy
FROM students s
WHERE s.current_class_id IN (@c10a,@c11a)
ON DUPLICATE KEY UPDATE status='published';

-- Grade 12 — Graduated
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id, s.current_class_id, @y26,
  18+FLOOR(RAND()*2), FLOOR(RAND()*2), 0,
  ROUND(88+RAND()*11,1), ROUND(72+RAND()*22,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'An outstanding student. Best wishes for the future.','A dedicated and focused student. Congratulations on your graduation.','It has been a pleasure having you at KHS. Best wishes ahead.'),
  'Congratulations on your successful completion of Grade 12. Wishing you all the best!',
  'Graduated', 'published', NOW(), NOW(), @approvedBy
FROM students s
WHERE s.current_class_id IN (@c12a,@c12b)
ON DUPLICATE KEY UPDATE status='published';

-- ============================================================
-- 17. GRADUATION RECORDS (Grade 12)
-- ============================================================
INSERT IGNORE INTO graduation_records (student_id,academic_year_id,graduation_date,ceremony_date,status,honours)
SELECT s.id,@y26,'2027-06-26','2027-06-26','Graduated',
  CASE WHEN (SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=s.id AND academic_year_id=@y26 AND status='approved')>=90 THEN 'Summa Cum Laude'
       WHEN (SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=s.id AND academic_year_id=@y26 AND status='approved')>=85 THEN 'Magna Cum Laude'
       WHEN (SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=s.id AND academic_year_id=@y26 AND status='approved')>=80 THEN 'Cum Laude'
       ELSE NULL END
FROM students s
WHERE s.current_class_id IN (@c12a,@c12b);

-- ============================================================
-- 18. FEE STRUCTURES (all 4 years)
-- ============================================================
INSERT IGNORE INTO fee_structures (academic_year_id,fee_type,amount,currency,is_mandatory) VALUES
(@y23,'Tuition',     12000.00,'LRD',1),(@y23,'Registration', 4000.00,'LRD',1),
(@y23,'Examination', 2500.00,'LRD',1),(@y23,'Development',  1500.00,'LRD',1),
(@y23,'Computer',    1000.00,'LRD',0),(@y23,'Library',       300.00,'LRD',0);
INSERT IGNORE INTO fee_structures (academic_year_id,fee_type,amount,currency,is_mandatory) VALUES
(@y24,'Tuition',     13000.00,'LRD',1),(@y24,'Registration', 4500.00,'LRD',1),
(@y24,'Examination', 2700.00,'LRD',1),(@y24,'Development',  1700.00,'LRD',1),
(@y24,'Computer',    1200.00,'LRD',0),(@y24,'Library',       400.00,'LRD',0);
INSERT IGNORE INTO fee_structures (academic_year_id,fee_type,amount,currency,is_mandatory) VALUES
(@y25,'Tuition',     14000.00,'LRD',1),(@y25,'Registration', 4800.00,'LRD',1),
(@y25,'Examination', 2900.00,'LRD',1),(@y25,'Development',  1900.00,'LRD',1),
(@y25,'Computer',    1400.00,'LRD',0),(@y25,'Library',       450.00,'LRD',0);

-- ============================================================
-- 19. PAYMENTS (realistic across all years)
-- ============================================================
SET @accUser=(SELECT id FROM users WHERE email='accountant@karnhighschool.edu.lr' LIMIT 1);

-- Current year payments for active students
INSERT IGNORE INTO payments (receipt_number,student_id,fee_structure_id,amount,currency,payment_method,payment_date,academic_year_id,recorded_by)
SELECT CONCAT('REC-26-',LPAD(ROW_NUMBER() OVER (ORDER BY s.id,fs.id),5,'0')),
  s.id,fs.id,fs.amount,fs.currency,
  ELT(1+FLOOR(RAND()*3),'Cash','Mobile money','Bank transfer'),
  DATE_SUB(CURDATE(),INTERVAL FLOOR(RAND()*60) DAY),
  @y26,@accUser
FROM students s
CROSS JOIN fee_structures fs
WHERE fs.academic_year_id=@y26 AND fs.is_mandatory=1
  AND s.status='Active' AND s.current_class_id IN (@c10a,@c11a,@c12a);

-- Past year payments (2025/2026)
INSERT IGNORE INTO payments (receipt_number,student_id,fee_structure_id,amount,currency,payment_method,payment_date,academic_year_id,recorded_by)
SELECT CONCAT('REC-25-',LPAD(ROW_NUMBER() OVER (ORDER BY s.id,fs.id),5,'0')),
  s.id,fs.id,fs.amount,fs.currency,
  ELT(1+FLOOR(RAND()*3),'Cash','Mobile money','Bank transfer'),
  DATE_SUB(CURDATE(),INTERVAL 300+FLOOR(RAND()*60) DAY),
  @y25,@accUser
FROM students s
CROSS JOIN fee_structures fs
WHERE fs.academic_year_id=@y25 AND fs.is_mandatory=1
  AND s.status='Active' AND s.student_id IN ('KHS-2026-1051','KHS-2026-1052','KHS-2026-1053','KHS-2026-1054','KHS-2024-0181','KHS-2026-1071','KHS-2026-1072');

-- ============================================================
-- 20. DISCIPLINE RECORDS (with new extended columns)
-- ============================================================
SET @discUser=(SELECT id FROM users WHERE email='discipline@karnhighschool.edu.lr' LIMIT 1);
INSERT IGNORE INTO discipline_records
  (student_id,incident_date,date_occurred,category,violation_type,description,action_taken,severity,status,parent_notified,academic_year_id,recorded_by,reported_by,created_at)
VALUES
((SELECT id FROM students WHERE student_id='KHS-2026-1011'),DATE_SUB(CURDATE(),INTERVAL 15 DAY),'2026-08-25','Misconduct','Misconduct','Student was disruptive during Mathematics class.','Verbal Warning','minor','resolved',1,@y26,@discUser,@discUser,DATE_SUB(NOW(),INTERVAL 15 DAY)),
((SELECT id FROM students WHERE student_id='KHS-2026-1022'),DATE_SUB(CURDATE(),INTERVAL 10 DAY),'2026-08-30','Absence','Truancy','Student was absent 3 days without excuse.','Parent Meeting','moderate','investigating',1,@y26,@discUser,@discUser,DATE_SUB(NOW(),INTERVAL 10 DAY)),
((SELECT id FROM students WHERE student_id='KHS-2026-1052'),DATE_SUB(CURDATE(),INTERVAL 7 DAY), '2026-09-02','Cheating','Cheating','Student found with notes during class test.','Suspension','serious','open',0,@y26,@discUser,@discUser,DATE_SUB(NOW(),INTERVAL 7 DAY)),
((SELECT id FROM students WHERE student_id='KHS-2026-1032'),DATE_SUB(CURDATE(),INTERVAL 20 DAY),'2026-08-20','Misconduct','Disrespect','Student used disrespectful language toward teacher.','Counselling Referral','moderate','resolved',1,@y26,@discUser,@discUser,DATE_SUB(NOW(),INTERVAL 20 DAY)),
((SELECT id FROM students WHERE student_id='KHS-2026-1071'),DATE_SUB(CURDATE(),INTERVAL 5 DAY), '2026-09-04','Vandalism','Vandalism','Student damaged a school bench.','Community Service','moderate','open',1,@y26,@discUser,@discUser,DATE_SUB(NOW(),INTERVAL 5 DAY)),
((SELECT id FROM students WHERE student_id='KHS-2026-1025'),DATE_SUB(CURDATE(),INTERVAL 12 DAY),'2026-08-28','Misconduct','Bullying','Student bullied a junior student.','Written Warning','serious','pending_decision',1,@y26,@discUser,@discUser,DATE_SUB(NOW(),INTERVAL 12 DAY)),
((SELECT id FROM students WHERE student_id='KHS-2026-1061'),DATE_SUB(CURDATE(),INTERVAL 3 DAY), '2026-09-06','Misconduct','Uniform Violation','Student repeatedly came to school without proper uniform.','Warning','minor','open',0,@y26,@discUser,@discUser,DATE_SUB(NOW(),INTERVAL 3 DAY));

-- ============================================================
-- 21. LIBRARY BOOKS (more realistic catalogue)
-- ============================================================
INSERT IGNORE INTO library_books (isbn,title,author,category,publisher,year,total_copies,available,location,is_active) VALUES
('978-0-06-112008-4','English Grammar in Use',        'Raymond Murphy',    'English',      'Cambridge',  2019,5,4,'Section A, Shelf 1',1),
('978-0-19-953492-3','Oxford Mathematics D1',          'Various',           'Mathematics',  'Oxford',     2020,4,3,'Section B, Shelf 2',1),
('978-0-00-000003-0','Biology for Senior High School', 'James T. Freeman',  'Science',      'Liberian Ed',2021,6,5,'Section C, Shelf 1',1),
('978-0-00-000004-0','Chemistry Today Gr 10-12',       'Agnes K. Williams', 'Science',      'Liberian Ed',2022,4,4,'Section C, Shelf 2',1),
('978-0-00-000005-0','Liberia: Its History & People',  'Moses A. Cooper',   'History',      'KHS Press',  2020,3,3,'Section D, Shelf 1',1),
('978-0-00-000006-0','Economics Principles Sr High',   'Ruth E. Kamara',    'Economics',    'Nimba Press',2021,3,2,'Section D, Shelf 2',1),
('978-0-00-000007-0','Computer Science Fundamentals',  'Emmanuel J. Sumo',  'Computing',    'KHS Press',  2023,5,5,'Section E, Shelf 1',1),
('978-0-00-000008-0','Physics for Liberian Schools',   'Daniel T. Harris',  'Science',      'KHS Press',  2022,4,3,'Section C, Shelf 3',1),
('978-0-00-000009-0','Geography of West Africa',       'Comfort N. Flomo',  'Geography',    'Liberian Ed',2020,3,3,'Section D, Shelf 3',1),
('978-0-00-000010-0','English Literature Anthology',   'Patricia L. Cole',  'Literature',   'Oxford',     2021,4,4,'Section A, Shelf 2',1),
('978-0-00-000011-0','Mathematics for Grade 7-9',      'Sarah A. Williams', 'Mathematics',  'KHS Press',  2023,5,5,'Section B, Shelf 1',1),
('978-0-00-000012-0','Religious & Moral Education',    'Various',           'Religion',     'Liberian Ed',2019,3,3,'Section F, Shelf 1',1);

-- Library transactions (using actual library_transactions columns)
-- Add missing columns first if needed
ALTER TABLE library_transactions
  ADD COLUMN IF NOT EXISTS borrow_date DATE NULL,
  ADD COLUMN IF NOT EXISTS returned_date DATETIME NULL,
  ADD COLUMN IF NOT EXISTS fine_paid TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS fine_waived TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS book_condition VARCHAR(30) NULL,
  ADD COLUMN IF NOT EXISTS returned_by INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS renewed_count TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS member_type VARCHAR(20) NOT NULL DEFAULT 'student';

-- Back-fill borrow_date from issued_at
UPDATE library_transactions SET borrow_date=DATE(issued_at) WHERE borrow_date IS NULL;

SET @libUser=(SELECT id FROM users WHERE email='librarian@karnhighschool.edu.lr' LIMIT 1);
INSERT IGNORE INTO library_transactions (book_id,student_id,issued_by,issued_at,borrow_date,due_date,returned_at,returned_date,status,fine_amount,fine_paid,academic_year_id)
SELECT lb.id,s.id,@libUser,
  DATE_SUB(NOW(),INTERVAL 7 DAY),DATE_SUB(CURDATE(),INTERVAL 7 DAY),DATE_ADD(CURDATE(),INTERVAL 7 DAY),NULL,NULL,'Issued',0,0,@y26
FROM library_books lb, students s WHERE lb.title LIKE 'English Grammar%' AND s.student_id='KHS-2026-1031' LIMIT 1;
INSERT IGNORE INTO library_transactions (book_id,student_id,issued_by,issued_at,borrow_date,due_date,returned_at,returned_date,status,fine_amount,fine_paid,academic_year_id)
SELECT lb.id,s.id,@libUser,
  DATE_SUB(NOW(),INTERVAL 20 DAY),DATE_SUB(CURDATE(),INTERVAL 20 DAY),DATE_SUB(CURDATE(),INTERVAL 6 DAY),NULL,NULL,'Issued',0,0,@y26
FROM library_books lb, students s WHERE lb.title LIKE 'Oxford Mathematics%' AND s.student_id='KHS-2026-1032' LIMIT 1;
INSERT IGNORE INTO library_transactions (book_id,student_id,issued_by,issued_at,borrow_date,due_date,returned_at,returned_date,status,fine_amount,fine_paid,academic_year_id)
SELECT lb.id,s.id,@libUser,
  DATE_SUB(NOW(),INTERVAL 30 DAY),DATE_SUB(CURDATE(),INTERVAL 30 DAY),DATE_SUB(CURDATE(),INTERVAL 16 DAY),DATE_SUB(NOW(),INTERVAL 15 DAY),DATE_SUB(CURDATE(),INTERVAL 15 DAY),'Returned',0,0,@y26
FROM library_books lb, students s WHERE lb.title LIKE 'Liberia%' AND s.student_id='KHS-2026-1071' LIMIT 1;
INSERT IGNORE INTO library_transactions (book_id,student_id,issued_by,issued_at,borrow_date,due_date,returned_at,returned_date,status,fine_amount,fine_paid,academic_year_id)
SELECT lb.id,s.id,@libUser,
  DATE_SUB(NOW(),INTERVAL 15 DAY),DATE_SUB(CURDATE(),INTERVAL 15 DAY),DATE_SUB(CURDATE(),INTERVAL 1 DAY),NULL,NULL,'Issued',7.50,0,@y26
FROM library_books lb, students s WHERE lb.title LIKE 'Biology%' AND s.student_id='KHS-2026-1052' LIMIT 1;
-- Update available copies to match
UPDATE library_books SET available=available-1 WHERE title LIKE 'English Grammar%' AND available>0;
UPDATE library_books SET available=available-1 WHERE title LIKE 'Oxford Mathematics%' AND available>0;
UPDATE library_books SET available=available-1 WHERE title LIKE 'Biology%' AND available>0;

-- ============================================================
-- 22. ICT ASSETS
-- ============================================================
INSERT IGNORE INTO ict_assets (asset_id,asset_type,brand,model,serial_number,purchase_date,location,condition_status,os_installed,status,added_by) VALUES
('KHS-PC-001','Desktop','Dell','OptiPlex 3080','SN-PC-001','2022-09-01','ICT Lab','Good','Windows 11 Pro','active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-PC-002','Desktop','Dell','OptiPlex 3080','SN-PC-002','2022-09-01','ICT Lab','Good','Windows 11 Pro','active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-PC-003','Desktop','HP','EliteDesk 800','SN-PC-003','2021-09-01','ICT Lab','Fair','Windows 10 Pro','active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-PC-004','Desktop','HP','EliteDesk 800','SN-PC-004','2021-09-01','ICT Lab','Needs Repair','Windows 10 Pro','in_repair',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-PC-005','Desktop','Lenovo','ThinkCentre','SN-PC-005','2023-08-01','Admin Office','Good','Windows 11 Pro','active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-LT-001','Laptop','HP','ProBook 450','SN-LT-001','2023-08-01','Principal Office','Good','Windows 11 Pro','active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-LT-002','Laptop','Lenovo','ThinkPad E15','SN-LT-002','2023-08-01','Admin Office','Good','Windows 11 Pro','active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-PR-001','Printer','HP','LaserJet Pro M404','SN-PR-001','2022-01-15','Admin Office','Good',NULL,'active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-PR-002','Printer','Canon','PIXMA G3020','SN-PR-002','2023-03-10','Library','Good',NULL,'active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-PJ-001','Projector','Epson','EB-W51','SN-PJ-001','2022-06-01','Science Lab','Good',NULL,'active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-SV-001','Server','Dell','PowerEdge T40','SN-SV-001','2021-08-01','Server Room','Good','Ubuntu Server 22.04','active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-RT-001','Router','Cisco','RV340','SN-RT-001','2022-01-10','Server Room','Good',NULL,'active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-AP-001','Access Point','TP-Link','EAP245','SN-AP-001','2022-01-10','Block A','Good',NULL,'active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('KHS-AP-002','Access Point','TP-Link','EAP245','SN-AP-002','2022-01-10','Block B','Good',NULL,'active',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr'));

-- Network devices
INSERT IGNORE INTO ict_network_devices (device_name,device_type,ip_address,location,brand,model,status,added_by) VALUES
('Main Router',       'Router',       '192.168.1.1', 'Server Room','Cisco',  'RV340',  'online',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('Core Switch',       'Switch',       '192.168.1.2', 'Server Room','Cisco',  'SG350',  'online',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('Block A Wi-Fi',     'Access Point', '192.168.1.10','Block A',    'TP-Link','EAP245', 'online',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('Block B Wi-Fi',     'Access Point', '192.168.1.11','Block B',    'TP-Link','EAP245', 'degraded',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('ICT Lab Switch',    'Switch',       '192.168.1.20','ICT Lab',    'TP-Link','TL-SF1024','online',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr')),
('School Server',     'Server',       '192.168.1.5', 'Server Room','Dell',   'PE T40', 'online',(SELECT id FROM users WHERE email='ict@karnhighschool.edu.lr'));

-- ICT support ticket
INSERT IGNORE INTO ict_tickets (ticket_ref,category,subject,description,priority,reported_by,reporter_name,reporter_role,status) VALUES
('TKT-2026-0001','Hardware','Desktop PC in ICT Lab not starting','KHS-PC-004 desktop does not power on. Needs urgent repair for student lab sessions.','high',
 (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr'),'Sarah Williams','teacher','in_progress'),
('TKT-2026-0002','Network','Block B Wi-Fi intermittent signal','Block B Access Point showing degraded status. Students report slow/no internet in that area.','medium',
 (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr'),'Sarah Williams','teacher','open'),
('TKT-2026-0003','Account/Password','Student unable to login to portal','Student KHS-2026-1031 reports unable to access student portal. Password reset needed.','low',
 (SELECT id FROM users WHERE email='registrar@karnhighschool.edu.lr'),'Mary Kollie','registrar','resolved');

-- ============================================================
-- 23. ANNOUNCEMENTS (full history)
-- ============================================================
SET @prinUser=(SELECT id FROM users WHERE email='principal@karnhighschool.edu.lr' LIMIT 1);
SET @vpUser  =(SELECT id FROM users WHERE email='vprincipal@karnhighschool.edu.lr' LIMIT 1);
INSERT IGNORE INTO announcements (title,message,target,is_public,created_by,published_at,expires_at) VALUES
('Welcome to Academic Year 2026/2027','We welcome all students, parents, and staff to the new academic year. First day of classes is Monday, August 18, 2026. All students must report by 7:45 AM.','all',1,@prinUser,DATE_SUB(NOW(),INTERVAL 25 DAY),NULL),
('Mid-Term Examinations — Semester 1','Mid-term examinations will be held October 6–10, 2026. All students are required to be present. Detailed timetables will be distributed by class teachers.','students',1,@prinUser,DATE_SUB(NOW(),INTERVAL 5 DAY),DATE_ADD(NOW(),INTERVAL 30 DAY)),
('PTA Meeting — First Semester','The PTA meeting for Semester 1 will be held Saturday, October 18, 2026 at 10:00 AM in the school hall. All parents and guardians are encouraged to attend.','parents',1,@prinUser,DATE_SUB(NOW(),INTERVAL 3 DAY),DATE_ADD(NOW(),INTERVAL 20 DAY)),
('Library Hours Extended','The school library is now open until 5:00 PM on weekdays. Students may borrow up to 2 books at a time with a valid student ID.','all',1,@vpUser,DATE_SUB(NOW(),INTERVAL 14 DAY),NULL),
('Fee Payment Reminder','Semester 1 school fees are due by October 31, 2026. Parents who have not yet paid should visit the Bursar''s office. Outstanding fees may affect examination participation.','parents',0,@accUser,DATE_SUB(NOW(),INTERVAL 2 DAY),DATE_ADD(NOW(),INTERVAL 45 DAY)),
('Sports Day — Annual Inter-House Competition','Annual inter-house sports day will be held on November 8, 2026. All students are expected to participate. Events include athletics, football, and relay races.','all',1,@vpUser,DATE_SUB(NOW(),INTERVAL 10 DAY),DATE_ADD(NOW(),INTERVAL 40 DAY)),
('Academic Awards Night — Class of 2026/2027','We are pleased to announce that the Annual Prize Giving and Awards Night will be held at the end of the academic year. Top students from each grade will be recognized.','all',1,@prinUser,DATE_SUB(NOW(),INTERVAL 1 DAY),NULL);

-- Historical announcements
INSERT IGNORE INTO announcements (title,message,target,is_public,created_by,published_at,expires_at) VALUES
('Welcome to Academic Year 2025/2026','KHS warmly welcomes all students and staff to the 2025/2026 academic year. Classes begin August 18, 2025.','all',1,@prinUser,'2025-08-15 08:00:00',NULL),
('Graduation Ceremony — Class of 2024/2025','The graduation ceremony for the Class of 2024/2025 will be held June 27, 2025. Congratulations to all graduating students!','all',1,@prinUser,'2025-06-01 08:00:00',NULL),
('2023/2024 End of Year Results Published','End-of-year results for all grades have been published. Students can access their report cards through the student portal.','students',1,@vpUser,'2024-06-25 08:00:00',NULL);

-- ============================================================
-- 24. EVENTS (full calendar)
-- ============================================================
SET @saUser=(SELECT id FROM users WHERE email='schooladmin@karnhighschool.edu.lr' LIMIT 1);
INSERT IGNORE INTO events (title,description,event_date,start_time,end_time,venue,category,is_public,created_by) VALUES
('Academic Year Opening','Official opening ceremony for 2026/2027 academic year.',                                    '2026-08-18','09:00:00','11:00:00','School Hall',      'academic',  1,@prinUser),
('First PTA Meeting',    'Parent-Teacher Association first meeting of the year.',                                     '2026-09-20','10:00:00','13:00:00','School Hall',      'community', 1,@prinUser),
('Mid-Term Exams Week',  'Semester 1 mid-term examinations for all grades.',                                          DATE_ADD(CURDATE(),INTERVAL 14 DAY),'07:30:00','15:30:00','All Classrooms','academic',1,@prinUser),
('PTA Second Meeting',   'Second PTA meeting. Parents receive mid-term report updates.',                              DATE_ADD(CURDATE(),INTERVAL 21 DAY),'10:00:00','13:00:00','School Hall','community',1,@prinUser),
('Annual Sports Day',    'KHS Annual Inter-House Sports Day. All students and families welcome.',                     DATE_ADD(CURDATE(),INTERVAL 35 DAY),'08:00:00','17:00:00','School Ground',  'sports',    1,@vpUser),
('Science Fair 2026',    'Annual science and technology fair. Students present projects.',                             DATE_ADD(CURDATE(),INTERVAL 50 DAY),'09:00:00','16:00:00','Science Block',  'academic',  1,@vpUser),
('Semester 1 Final Exams','End-of-semester examinations for all grades.',                                             DATE_ADD(CURDATE(),INTERVAL 70 DAY),'07:30:00','15:30:00','All Classrooms', 'academic',  1,@prinUser),
('Christmas Break Begins','School closes for Christmas holiday.',                                                     '2026-12-19','12:00:00',NULL,        'All',            'holiday',   1,@prinUser),
('Second Semester Opens', 'Second semester begins for all students.',                                                 '2027-01-06','07:30:00',NULL,        'All Classrooms', 'academic',  1,@prinUser),
('Prize Giving Day',      'Annual Prize Giving ceremony recognizing academic excellence.',                            DATE_ADD(CURDATE(),INTERVAL 90 DAY), '09:00:00','14:00:00','School Hall','cultural',  1,@prinUser),
('Grade 12 Graduation',   'Graduation ceremony for the Class of 2026/2027.',                                         '2027-06-26','10:00:00','14:00:00','School Hall',      'graduation',1,@prinUser);

-- ============================================================
-- 25. MORE APPLICATIONS (complete workflow demo)
-- ============================================================
INSERT IGNORE INTO applications (application_number,first_name,middle_name,last_name,date_of_birth,gender,nationality,phone,current_address,county,grade_applying_for,grade_id,academic_year_id,academic_year,guardian_name,guardian_relationship,guardian_phone,status,document_status,entrance_status) VALUES
('KHS-2026-000301','Abraham','K.','Kollie', '2011-04-12','Male',  'Liberian','+231 881 300 001','Karnplay, Nimba','Nimba','Grade 9', @g9, @y26,'2026/2027','Sarah Kollie', 'Mother','+231 881 300 002','Application Submitted','Pending','Not scheduled'),
('KHS-2026-000302','Blessing','', 'Freeman','2009-08-25','Female','Liberian','+231 881 300 003','Ganta, Nimba',  'Nimba','Grade 11',@g11,@y26,'2026/2027','James Freeman','Father','+231 881 300 004','Under Review',        'Pending','Not scheduled'),
('KHS-2026-000303','George', 'A.','Sumo',   '2013-01-17','Male',  'Liberian','+231 881 300 005','Karnplay, Nimba','Nimba','Grade 7', @g7, @y26,'2026/2027','Agnes Sumo',   'Mother','+231 881 300 006','Documents needed',    'Pending','Not scheduled'),
('KHS-2026-000304','Mariama','',  'Konneh', '2010-11-30','Female','Liberian','+231 881 300 007','Sanniquellie',  'Nimba','Grade 10',@g10,@y26,'2026/2027','Thomas Konneh','Father','+231 881 300 008','Approved for entrance','Verified','Not scheduled'),
('KHS-2026-000305','Emmanuel','K.','Harris','2012-06-09','Male',  'Liberian','+231 881 300 009','Karnplay, Nimba','Nimba','Grade 8', @g8, @y26,'2026/2027','Paul Harris',  'Father','+231 881 300 010','Entrance scheduled',  'Verified','Scheduled'),
('KHS-2026-000306','Patience','', 'Williams','2008-03-14','Female','Liberian','+231 881 300 011','Ganta, Nimba', 'Nimba','Grade 12',@g12,@y26,'2026/2027','Mary Williams','Mother','+231 881 300 012','Admitted',            'Verified','Passed'),
('KHS-2026-000307','Daniel',  'J.','Flomo', '2011-09-20','Male',  'Liberian','+231 881 300 013','Karnplay, Nimba','Nimba','Grade 9', @g9, @y26,'2026/2027','Agnes Flomo',  'Mother','+231 881 300 014','Rejected',            'Incomplete','Failed');

UPDATE applications SET entrance_letter_ref='KEL-2026-00304' WHERE application_number='KHS-2026-000304';
UPDATE applications SET entrance_letter_ref='KEL-2026-00305',entrance_exam_date=DATE_ADD(CURDATE(),INTERVAL 7 DAY) WHERE application_number='KHS-2026-000305';

-- ============================================================
-- 26. APPROVAL REQUESTS (workflow demo)
-- ============================================================
INSERT IGNORE INTO approval_requests (module,record_type,record_id,requested_by,status,priority,title,description) VALUES
('marks','assessment_batch',1,@enteredBy,'pending','normal','Marks Submitted: Grade 8A — Mathematics','Teacher has submitted 1st Period marks for Grade 8A Mathematics. Please review and approve.'),
('admissions','application',(SELECT id FROM applications WHERE application_number='KHS-2026-000302'),@vpUser,'pending','high','Admission Review: Blessing Freeman — Grade 11','Application reviewed. Documents verified. Recommend for entrance examination.'),
('discipline','discipline_record',(SELECT id FROM discipline_records WHERE violation_type='Cheating' LIMIT 1),@discUser,'pending','high','Suspension Approval: Grade 11 Student','Student found cheating. Requesting approval for 2-day suspension.'),
('marks','report_card',1,@vpUser,'approved','normal','Report Cards Published: Grade 10A','All Grade 10A report cards generated and published for parent/student access.'),
('finance','fee_waiver',1,@accUser,'pending','normal','Fee Waiver Request: KHS-2026-1001','Parent of Fatu Kollie (Grade 7) requesting partial fee waiver due to financial hardship.');

-- ============================================================
-- 27. AUDIT LOGS (realistic activity history)
-- ============================================================
INSERT IGNORE INTO audit_logs (user_name,action,module,record_type,record_id,old_value,new_value,ip_address,created_at) VALUES
('Mary Kollie',      'create',        'admissions','application',1,NULL,'Application Submitted','192.168.1.10',DATE_SUB(NOW(),INTERVAL 25 DAY)),
('Mary Kollie',      'update_status', 'admissions','application',2,'Application Submitted','Under Review','192.168.1.10',DATE_SUB(NOW(),INTERVAL 20 DAY)),
('Sarah Williams',   'submit_marks',  'marks','assessment_score',1,'draft','submitted','192.168.1.11',DATE_SUB(NOW(),INTERVAL 14 DAY)),
('Alice Konneh',     'approve_marks', 'marks','assessment_score',1,'submitted','approved','192.168.1.12',DATE_SUB(NOW(),INTERVAL 13 DAY)),
('Moses Johnson',    'create',        'finance','payment',1,NULL,'LRD 15000','192.168.1.13',DATE_SUB(NOW(),INTERVAL 10 DAY)),
('John Cooper',      'login',         'auth','user',1,NULL,'Login','192.168.1.14',DATE_SUB(NOW(),INTERVAL 2 HOUR)),
('Sarah Williams',   'login',         'auth','user',10,NULL,'Login','192.168.1.11',DATE_SUB(NOW(),INTERVAL 3 HOUR)),
('Agnes Wea',        'create',        'discipline','discipline_record',1,NULL,'Misconduct - KHS-2026-1011','192.168.1.15',DATE_SUB(NOW(),INTERVAL 15 DAY)),
('Alice Konneh',     'publish',       'academics','report_card',1,'generated','published','192.168.1.12',DATE_SUB(NOW(),INTERVAL 5 DAY)),
('David Flomo',      'issue_book',    'library','transaction',1,NULL,'English Grammar in Use → KHS-2026-1031','192.168.1.16',DATE_SUB(NOW(),INTERVAL 7 DAY)),
('Emmanuel Doe',     'login',         'auth','user',9,NULL,'Login','192.168.1.17',DATE_SUB(NOW(),INTERVAL 1 HOUR)),
('System Admin',     'update',        'system','settings',1,NULL,'School settings updated','192.168.1.1',DATE_SUB(NOW(),INTERVAL 30 DAY));

-- ============================================================
-- 28. ADMIN USERS SHIM (for backward compatibility)
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'staff',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login DATETIME DEFAULT NULL,
  username VARCHAR(60) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add username column if it doesn't exist
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS username VARCHAR(80) NULL;

INSERT INTO admin_users (name,email,password_hash,role,username) VALUES
('System Administrator', 'sysadmin@karnhighschool.edu.lr',    @pw,'sys_admin',    'sysadmin'),
('School Administrator', 'schooladmin@karnhighschool.edu.lr',  @pw,'school_admin', 'schooladmin'),
('Mr. John T. Cooper',   'principal@karnhighschool.edu.lr',    @pw,'principal',    'principal'),
('Mrs. Alice Konneh',    'vprincipal@karnhighschool.edu.lr',   @pw,'vice_principal','vprincipal'),
('Miss Mary E. Kollie',  'registrar@karnhighschool.edu.lr',    @pw,'registrar',    'registrar'),
('Mr. Moses M. Johnson', 'accountant@karnhighschool.edu.lr',   @pw,'accountant',   'accountant'),
('Mr. David K. Flomo',   'librarian@karnhighschool.edu.lr',    @pw,'librarian',    'librarian'),
('Miss Agnes T. Wea',    'discipline@karnhighschool.edu.lr',   @pw,'discipline_officer','discipline'),
('Mr. Emmanuel R. Doe',  'ict@karnhighschool.edu.lr',          @pw,'ict_officer',   'ict'),
('Mrs. Sarah A. Williams','teacher@karnhighschool.edu.lr',     @pw,'teacher',      'teacher'),
('Mrs. Patricia L. Sumo','psumo@karnhighschool.edu.lr',        @pw,'class_teacher', 'class_teacher')
ON DUPLICATE KEY UPDATE password_hash=@pw, role=VALUES(role);

-- ============================================================
-- 29. USERS TABLE — add username column if missing
-- ============================================================
ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(80) NULL AFTER email;
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(40) NULL AFTER username;
ALTER TABLE users ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'Active' AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL;

-- Add role+username convenience columns
UPDATE users u JOIN roles r ON r.id=u.role_id SET u.role=r.name WHERE u.role IS NULL;
UPDATE users SET username=SUBSTRING_INDEX(email,'@',1) WHERE username IS NULL AND email IS NOT NULL;

-- ============================================================
-- 30. FINAL CLEANUP & VERIFICATION
-- ============================================================
-- Fix any students without class assignments
UPDATE students SET current_class_id=@c10a WHERE student_id='KHS-2024-0183' AND current_class_id IS NULL;
UPDATE students SET current_class_id=@c11a WHERE student_id='KHS-2024-0181' AND current_class_id IS NULL;
UPDATE students SET current_class_id=@c8a  WHERE student_id='KHS-2024-0184' AND current_class_id IS NULL;

-- Ensure all students have academic_year_id set
UPDATE students SET academic_year_id=@y26 WHERE academic_year_id IS NULL AND status='Active';

SET FOREIGN_KEY_CHECKS = 1;

-- ── Verification ──────────────────────────────────────────────
SELECT 'SEED COMPLETE — Summary:' AS '';
SELECT t,n FROM (
  SELECT 'academic_years'       t, COUNT(*) n FROM academic_years
  UNION ALL SELECT 'users',              COUNT(*) FROM users
  UNION ALL SELECT 'staff',              COUNT(*) FROM staff
  UNION ALL SELECT 'teachers',           COUNT(*) FROM teachers
  UNION ALL SELECT 'students',           COUNT(*) FROM students
  UNION ALL SELECT 'classes',            COUNT(*) FROM classes
  UNION ALL SELECT 'teacher_assignments',COUNT(*) FROM teacher_assignments
  UNION ALL SELECT 'timetable_slots',    COUNT(*) FROM timetable
  UNION ALL SELECT 'applications',       COUNT(*) FROM applications
  UNION ALL SELECT 'assessment_configs', COUNT(*) FROM assessment_configs
  UNION ALL SELECT 'assessment_scores',  COUNT(*) FROM assessment_scores
  UNION ALL SELECT 'attendance_records', COUNT(*) FROM attendance
  UNION ALL SELECT 'payments',           COUNT(*) FROM payments
  UNION ALL SELECT 'report_cards',       COUNT(*) FROM report_cards
  UNION ALL SELECT 'discipline_records', COUNT(*) FROM discipline_records
  UNION ALL SELECT 'library_books',      COUNT(*) FROM library_books
  UNION ALL SELECT 'library_transactions',COUNT(*) FROM library_transactions
  UNION ALL SELECT 'ict_assets',         COUNT(*) FROM ict_assets
  UNION ALL SELECT 'announcements',      COUNT(*) FROM announcements
  UNION ALL SELECT 'events',             COUNT(*) FROM events
  UNION ALL SELECT 'approval_requests',  COUNT(*) FROM approval_requests
  UNION ALL SELECT 'audit_logs',         COUNT(*) FROM audit_logs
) summary;

SELECT 'LOGIN CREDENTIALS (all password: 1234):' AS '';
SELECT u.email, r.name AS role FROM users u JOIN roles r ON r.id=u.role_id
WHERE u.email LIKE '%karnhighschool%' AND r.name NOT IN ('student','parent','applicant')
ORDER BY r.name, u.email;
