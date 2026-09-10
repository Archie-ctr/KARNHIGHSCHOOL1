USE karnhighschool;

UPDATE teachers t JOIN users u ON u.email='aharris@karnhighschool.edu.lr'
SET t.user_id=u.id, t.email='aharris@karnhighschool.edu.lr'
WHERE t.teacher_id='TCH-005';

UPDATE teachers t JOIN users u ON u.email='psumo@karnhighschool.edu.lr'
SET t.user_id=u.id, t.email='psumo@karnhighschool.edu.lr'
WHERE t.teacher_id='TCH-007';

UPDATE teachers t JOIN users u ON u.email='dcooper@karnhighschool.edu.lr'
SET t.user_id=u.id, t.email='dcooper@karnhighschool.edu.lr'
WHERE t.teacher_id='TCH-008';

UPDATE users u JOIN roles r ON r.id=u.role_id
SET u.role=r.name, u.status='Active' WHERE u.role IS NULL OR u.role='';

UPDATE users SET username=SUBSTRING_INDEX(email,'@',1)
WHERE username IS NULL AND email IS NOT NULL;

SELECT teacher_id, CONCAT(first_name,' ',last_name) name, email,
       IF(user_id IS NOT NULL,'✓ Linked','✗ Missing') linked
FROM teachers ORDER BY teacher_id;

SELECT 'STUDENTS WITH PORTAL ACCESS:' info;
SELECT SUM(user_id IS NOT NULL) linked, COUNT(*) total FROM students WHERE status='Active';
