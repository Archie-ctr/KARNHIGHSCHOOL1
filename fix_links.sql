-- ============================================================
-- FIX ALL USER → RECORD LINKS
-- Links teachers, students, staff, guardians to their user accounts
-- Also adds total_marks to teacher_quizzes and seeds sample quizzes
-- ============================================================
USE karnhighschool;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. LINK TEACHERS to their user accounts by email
-- ============================================================
UPDATE teachers t
JOIN users u ON u.email = t.email
SET t.user_id = u.id
WHERE t.user_id IS NULL AND t.email IS NOT NULL AND t.email != '';

-- Also match by name if email didn't match
UPDATE teachers t
JOIN users u ON u.name = CONCAT(t.first_name,' ',t.last_name)
  AND u.role_id = (SELECT id FROM roles WHERE name='teacher' LIMIT 1)
SET t.user_id = u.id
WHERE t.user_id IS NULL;

-- Match class_teacher role too
UPDATE teachers t
JOIN users u ON u.name = CONCAT(t.first_name,' ',t.last_name)
  AND u.role_id = (SELECT id FROM roles WHERE name='class_teacher' LIMIT 1)
SET t.user_id = u.id
WHERE t.user_id IS NULL;

-- ============================================================
-- 2. LINK STUDENTS to their user accounts by student_id email pattern
-- ============================================================
UPDATE students s
JOIN users u ON u.email = LOWER(CONCAT(s.student_id,'@student.karnhighschool.edu.lr'))
SET s.user_id = u.id
WHERE s.user_id IS NULL;

-- Also link by phone
UPDATE students s
JOIN users u ON u.phone = s.phone AND u.role_id=(SELECT id FROM roles WHERE name='student' LIMIT 1)
SET s.user_id = u.id
WHERE s.user_id IS NULL AND s.phone IS NOT NULL;

-- Link demo student
UPDATE students s
JOIN users u ON u.email = 'student@karnhighschool.edu.lr'
SET s.user_id = u.id
WHERE s.student_id IN ('KHS-2024-0184','KHS-2026-1031')
  AND s.user_id IS NULL;

-- ============================================================
-- 3. LINK STAFF to their user accounts by email
-- ============================================================
UPDATE staff st
JOIN users u ON u.email = st.email
SET st.user_id = u.id
WHERE st.user_id IS NULL AND st.email IS NOT NULL;

-- ============================================================
-- 4. LINK GUARDIANS (parents) to their user accounts by phone
-- ============================================================
UPDATE guardians g
JOIN users u ON u.phone = g.phone
  AND u.role_id = (SELECT id FROM roles WHERE name='parent' LIMIT 1)
SET g.user_id = u.id
WHERE g.user_id IS NULL AND g.phone IS NOT NULL;

-- Also match demo parent by email
UPDATE guardians g
JOIN users u ON u.email = 'parent@karnhighschool.edu.lr'
SET g.user_id = u.id
WHERE g.first_name='Demo' AND g.user_id IS NULL;

-- ============================================================
-- 5. ADD total_marks COLUMN to teacher_quizzes (for quiz player)
-- ============================================================
ALTER TABLE teacher_quizzes
  ADD COLUMN IF NOT EXISTS total_marks DECIMAL(8,2) NULL COMMENT 'Computed from sum of question marks';

-- Recalculate total_marks from existing questions
UPDATE teacher_quizzes tq
SET total_marks = (
  SELECT COALESCE(SUM(marks),0) FROM quiz_questions WHERE quiz_id=tq.id
)
WHERE total_marks IS NULL OR total_marks=0;

-- Auto-update via trigger concept: we'll update in PHP on question add/delete instead.
-- For now just ensure column exists and is populated.

-- ============================================================
-- 6. ADD in_progress_responses COLUMN to quiz_attempts
--    (for auto-save mid-quiz)
-- ============================================================
ALTER TABLE quiz_attempts
  ADD COLUMN IF NOT EXISTS in_progress TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS started_at  DATETIME   NULL,
  ADD COLUMN IF NOT EXISTS time_spent  INT        NULL COMMENT 'seconds spent';

-- Set started_at for existing attempts
UPDATE quiz_attempts SET started_at=submitted_at WHERE started_at IS NULL AND submitted_at IS NOT NULL;

-- ============================================================
-- 7. SEED SAMPLE QUIZZES with real questions
--    for teachers who have class assignments
-- ============================================================
SET @ayId = (SELECT id FROM academic_years WHERE is_current=1 LIMIT 1);
SET @tch1 = (SELECT id FROM teachers WHERE teacher_id='TCH-001' LIMIT 1); -- Sarah Williams (Math)
SET @tch2 = (SELECT id FROM teachers WHERE teacher_id='TCH-002' LIMIT 1); -- Robert Brown (English)
SET @tch3 = (SELECT id FROM teachers WHERE teacher_id='TCH-003' LIMIT 1); -- Grace Flomo (Biology)
SET @cls10a = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.academic_year_id=@ayId AND c.section='A' LIMIT 1);
SET @cls11a = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 11' AND c.academic_year_id=@ayId AND c.section='A' LIMIT 1);
SET @cls12a = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 12' AND c.academic_year_id=@ayId AND c.section='A' LIMIT 1);
SET @subMAT = (SELECT id FROM subjects WHERE code='MAT' LIMIT 1);
SET @subENG = (SELECT id FROM subjects WHERE code='ENG' LIMIT 1);
SET @subBIO = (SELECT id FROM subjects WHERE code='BIO' LIMIT 1);

-- Quiz 1: Mathematics — Grade 10
INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,is_published,total_marks)
VALUES (@tch1,@cls10a,@subMAT,@ayId,
  'Mathematics — Chapter 3: Algebra Basics',
  'This quiz covers basic algebra: equations, expressions and simplification. Read each question carefully.',
  20, 2, 1, 10.00);
SET @qz1 = (SELECT LAST_INSERT_ID());

-- Questions for Quiz 1 (Maths MCQ + True/False)
INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence) VALUES
(@qz1,'What is the value of x if 2x + 4 = 12?','mcq','["x = 2","x = 4","x = 6","x = 8"]','B',2,1),
(@qz1,'Simplify: 3a + 2b - a + 4b','mcq','["2a + 6b","4a + 2b","2a + 2b","4a + 6b"]','A',2,2),
(@qz1,'If y = 3x - 1, what is y when x = 5?','mcq','["12","14","15","16"]','B',2,3),
(@qz1,'The expression 4(x + 3) is equivalent to 4x + 12.','true_false','["True","False"]','True',2,4),
(@qz1,'What is the coefficient of x in the expression 7x + 5?','mcq','["5","7","12","35"]','B',2,5);

-- Update total_marks
UPDATE teacher_quizzes SET total_marks=10 WHERE id=@qz1;

-- Quiz 2: English Language — Grade 10
INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,is_published,total_marks)
VALUES (@tch2,@cls10a,@subENG,@ayId,
  'English — Grammar: Tenses & Sentence Structure',
  'Test your knowledge of English grammar. Choose the best answer for each question.',
  15, 3, 1, 8.00);
SET @qz2 = (SELECT LAST_INSERT_ID());

INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence) VALUES
(@qz2,'Which sentence is in the past tense?','mcq','["She runs every morning.","She ran yesterday.","She will run tomorrow.","She is running now."]','B',2,1),
(@qz2,'Choose the correct article: ___ elephant is a large animal.','mcq','["A","An","The","No article"]','B',2,2),
(@qz2,'A verb that shows action is called a transitive verb.','true_false','["True","False"]','False',2,3),
(@qz2,'Which word is a conjunction?','mcq','["Quickly","Beautiful","Because","School"]','C',2,4);

UPDATE teacher_quizzes SET total_marks=8 WHERE id=@qz2;

-- Quiz 3: Biology — Grade 11
INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,is_published,total_marks)
VALUES (@tch3,@cls11a,@subBIO,@ayId,
  'Biology — Cell Structure and Function',
  'This quiz tests your understanding of cell biology. Take your time and read carefully.',
  25, 2, 1, 12.00);
SET @qz3 = (SELECT LAST_INSERT_ID());

INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence) VALUES
(@qz3,'What is the powerhouse of the cell?','mcq','["Nucleus","Ribosome","Mitochondria","Cell wall"]','C',2,1),
(@qz3,'Which organelle is responsible for protein synthesis?','mcq','["Mitochondria","Golgi apparatus","Ribosome","Vacuole"]','C',2,2),
(@qz3,'Plant cells have a cell wall but animal cells do not.','true_false','["True","False"]','True',2,3),
(@qz3,'The process by which plants make food using sunlight is called:','mcq','["Respiration","Photosynthesis","Digestion","Osmosis"]','B',2,4),
(@qz3,'DNA is found mainly in the:','mcq','["Cytoplasm","Cell membrane","Nucleus","Vacuole"]','C',2,5),
(@qz3,'Which of the following is NOT found in animal cells?','mcq','["Mitochondria","Cell wall","Ribosome","Nucleus"]','B',2,6);

UPDATE teacher_quizzes SET total_marks=12 WHERE id=@qz3;

-- Quiz 4: Mathematics — Grade 12 (harder)
INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,is_published,total_marks)
VALUES (@tch1,@cls12a,@subMAT,@ayId,
  'Mathematics — Quadratic Equations',
  'Advanced algebra quiz on quadratic equations and their solutions.',
  30, 2, 1, 10.00);
SET @qz4 = (SELECT LAST_INSERT_ID());

INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence) VALUES
(@qz4,'Solve: x² - 5x + 6 = 0. What are the roots?','mcq','["x=1, x=6","x=2, x=3","x=-2, x=-3","x=1, x=5"]','B',2,1),
(@qz4,'The discriminant of ax²+bx+c=0 is given by:','mcq','["b²-4ac","b²+4ac","4ac-b²","-b²-4ac"]','A',2,2),
(@qz4,'A quadratic equation always has exactly two real roots.','true_false','["True","False"]','False',2,3),
(@qz4,'If x² = 16, then x equals:','mcq','["4 only","-4 only","±4","±8"]','C',2,4),
(@qz4,'The sum of roots of 2x² - 8x + 6 = 0 is:','mcq','["4","3","6","8"]','A',2,5);

UPDATE teacher_quizzes SET total_marks=10 WHERE id=@qz4;

-- Also seed quizzes for other classes so B-section students can take them
-- Duplicate quiz 1 for Grade 10B if it exists
SET @cls10b = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 10' AND c.academic_year_id=@ayId AND c.section='B' LIMIT 1);
INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,is_published,total_marks)
SELECT @tch1,@cls10b,@subMAT,@ayId,title,description,time_limit_mins,max_attempts,is_published,total_marks
FROM teacher_quizzes WHERE id=@qz1 AND @cls10b IS NOT NULL;

SET @qz5 = (SELECT LAST_INSERT_ID());
INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence)
SELECT @qz5,question,type,options,answer,marks,sequence FROM quiz_questions WHERE quiz_id=@qz1 AND @cls10b IS NOT NULL;

-- Quiz for Grade 8 and 9 (simpler)
SET @cls9a = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 9' AND c.academic_year_id=@ayId AND c.section='A' LIMIT 1);
SET @cls8a = (SELECT c.id FROM classes c JOIN grades g ON g.id=c.grade_id WHERE g.name='Grade 8' AND c.academic_year_id=@ayId AND c.section='A' LIMIT 1);

INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,is_published,total_marks)
VALUES (@tch1,@cls9a,@subMAT,@ayId,
  'Mathematics — Basic Number Operations',
  'A short quiz on addition, subtraction, multiplication and division.',
  15, 3, 1, 8.00);
SET @qz6 = (SELECT LAST_INSERT_ID());

INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence) VALUES
(@qz6,'What is 15 × 8?','mcq','["100","110","120","130"]','C',2,1),
(@qz6,'What is 144 ÷ 12?','mcq','["10","11","12","13"]','C',2,2),
(@qz6,'The square root of 81 is 9.','true_false','["True","False"]','True',2,3),
(@qz6,'What is the value of 2³?','mcq','["4","6","8","9"]','C',2,2);

UPDATE teacher_quizzes SET total_marks=8 WHERE id=@qz6;

-- Grade 8 quiz
INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,is_published,total_marks)
VALUES (@tch2,@cls8a,@subENG,@ayId,
  'English — Vocabulary and Spelling',
  'Test your English vocabulary and spelling knowledge.',
  10, 3, 1, 6.00);
SET @qz7 = (SELECT LAST_INSERT_ID());

INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence) VALUES
(@qz7,'Choose the correct spelling:','mcq','["Recieve","Receive","Receve","Reciev"]','B',2,1),
(@qz7,'What does the word "benevolent" mean?','mcq','["Angry","Kind and generous","Tired","Lazy"]','B',2,2),
(@qz7,'The opposite of "ancient" is "modern".','true_false','["True","False"]','True',2,3);

UPDATE teacher_quizzes SET total_marks=6 WHERE id=@qz7;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Verification ─────────────────────────────────────────────
SELECT 'TEACHER LINKS:' AS info;
SELECT t.teacher_id, CONCAT(t.first_name,' ',t.last_name) name, u.email, u.role_id IS NOT NULL linked
FROM teachers t LEFT JOIN users u ON u.id=t.user_id
ORDER BY t.teacher_id;

SELECT 'STUDENT LINK SAMPLE (first 10):' AS info;
SELECT s.student_id, CONCAT(s.first_name,' ',s.last_name) name, s.user_id IS NOT NULL linked
FROM students s WHERE s.status='Active' ORDER BY s.student_id LIMIT 10;

SELECT 'QUIZZES SEEDED:' AS info;
SELECT tq.id, tq.title, c.name class_name, tq.is_published pub, tq.total_marks,
  (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=tq.id) questions
FROM teacher_quizzes tq JOIN classes c ON c.id=tq.class_id
ORDER BY tq.id;
