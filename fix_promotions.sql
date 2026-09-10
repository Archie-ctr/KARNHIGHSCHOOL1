-- ============================================================
-- FIX: Complete promotion records for ALL students
-- across ALL past academic years 2023/2024→2024/2025→2025/2026
-- Every active student should have a promotion record
-- for each year they completed
-- ============================================================
USE karnhighschool;
SET FOREIGN_KEY_CHECKS = 0;

SET @y23 = (SELECT id FROM academic_years WHERE name='2023/2024');
SET @y24 = (SELECT id FROM academic_years WHERE name='2024/2025');
SET @y25 = (SELECT id FROM academic_years WHERE name='2025/2026');
SET @y26 = (SELECT id FROM academic_years WHERE name='2026/2027');
SET @regBy = (SELECT id FROM users WHERE email='registrar@karnhighschool.edu.lr' LIMIT 1);
SET @appBy = (SELECT id FROM users WHERE email='vprincipal@karnhighschool.edu.lr' LIMIT 1);
SET @entBy = (SELECT id FROM users WHERE email='teacher@karnhighschool.edu.lr'    LIMIT 1);
SET @accBy = (SELECT id FROM users WHERE email='accountant@karnhighschool.edu.lr' LIMIT 1);

-- ============================================================
-- STEP 1: Build a complete map of each student's grade
--         for each past year based on their current grade
--         and when they were admitted
-- Logic: if student is currently in Grade X in 2026/2027,
--        in 2025/2026 they were in Grade X-1,
--        in 2024/2025 they were in Grade X-2, etc.
--        (unless admitted later than 2023)
-- ============================================================

-- Helper: get class id for a grade+year+section
-- We'll use COALESCE to fall back to any available class for that grade/year

-- ── CURRENT YEAR GRADE IDs ────────────────────────────────────
SET @gid7  = (SELECT id FROM grades WHERE name='Grade 7');
SET @gid8  = (SELECT id FROM grades WHERE name='Grade 8');
SET @gid9  = (SELECT id FROM grades WHERE name='Grade 9');
SET @gid10 = (SELECT id FROM grades WHERE name='Grade 10');
SET @gid11 = (SELECT id FROM grades WHERE name='Grade 11');
SET @gid12 = (SELECT id FROM grades WHERE name='Grade 12');

-- ============================================================
-- STEP 2: Insert missing 2023/2024 promotions
--         For students who were admitted in 2023 or earlier
--         and are currently in Grade 8+
-- ============================================================

-- Students currently in Grade 8 → were Grade 7 in 2023/2024 → promoted to Grade 8
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid7,@gid8,@y23,@y24,'2024-07-01',@regBy,
  CONCAT('End of 2023/2024: ',s.first_name,' ',s.last_name,' promoted from Grade 7 to Grade 8')
FROM students s
WHERE s.current_grade_id=@gid8
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023;

-- Students currently in Grade 9 → were Grade 8 in 2023/2024 → promoted
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid8,@gid9,@y23,@y24,'2024-07-01',@regBy,
  CONCAT('End of 2023/2024: ',s.first_name,' ',s.last_name,' promoted from Grade 8 to Grade 9')
FROM students s
WHERE s.current_grade_id=@gid9
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023;

-- Students currently in Grade 10 → were Grade 9 in 2023/2024
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid9,@gid10,@y23,@y24,'2024-07-01',@regBy,
  CONCAT('End of 2023/2024: ',s.first_name,' ',s.last_name,' promoted from Grade 9 to Grade 10')
FROM students s
WHERE s.current_grade_id=@gid10
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023;

-- Students currently in Grade 11 → were Grade 10 in 2023/2024
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid10,@gid11,@y23,@y24,'2024-07-01',@regBy,
  CONCAT('End of 2023/2024: ',s.first_name,' ',s.last_name,' promoted from Grade 10 to Grade 11')
FROM students s
WHERE s.current_grade_id=@gid11
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023;

-- Students currently in Grade 12 → were Grade 11 in 2023/2024
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid11,@gid12,@y23,@y24,'2024-07-01',@regBy,
  CONCAT('End of 2023/2024: ',s.first_name,' ',s.last_name,' promoted from Grade 11 to Grade 12')
FROM students s
WHERE s.current_grade_id=@gid12
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023;

-- ============================================================
-- STEP 3: Insert missing 2024/2025 promotions
--         For all students admitted 2024 or earlier
-- ============================================================

-- Currently Grade 8 → admitted in 2024 → were Grade 7 in 2024/2025
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid7,@gid8,@y24,@y25,'2025-07-01',@regBy,
  CONCAT('End of 2024/2025: ',s.first_name,' ',s.last_name,' promoted Grade 7→8')
FROM students s
WHERE s.current_grade_id=@gid8
  AND s.status='Active'
  AND YEAR(s.admission_date)=2024
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y24 AND sp.to_grade_id=@gid8
  );

-- Currently Grade 9 → were Grade 8 in 2024/2025
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid8,@gid9,@y24,@y25,'2025-07-01',@regBy,
  CONCAT('End of 2024/2025: ',s.first_name,' ',s.last_name,' promoted Grade 8→9')
FROM students s
WHERE s.current_grade_id=@gid9
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2024
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y24 AND sp.to_grade_id=@gid9
  );

-- Currently Grade 10 → were Grade 9 in 2024/2025
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid9,@gid10,@y24,@y25,'2025-07-01',@regBy,
  CONCAT('End of 2024/2025: ',s.first_name,' ',s.last_name,' promoted Grade 9→10')
FROM students s
WHERE s.current_grade_id=@gid10
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2024
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y24 AND sp.to_grade_id=@gid10
  );

-- Currently Grade 11 → were Grade 10 in 2024/2025
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid10,@gid11,@y24,@y25,'2025-07-01',@regBy,
  CONCAT('End of 2024/2025: ',s.first_name,' ',s.last_name,' promoted Grade 10→11')
FROM students s
WHERE s.current_grade_id=@gid11
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2024
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y24 AND sp.to_grade_id=@gid11
  );

-- Currently Grade 12 → were Grade 11 in 2024/2025
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid11,@gid12,@y24,@y25,'2025-07-01',@regBy,
  CONCAT('End of 2024/2025: ',s.first_name,' ',s.last_name,' promoted Grade 11→12')
FROM students s
WHERE s.current_grade_id=@gid12
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2024
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y24 AND sp.to_grade_id=@gid12
  );

-- ============================================================
-- STEP 4: Insert missing 2025/2026 promotions
-- ============================================================

-- Currently Grade 8 → admitted 2025 → were Grade 7 in 2025/2026
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid7,@gid8,@y25,@y26,'2026-07-01',@regBy,
  CONCAT('End of 2025/2026: ',s.first_name,' ',s.last_name,' promoted Grade 7→8')
FROM students s
WHERE s.current_grade_id=@gid8
  AND s.status='Active'
  AND YEAR(s.admission_date)=2025
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y25 AND sp.to_grade_id=@gid8
  );

-- Currently Grade 9 → were Grade 8 in 2025/2026
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid8,@gid9,@y25,@y26,'2026-07-01',@regBy,
  CONCAT('End of 2025/2026: ',s.first_name,' ',s.last_name,' promoted Grade 8→9')
FROM students s
WHERE s.current_grade_id=@gid9
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2025
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y25 AND sp.to_grade_id=@gid9
  );

-- Currently Grade 10 → were Grade 9 in 2025/2026
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid9,@gid10,@y25,@y26,'2026-07-01',@regBy,
  CONCAT('End of 2025/2026: ',s.first_name,' ',s.last_name,' promoted Grade 9→10')
FROM students s
WHERE s.current_grade_id=@gid10
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2025
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y25 AND sp.to_grade_id=@gid10
  );

-- Currently Grade 11 → were Grade 10 in 2025/2026
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid10,@gid11,@y25,@y26,'2026-07-01',@regBy,
  CONCAT('End of 2025/2026: ',s.first_name,' ',s.last_name,' promoted Grade 10→11')
FROM students s
WHERE s.current_grade_id=@gid11
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2025
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y25 AND sp.to_grade_id=@gid11
  );

-- Currently Grade 12 → were Grade 11 in 2025/2026
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid11,@gid12,@y25,@y26,'2026-07-01',@regBy,
  CONCAT('End of 2025/2026: ',s.first_name,' ',s.last_name,' promoted Grade 11→12')
FROM students s
WHERE s.current_grade_id=@gid12
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2025
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y25 AND sp.to_grade_id=@gid12
  );

-- ============================================================
-- STEP 5: Add graduation promotions for graduated students
-- ============================================================
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'graduated',@gid12,NULL,gr.academic_year_id,NULL,gr.graduation_date,@regBy,
  CONCAT('Graduated: ',s.first_name,' ',s.last_name,' — Class of ',ay.name)
FROM students s
JOIN graduation_records gr ON gr.student_id=s.id
JOIN academic_years ay ON ay.id=gr.academic_year_id
WHERE s.status='Graduated'
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.movement_type='graduated' AND sp.from_year_id=gr.academic_year_id
  );

-- ============================================================
-- STEP 6: Fill missing REPORT CARDS for all students
--         for every year they attended
-- For each active student, insert a report card for each past year
-- where they don't already have one
-- ============================================================

-- Helper proc via temp table approach
CREATE TEMPORARY TABLE IF NOT EXISTS student_year_map (
  student_id INT UNSIGNED,
  class_id   INT UNSIGNED,
  year_id    INT UNSIGNED,
  grade_seq  INT
);

-- Build the map: what grade was each student in for each year
-- A student currently in Grade X (seq S) was in grade S-n years ago
INSERT INTO student_year_map (student_id, class_id, year_id, grade_seq)
SELECT s.id,
  COALESCE(
    (SELECT c2.id FROM classes c2 WHERE c2.grade_id=@gid7 AND c2.academic_year_id=@y23 AND c2.section='A' LIMIT 1),
    s.current_class_id
  ),
  @y23, 8
FROM students s
JOIN grades g ON g.id=s.current_grade_id
WHERE s.status='Active' AND g.sequence=11 AND YEAR(s.admission_date)<=2023; -- Grade 10 now = Grade 7 in 23/24

INSERT INTO student_year_map (student_id, class_id, year_id, grade_seq)
SELECT s.id,
  COALESCE((SELECT c2.id FROM classes c2 WHERE c2.grade_id=@gid8 AND c2.academic_year_id=@y23 AND c2.section='A' LIMIT 1), s.current_class_id),
  @y23, 9
FROM students s JOIN grades g ON g.id=s.current_grade_id
WHERE s.status='Active' AND g.sequence=12 AND YEAR(s.admission_date)<=2023; -- Grade 11 now = Grade 8 in 23/24

INSERT INTO student_year_map (student_id, class_id, year_id, grade_seq)
SELECT s.id,
  COALESCE((SELECT c2.id FROM classes c2 WHERE c2.grade_id=@gid9 AND c2.academic_year_id=@y23 AND c2.section='A' LIMIT 1), s.current_class_id),
  @y23, 10
FROM students s JOIN grades g ON g.id=s.current_grade_id
WHERE s.status='Active' AND g.sequence=13 AND YEAR(s.admission_date)<=2023; -- Grade 12 now = Grade 9 in 23/24

-- Now insert report cards for students missing them
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT m.student_id, m.class_id, m.year_id,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(83+RAND()*16,1),
  ROUND(65+RAND()*28,1),
  ELT(1+FLOOR(RAND()*4),'Excellent','Very Good','Good','Satisfactory'),
  ELT(1+FLOOR(RAND()*4),'Good academic performance throughout the year.','Shows consistent effort and dedication.','Hardworking student with good potential.','Making steady progress.'),
  'Promoted. Keep up the good work!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 998 DAY),
  DATE_SUB(NOW(),INTERVAL 997 DAY),
  @appBy
FROM student_year_map m
WHERE NOT EXISTS (
  SELECT 1 FROM report_cards rc
  WHERE rc.student_id=m.student_id AND rc.academic_year_id=m.year_id
);

DROP TEMPORARY TABLE IF EXISTS student_year_map;

-- ============================================================
-- STEP 7: Fill missing REPORT CARDS for 2024/2025
--         Every student currently in Grade 8+ who was admitted <=2024
-- ============================================================
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id,
  COALESCE(
    (SELECT c.id FROM classes c WHERE c.academic_year_id=@y24
     AND c.grade_id=(SELECT id FROM grades WHERE sequence=g.sequence-2 LIMIT 1)
     AND c.section='A' LIMIT 1),
    s.current_class_id
  ),
  @y24,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(84+RAND()*15,1), ROUND(67+RAND()*26,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*4),'Impressive improvement over the year.','Continues to show strong academic ability.','Works well independently and in groups.','Great year overall.'),
  'Promoted. Best wishes for next year!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 638 DAY),
  DATE_SUB(NOW(),INTERVAL 637 DAY),
  @appBy
FROM students s
JOIN grades g ON g.id=s.current_grade_id
WHERE s.status='Active'
  AND g.sequence>=11   -- Grade 10+ currently (were grade 8+ in 2024/2025)
  AND YEAR(s.admission_date)<=2024
  AND NOT EXISTS (
    SELECT 1 FROM report_cards rc WHERE rc.student_id=s.id AND rc.academic_year_id=@y24
  );

-- ============================================================
-- STEP 8: Fill missing REPORT CARDS for 2025/2026
--         Every student admitted <=2025 who is currently in Grade 8+
-- ============================================================
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id,
  COALESCE(
    (SELECT c.id FROM classes c WHERE c.academic_year_id=@y25
     AND c.grade_id=(SELECT id FROM grades WHERE sequence=g.sequence-1 LIMIT 1)
     AND c.section='A' LIMIT 1),
    s.current_class_id
  ),
  @y25,
  17+FLOOR(RAND()*3), FLOOR(RAND()*2), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(68+RAND()*25,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Excellent preparation for next grade.','Strong academic foundation built this year.','Ready for promotion.'),
  'Promoted. Looking forward to next year!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 278 DAY),
  DATE_SUB(NOW(),INTERVAL 277 DAY),
  @appBy
FROM students s
JOIN grades g ON g.id=s.current_grade_id
WHERE s.status='Active'
  AND g.sequence>=9   -- Grade 8+ currently (were grade 7+ in 2025/2026)
  AND YEAR(s.admission_date)<=2025
  AND NOT EXISTS (
    SELECT 1 FROM report_cards rc WHERE rc.student_id=s.id AND rc.academic_year_id=@y25
  );

-- ============================================================
-- STEP 9: Fill ASSESSMENT SCORES for all past years
--         for students missing them
-- ============================================================

-- 2023/2024: students currently in Grade 10, 11, 12 (admitted <=2023)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=(SELECT id FROM grades WHERE sequence=g.sequence-3) AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  sub.id, ac.id, @y23,
  ROUND(60+RAND()*35,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 1050 DAY), DATE_SUB(NOW(),INTERVAL 1048 DAY), @appBy, 'approved'
FROM students s
JOIN grades g ON g.id=s.current_grade_id
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active'
  AND g.sequence IN (11,12,13)  -- currently Grade 10,11,12
  AND YEAR(s.admission_date)<=2023
  AND sub.code IN ('MAT','ENG','HIS')
  AND ac.academic_year_id=@y23
  AND NOT EXISTS (
    SELECT 1 FROM assessment_scores ex
    WHERE ex.student_id=s.id AND ex.subject_id=sub.id
      AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y23
  );

-- 2024/2025: students currently in Grade 9, 10, 11, 12 (admitted <=2024)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=(SELECT id FROM grades WHERE sequence=g.sequence-2) AND c.academic_year_id=@y24 AND c.section='A' LIMIT 1), s.current_class_id),
  sub.id, ac.id, @y24,
  ROUND(62+RAND()*33,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 690 DAY), DATE_SUB(NOW(),INTERVAL 688 DAY), @appBy, 'approved'
FROM students s
JOIN grades g ON g.id=s.current_grade_id
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active'
  AND g.sequence IN (10,11,12,13)  -- currently Grade 9,10,11,12
  AND YEAR(s.admission_date)<=2024
  AND sub.code IN ('MAT','ENG','BIO')
  AND ac.academic_year_id=@y24
  AND NOT EXISTS (
    SELECT 1 FROM assessment_scores ex
    WHERE ex.student_id=s.id AND ex.subject_id=sub.id
      AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y24
  );

-- 2025/2026: students currently in Grade 8, 9, 10, 11, 12 (admitted <=2025)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=(SELECT id FROM grades WHERE sequence=g.sequence-1) AND c.academic_year_id=@y25 AND c.section='A' LIMIT 1), s.current_class_id),
  sub.id, ac.id, @y25,
  ROUND(64+RAND()*31,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 330 DAY), DATE_SUB(NOW(),INTERVAL 328 DAY), @appBy, 'approved'
FROM students s
JOIN grades g ON g.id=s.current_grade_id
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active'
  AND g.sequence IN (9,10,11,12,13)  -- currently Grade 8,9,10,11,12
  AND YEAR(s.admission_date)<=2025
  AND sub.code IN ('MAT','ENG','BIO','HIS')
  AND ac.academic_year_id=@y25
  AND NOT EXISTS (
    SELECT 1 FROM assessment_scores ex
    WHERE ex.student_id=s.id AND ex.subject_id=sub.id
      AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y25
  );

-- 2026/2027 current year — fill for ALL active students
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, s.current_class_id, sub.id, ac.id, @y26,
  ROUND(65+RAND()*30,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 15 DAY), DATE_SUB(NOW(),INTERVAL 12 DAY), @appBy, 'approved'
FROM students s
JOIN grades g ON g.id=s.current_grade_id
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active'
  AND g.sequence>=8  -- Grade 7+
  AND sub.code IN ('MAT','ENG','BIO')
  AND ac.academic_year_id=@y26
  AND s.current_class_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM assessment_scores ex
    WHERE ex.student_id=s.id AND ex.subject_id=sub.id
      AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y26
  );

-- ============================================================
-- STEP 10: Fill payments for all students, all years
-- ============================================================
SET @rownum=9000;
INSERT IGNORE INTO payments (receipt_number,student_id,fee_structure_id,amount,currency,payment_method,payment_date,academic_year_id,recorded_by)
SELECT CONCAT('REC-FIX-',LPAD((@rownum:=@rownum+1),5,'0')),
  s.id, fs.id, fs.amount, fs.currency,
  ELT(1+FLOOR(RAND()*3),'Cash','Mobile money','Bank transfer'),
  -- Date based on year
  CASE
    WHEN fs.academic_year_id=@y23 THEN DATE_SUB('2024-06-28', INTERVAL FLOOR(RAND()*200) DAY)
    WHEN fs.academic_year_id=@y24 THEN DATE_SUB('2025-06-27', INTERVAL FLOOR(RAND()*200) DAY)
    WHEN fs.academic_year_id=@y25 THEN DATE_SUB('2026-06-26', INTERVAL FLOOR(RAND()*200) DAY)
    ELSE DATE_SUB(CURDATE(), INTERVAL FLOOR(RAND()*60) DAY)
  END,
  fs.academic_year_id, @accBy
FROM students s
JOIN grades g ON g.id=s.current_grade_id
CROSS JOIN fee_structures fs
WHERE s.status='Active'
  AND g.sequence>=8
  AND fs.is_mandatory=1
  -- Only insert for years the student was enrolled
  AND (
    (fs.academic_year_id=@y23 AND YEAR(s.admission_date)<=2023 AND g.sequence>=11) OR
    (fs.academic_year_id=@y24 AND YEAR(s.admission_date)<=2024 AND g.sequence>=10) OR
    (fs.academic_year_id=@y25 AND YEAR(s.admission_date)<=2025 AND g.sequence>=9) OR
    (fs.academic_year_id=@y26 AND g.sequence>=8)
  )
  AND NOT EXISTS (
    SELECT 1 FROM payments p
    WHERE p.student_id=s.id AND p.fee_structure_id=fs.id
  );

-- ============================================================
-- STEP 11: Add current-year attendance for ALL active students
-- ============================================================
INSERT IGNORE INTO attendance (student_id,class_id,academic_year_id,date,status,recorded_by)
SELECT s.id, s.current_class_id, @y26,
  DATE_SUB(CURDATE(), INTERVAL n.n DAY),
  ELT(1+FLOOR(RAND()*10),'Present','Present','Present','Present','Present','Present','Present','Present','Absent','Late'),
  @entBy
FROM students s
JOIN grades g ON g.id=s.current_grade_id
CROSS JOIN (
  SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
  UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11
  UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18
  UNION SELECT 21 UNION SELECT 22 UNION SELECT 23
) n
WHERE s.status='Active'
  AND g.sequence>=8
  AND s.current_class_id IS NOT NULL
  AND DAYOFWEEK(DATE_SUB(CURDATE(), INTERVAL n.n DAY)) BETWEEN 2 AND 6
ON DUPLICATE KEY UPDATE status=VALUES(status);

SET FOREIGN_KEY_CHECKS = 1;

-- ── Final verification ────────────────────────────────────────
SELECT 'PROMOTION RECORDS BY YEAR:' AS info;
SELECT ay.name year_promoted_to, g_from.name from_grade, g_to.name to_grade, COUNT(*) students
FROM student_promotions sp
JOIN academic_years ay ON ay.id=sp.to_year_id
JOIN grades g_from ON g_from.id=sp.from_grade_id
LEFT JOIN grades g_to ON g_to.id=sp.to_grade_id
GROUP BY ay.id, sp.from_grade_id, sp.to_grade_id
ORDER BY ay.name, g_from.sequence;

SELECT 'REPORT CARDS BY YEAR:' AS info;
SELECT ay.name year, COUNT(*) cards
FROM report_cards rc JOIN academic_years ay ON ay.id=rc.academic_year_id
WHERE rc.status='published'
GROUP BY ay.id ORDER BY ay.name;

SELECT 'SCORES BY YEAR:' AS info;
SELECT ay.name year, COUNT(*) scores
FROM assessment_scores a JOIN academic_years ay ON ay.id=a.academic_year_id
WHERE a.status='approved'
GROUP BY ay.id ORDER BY ay.name;

SELECT 'STUDENTS PER GRADE:' AS info;
SELECT g.name grade, COUNT(*) n
FROM students s JOIN grades g ON g.id=s.current_grade_id
WHERE s.status='Active' GROUP BY g.id ORDER BY g.sequence;
