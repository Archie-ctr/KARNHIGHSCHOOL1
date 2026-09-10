-- ============================================================
-- FIX 2: Complete 2023/2024 promotions + current year scores
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

-- Grade IDs
SET @gid7  = (SELECT id FROM grades WHERE name='Grade 7');
SET @gid8  = (SELECT id FROM grades WHERE name='Grade 8');
SET @gid9  = (SELECT id FROM grades WHERE name='Grade 9');
SET @gid10 = (SELECT id FROM grades WHERE name='Grade 10');
SET @gid11 = (SELECT id FROM grades WHERE name='Grade 11');
SET @gid12 = (SELECT id FROM grades WHERE name='Grade 12');

-- ============================================================
-- 1. PROMOTIONS FOR 2023/2024 (end-of-year promotions)
--    These represent what happened AT THE END of 2023/2024
--    i.e. students being promoted INTO 2024/2025
-- The promotion year = to_year_id = 2024/2025
-- from_year_id = 2023/2024
-- BUT we show them as "2023/2024 promotions" using from_year_id
-- ============================================================

-- Currently Grade 9 → were in Grade 8 during 2023/2024
-- Admitted in 2023 or earlier → were definitely in Grade 8 in 2023/2024
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid8,@gid9,@y23,@y24,'2024-07-01',@regBy,
  CONCAT(s.first_name,' ',s.last_name,': Grade 8 → 9 (end of 2023/2024)')
FROM students s
WHERE s.current_grade_id=@gid9
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y23 AND sp.from_grade_id=@gid8
  );

-- Currently Grade 10 → were in Grade 9 during 2023/2024
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid9,@gid10,@y23,@y24,'2024-07-01',@regBy,
  CONCAT(s.first_name,' ',s.last_name,': Grade 9 → 10 (end of 2023/2024)')
FROM students s
WHERE s.current_grade_id=@gid10
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y23 AND sp.from_grade_id=@gid9
  );

-- Currently Grade 11 → were in Grade 10 during 2023/2024
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid10,@gid11,@y23,@y24,'2024-07-01',@regBy,
  CONCAT(s.first_name,' ',s.last_name,': Grade 10 → 11 (end of 2023/2024)')
FROM students s
WHERE s.current_grade_id=@gid11
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y23 AND sp.from_grade_id=@gid10
  );

-- Currently Grade 12 → were in Grade 11 during 2023/2024
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'promoted',@gid11,@gid12,@y23,@y24,'2024-07-01',@regBy,
  CONCAT(s.first_name,' ',s.last_name,': Grade 11 → 12 (end of 2023/2024)')
FROM students s
WHERE s.current_grade_id=@gid12
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y23 AND sp.from_grade_id=@gid11
  );

-- Graduated students (Classes of 2023/24, 2024/25, 2025/26)
-- End-of-year promotions for each graduating cohort
INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'graduated',@gid12,NULL,@y23,NULL,'2024-06-28',@regBy,
  CONCAT(s.first_name,' ',s.last_name,': Graduated (Class of 2023/2024)')
FROM students s
WHERE s.status='Graduated'
  AND s.graduation_date='2024-06-28'
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y23 AND sp.movement_type='graduated'
  );

INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'graduated',@gid12,NULL,@y24,NULL,'2025-06-27',@regBy,
  CONCAT(s.first_name,' ',s.last_name,': Graduated (Class of 2024/2025)')
FROM students s
WHERE s.status='Graduated'
  AND s.graduation_date='2025-06-27'
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y24 AND sp.movement_type='graduated'
  );

INSERT IGNORE INTO student_promotions (student_id,movement_type,from_grade_id,to_grade_id,from_year_id,to_year_id,effective_date,approved_by,notes)
SELECT s.id,'graduated',@gid12,NULL,@y25,NULL,'2026-06-26',@regBy,
  CONCAT(s.first_name,' ',s.last_name,': Graduated (Class of 2025/2026)')
FROM students s
WHERE s.status='Graduated'
  AND s.graduation_date='2026-06-26'
  AND NOT EXISTS (
    SELECT 1 FROM student_promotions sp
    WHERE sp.student_id=s.id AND sp.from_year_id=@y25 AND sp.movement_type='graduated'
  );

-- ============================================================
-- 2. REPORT CARDS for 2023/2024 for all students who were enrolled
-- ============================================================
-- Students currently in Grade 9 → were Grade 8 in 2023/2024
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid8 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  @y23,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(83+RAND()*16,1), ROUND(66+RAND()*27,1),
  ELT(1+FLOOR(RAND()*4),'Excellent','Very Good','Good','Satisfactory'),
  ELT(1+FLOOR(RAND()*4),'Good performance in Grade 8.','Hardworking and consistent.','Shows good potential.','Steady progress throughout the year.'),
  'Promoted to Grade 9. Keep it up!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 998 DAY), DATE_SUB(NOW(),INTERVAL 996 DAY), @appBy
FROM students s
WHERE s.current_grade_id=@gid9
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (SELECT 1 FROM report_cards rc WHERE rc.student_id=s.id AND rc.academic_year_id=@y23);

-- Students currently in Grade 10 → were Grade 9 in 2023/2024
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid9 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  @y23,
  16+FLOOR(RAND()*4), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(83+RAND()*16,1), ROUND(67+RAND()*26,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Strong academic year in Grade 9.','Motivated and focused learner.','Excellent preparation for senior school.'),
  'Promoted to Grade 10.',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 998 DAY), DATE_SUB(NOW(),INTERVAL 996 DAY), @appBy
FROM students s
WHERE s.current_grade_id=@gid10
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (SELECT 1 FROM report_cards rc WHERE rc.student_id=s.id AND rc.academic_year_id=@y23);

-- Students currently in Grade 11 → were Grade 10 in 2023/2024
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid10 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  @y23,
  17+FLOOR(RAND()*3), FLOOR(RAND()*3), FLOOR(RAND()*2),
  ROUND(84+RAND()*15,1), ROUND(68+RAND()*25,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Excellent entry into senior school.','Showed great maturity in Grade 10.','Ready for the challenges ahead.'),
  'Promoted to Grade 11.',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 998 DAY), DATE_SUB(NOW(),INTERVAL 996 DAY), @appBy
FROM students s
WHERE s.current_grade_id=@gid11
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (SELECT 1 FROM report_cards rc WHERE rc.student_id=s.id AND rc.academic_year_id=@y23);

-- Students currently in Grade 12 → were Grade 11 in 2023/2024
INSERT IGNORE INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,conduct,teacher_comment,principal_comment,promotion_status,status,generated_at,published_at,generated_by)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid11 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  @y23,
  17+FLOOR(RAND()*3), FLOOR(RAND()*2), FLOOR(RAND()*2),
  ROUND(85+RAND()*14,1), ROUND(69+RAND()*24,1),
  ELT(1+FLOOR(RAND()*3),'Excellent','Very Good','Good'),
  ELT(1+FLOOR(RAND()*3),'Outstanding Grade 11 performance.','Excellent preparation for final year.','Strong foundations built for Grade 12.'),
  'Promoted to Grade 12. Final year ahead!',
  'Promoted', 'published',
  DATE_SUB(NOW(),INTERVAL 998 DAY), DATE_SUB(NOW(),INTERVAL 996 DAY), @appBy
FROM students s
WHERE s.current_grade_id=@gid12
  AND s.status='Active'
  AND YEAR(s.admission_date)<=2023
  AND NOT EXISTS (SELECT 1 FROM report_cards rc WHERE rc.student_id=s.id AND rc.academic_year_id=@y23);

-- ============================================================
-- 3. ASSESSMENT SCORES for 2023/2024 — fill all missing
-- ============================================================
-- Currently Grade 9 → were Grade 8 in 2023/2024
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid8 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  sub.id, ac.id, @y23,
  ROUND(62+RAND()*33,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 1040 DAY), DATE_SUB(NOW(),INTERVAL 1038 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_grade_id=@gid9 AND s.status='Active' AND YEAR(s.admission_date)<=2023
  AND sub.code IN ('MAT','EGR','HIS','ENG')
  AND ac.academic_year_id=@y23
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y23);

-- Currently Grade 10 → were Grade 9 in 2023/2024
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid9 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  sub.id, ac.id, @y23,
  ROUND(63+RAND()*32,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 1040 DAY), DATE_SUB(NOW(),INTERVAL 1038 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_grade_id=@gid10 AND s.status='Active' AND YEAR(s.admission_date)<=2023
  AND sub.code IN ('MAT','ENG','HIS','BIO')
  AND ac.academic_year_id=@y23
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y23);

-- Currently Grade 11 → were Grade 10 in 2023/2024
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid10 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  sub.id, ac.id, @y23,
  ROUND(64+RAND()*31,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 1040 DAY), DATE_SUB(NOW(),INTERVAL 1038 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_grade_id=@gid11 AND s.status='Active' AND YEAR(s.admission_date)<=2023
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id=@y23
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y23);

-- Currently Grade 12 → were Grade 11 in 2023/2024
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id,
  COALESCE((SELECT c.id FROM classes c WHERE c.grade_id=@gid11 AND c.academic_year_id=@y23 AND c.section='A' LIMIT 1), s.current_class_id),
  sub.id, ac.id, @y23,
  ROUND(65+RAND()*30,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 1040 DAY), DATE_SUB(NOW(),INTERVAL 1038 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.current_grade_id=@gid12 AND s.status='Active' AND YEAR(s.admission_date)<=2023
  AND sub.code IN ('ENG','MAT','BIO','CHM','PHY')
  AND ac.academic_year_id=@y23
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y23);

-- ============================================================
-- 4. CURRENT YEAR 2026/2027 — fill scores for ALL active students
--    Ensure all grades 7-12 have marks for current year
-- ============================================================
-- Grade 7 current
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, s.current_class_id, sub.id, ac.id, @y26,
  ROUND(60+RAND()*35,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 10 DAY), DATE_SUB(NOW(),INTERVAL 8 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active' AND s.current_grade_id=@gid7
  AND sub.code IN ('MAT','EGR','HIS')
  AND ac.academic_year_id=@y26
  AND s.current_class_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y26);

-- Grade 8 current
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, s.current_class_id, sub.id, ac.id, @y26,
  ROUND(62+RAND()*33,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 10 DAY), DATE_SUB(NOW(),INTERVAL 8 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active' AND s.current_grade_id=@gid8
  AND sub.code IN ('MAT','EGR','HIS','ENG')
  AND ac.academic_year_id=@y26
  AND s.current_class_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y26);

-- Grade 9 current
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, s.current_class_id, sub.id, ac.id, @y26,
  ROUND(63+RAND()*32,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 10 DAY), DATE_SUB(NOW(),INTERVAL 8 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active' AND s.current_grade_id=@gid9
  AND sub.code IN ('MAT','ENG','HIS','BIO')
  AND ac.academic_year_id=@y26
  AND s.current_class_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y26);

-- Grade 10 current (already has some — fill gaps)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, s.current_class_id, sub.id, ac.id, @y26,
  ROUND(65+RAND()*30,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 10 DAY), DATE_SUB(NOW(),INTERVAL 8 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active' AND s.current_grade_id=@gid10
  AND sub.code IN ('MAT','ENG','BIO','CHM','HIS')
  AND ac.academic_year_id=@y26
  AND s.current_class_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y26);

-- Grade 11 current
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, s.current_class_id, sub.id, ac.id, @y26,
  ROUND(66+RAND()*29,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 10 DAY), DATE_SUB(NOW(),INTERVAL 8 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active' AND s.current_grade_id=@gid11
  AND sub.code IN ('ENG','MAT','BIO','CHM','PHY')
  AND ac.academic_year_id=@y26
  AND s.current_class_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y26);

-- Grade 12 current (graduating year)
INSERT IGNORE INTO assessment_scores (student_id,class_id,subject_id,assessment_config_id,academic_year_id,marks_obtained,max_marks,entered_by,submitted_at,approved_at,approved_by,status)
SELECT s.id, s.current_class_id, sub.id, ac.id, @y26,
  ROUND(68+RAND()*28,1), 100.00,
  @entBy, DATE_SUB(NOW(),INTERVAL 10 DAY), DATE_SUB(NOW(),INTERVAL 8 DAY), @appBy, 'approved'
FROM students s
CROSS JOIN subjects sub
CROSS JOIN assessment_configs ac
WHERE s.status='Active' AND s.current_grade_id=@gid12
  AND sub.code IN ('ENG','MAT','BIO','CHM','PHY')
  AND ac.academic_year_id=@y26
  AND s.current_class_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM assessment_scores ex WHERE ex.student_id=s.id AND ex.subject_id=sub.id AND ex.assessment_config_id=ac.id AND ex.academic_year_id=@y26);

-- ============================================================
-- 5. ANNUAL RESULTS — rebuild for all years
-- ============================================================
INSERT IGNORE INTO annual_results (student_id,class_id,subject_id,academic_year_id,sem1_average,sem2_average,yearly_average,grade_letter,passed,status)
SELECT
  a.student_id, a.class_id, a.subject_id, a.academic_year_id,
  ROUND(AVG(CASE WHEN ac.sequence<=4 THEN a.marks_obtained/a.max_marks*100 ELSE NULL END),1),
  ROUND(AVG(CASE WHEN ac.sequence>4  THEN a.marks_obtained/a.max_marks*100 ELSE NULL END),1),
  ROUND(AVG(a.marks_obtained/a.max_marks*100),1),
  CASE
    WHEN AVG(a.marks_obtained/a.max_marks*100)>=90 THEN 'A'
    WHEN AVG(a.marks_obtained/a.max_marks*100)>=80 THEN 'B'
    WHEN AVG(a.marks_obtained/a.max_marks*100)>=70 THEN 'C'
    WHEN AVG(a.marks_obtained/a.max_marks*100)>=60 THEN 'D'
    ELSE 'F'
  END,
  IF(AVG(a.marks_obtained/a.max_marks*100)>=70,1,0),
  'published'
FROM assessment_scores a
JOIN assessment_configs ac ON ac.id=a.assessment_config_id
WHERE a.status='approved' AND a.max_marks>0
GROUP BY a.student_id, a.class_id, a.subject_id, a.academic_year_id
ON DUPLICATE KEY UPDATE
  yearly_average=VALUES(yearly_average),
  grade_letter=VALUES(grade_letter),
  passed=VALUES(passed),
  status='published';

-- Update class positions
UPDATE annual_results ar
JOIN (
  SELECT id, ROW_NUMBER() OVER (PARTITION BY academic_year_id,class_id ORDER BY yearly_average DESC) rn
  FROM annual_results WHERE status='published'
) ranked ON ranked.id=ar.id
SET ar.class_position=ranked.rn;

-- ============================================================
-- 6. UPDATE REPORT CARD yearly_average from actual scores
-- ============================================================
UPDATE report_cards rc
JOIN (
  SELECT student_id, academic_year_id,
    ROUND(AVG(yearly_average),1) avg
  FROM annual_results WHERE status='published'
  GROUP BY student_id, academic_year_id
) avg_data ON avg_data.student_id=rc.student_id
  AND avg_data.academic_year_id=rc.academic_year_id
SET rc.yearly_average=avg_data.avg
WHERE rc.status='published' AND (rc.yearly_average IS NULL OR rc.yearly_average=0);

SET FOREIGN_KEY_CHECKS = 1;

-- ── Final check ───────────────────────────────────────────────
SELECT 'PROMOTIONS BY YEAR (from_year_id):' AS '';
SELECT ay.name year, from_grade_id, to_grade_id, COUNT(*) n
FROM student_promotions sp
JOIN academic_years ay ON ay.id=sp.from_year_id
GROUP BY ay.id, sp.from_grade_id, sp.to_grade_id
ORDER BY ay.name, sp.from_grade_id;

SELECT 'REPORT CARDS BY YEAR:' AS '';
SELECT ay.name year, COUNT(*) cards
FROM report_cards rc JOIN academic_years ay ON ay.id=rc.academic_year_id
WHERE rc.status='published'
GROUP BY ay.id ORDER BY ay.name;

SELECT 'SCORES BY YEAR (approved):' AS '';
SELECT ay.name year, COUNT(*) scores
FROM assessment_scores a JOIN academic_years ay ON ay.id=a.academic_year_id
WHERE a.status='approved'
GROUP BY ay.id ORDER BY ay.name;

SELECT 'ANNUAL RESULTS BY YEAR:' AS '';
SELECT ay.name year, COUNT(*) row_count
FROM annual_results ar JOIN academic_years ay ON ay.id=ar.academic_year_id
WHERE ar.status='published'
GROUP BY ay.id ORDER BY ay.name;
