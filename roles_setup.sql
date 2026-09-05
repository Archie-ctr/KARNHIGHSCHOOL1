-- ============================================================
-- KHSMIS — Complete Granular RBAC Setup
-- 17 Roles · 110 Permissions · Full Approval Matrix
-- ============================================================
USE karnhighschool;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Clear ─────────────────────────────────────────────────────
TRUNCATE TABLE role_permissions;
TRUNCATE TABLE permissions;
TRUNCATE TABLE roles;

-- ============================================================
-- 1. ROLES (17 — 4 removed: academic_coord, exam_officer, dept_head, counselor)
-- ============================================================
INSERT INTO roles (id, name, label, description) VALUES
-- Administration
(1,  'sys_admin',           'System Administrator',    'Full system control. Manages all settings, users, data and security.'),
(2,  'school_admin',        'School Administrator',    'Manages overall school operations and day-to-day configuration.'),
(3,  'principal',           'Principal',               'Senior academic/administrative authority. Final approver on most workflows.'),
(4,  'vice_principal',      'Vice Principal',          'Assists principal with academic/student management. First-level approver.'),
(5,  'registrar',           'Registrar',               'Manages student registration, admissions, and academic records.'),
-- Finance
(6,  'accountant',          'Accountant / Bursar',     'Manages fees, payments, receipts, and financial records.'),
(7,  'finance_officer',     'Finance Officer',         'Assists accountant with financial transactions and reports.'),
-- Academic / Teaching
(8,  'teacher',             'Teacher',                 'Manages assigned classes, subjects, attendance, marks, and assessments.'),
(9,  'class_teacher',       'Class Teacher',           'Responsible for a specific class: attendance, discipline, communication.'),
-- Student Services
(10, 'discipline_officer',  'Discipline Officer',      'Manages disciplinary incidents, investigations, and recommendations.'),
(11, 'librarian',           'Librarian',               'Manages books, borrowing, returns, and library records.'),
-- Support
(12, 'ict_officer',         'ICT Officer',             'Manages technology resources and system support.'),
-- Users
(13, 'student',             'Student',                 'Views own academic, attendance, fee, and announcement information.'),
(14, 'parent',              'Parent / Guardian',       'Views their child''s academic, attendance, and fee information.'),
-- Legacy compat aliases
(15, 'academic_dean',       'Academic Dean',           'Legacy alias — maps to Vice Principal.'),
(16, 'super_admin',         'Super Admin',             'Legacy alias — maps to System Administrator.'),
(17, 'vice_principal_alt',  'VP (alt)',                'Legacy alias.');

-- ============================================================
-- 2. PERMISSIONS (110 granular: module.action)
-- ============================================================
INSERT INTO permissions (id, name, label, module) VALUES
(1,  'dashboard.view',               'View Dashboard',                   'dashboard'),
(10, 'students.view',                'View Students',                    'students'),
(11, 'students.create',              'Create Student',                   'students'),
(12, 'students.update',              'Update Student',                   'students'),
(13, 'students.delete',              'Delete Student',                   'students'),
(14, 'students.export',              'Export Students',                  'students'),
(15, 'students.archive',             'Archive Student (Soft Delete)',    'students'),
(16, 'students.view_own',            'View Own Profile (Student)',       'students'),
(17, 'students.manage_documents',    'Manage Student Documents',         'students'),
(18, 'students.verify_documents',    'Verify Student Documents',         'students'),
(19, 'students.manage_guardians',    'Manage Guardians',                 'students'),
(20, 'admissions.view',              'View Applications',                'admissions'),
(21, 'admissions.create',            'Create Application',               'admissions'),
(22, 'admissions.update',            'Update Application',               'admissions'),
(23, 'admissions.verify',            'Verify Documents',                 'admissions'),
(24, 'admissions.recommend',         'Recommend Admission',              'admissions'),
(25, 'admissions.approve',           'Approve Admission (Final)',        'admissions'),
(26, 'admissions.reject',            'Reject Application',               'admissions'),
(27, 'admissions.generate_letter',   'Generate Letters',                 'admissions'),
(28, 'admissions.manage_entrance',   'Manage Entrance Exams',            'admissions'),
(29, 'admissions.export',            'Export Applications',              'admissions'),
(30, 'academics.view',               'View Academic Structure',          'academics'),
(31, 'academics.manage_years',       'Manage Academic Years',            'academics'),
(32, 'academics.manage_classes',     'Manage Classes',                   'academics'),
(33, 'academics.manage_subjects',    'Manage Subjects',                  'academics'),
(34, 'academics.manage_timetable',   'Manage Timetable',                 'academics'),
(35, 'academics.view_timetable',     'View Timetable',                   'academics'),
(36, 'academics.assign_teachers',    'Assign Teachers to Classes',       'academics'),
(40, 'marks.view',                   'View Marks',                       'marks'),
(41, 'marks.create',                 'Enter Marks (Draft)',               'marks'),
(42, 'marks.update',                 'Edit Draft Marks',                  'marks'),
(43, 'marks.delete',                 'Delete Draft Marks',                'marks'),
(44, 'marks.submit',                 'Submit Marks for Review',           'marks'),
(45, 'marks.review',                 'Review Submitted Marks',            'marks'),
(46, 'marks.approve',                'Approve Marks',                     'marks'),
(47, 'marks.return',                 'Return Marks for Correction',       'marks'),
(48, 'marks.reject',                 'Reject Marks',                      'marks'),
(49, 'marks.lock',                   'Lock Approved Marks',               'marks'),
(50, 'marks.unlock',                 'Unlock Locked Marks',               'marks'),
(51, 'marks.publish',                'Publish Marks',                     'marks'),
(52, 'marks.export',                 'Export Mark Sheets',                'marks'),
(53, 'marks.view_own',               'View Own Results (Student)',        'marks'),
(54, 'marks.history',                'View Marks Version History',        'marks'),
(55, 'reportcards.view',             'View Report Cards',                'reportcards'),
(56, 'reportcards.generate',         'Generate Report Cards',            'reportcards'),
(57, 'reportcards.comment',          'Add Comments to Report Card',      'reportcards'),
(58, 'reportcards.approve',          'Approve Report Cards',             'reportcards'),
(59, 'reportcards.publish',          'Publish Report Cards',             'reportcards'),
(60, 'reportcards.view_own',         'View Own Report Card (Student)',   'reportcards'),
(61, 'reportcards.print',            'Print/Download Report Cards',      'reportcards'),
(62, 'attendance.view',              'View Attendance',                  'attendance'),
(63, 'attendance.take',              'Take Attendance',                  'attendance'),
(64, 'attendance.submit',            'Submit Attendance',                'attendance'),
(65, 'attendance.correct',           'Request Attendance Correction',    'attendance'),
(66, 'attendance.approve_correction','Approve Attendance Correction',    'attendance'),
(67, 'attendance.lock',              'Lock Attendance',                  'attendance'),
(68, 'attendance.export',            'Export Attendance',                'attendance'),
(69, 'attendance.view_own',          'View Own Attendance (Student)',    'attendance'),
(70, 'exams.view',                   'View Examinations',                'exams'),
(71, 'exams.create',                 'Create Examination',               'exams'),
(72, 'exams.update',                 'Update Examination',               'exams'),
(73, 'exams.publish',                'Publish Examination',              'exams'),
(74, 'exams.approve',                'Approve Examination',              'exams'),
(75, 'promotion.view',               'View Promotion',                   'promotion'),
(76, 'promotion.recommend',          'Recommend Promotion',              'promotion'),
(77, 'promotion.approve',            'Approve Promotion',                'promotion'),
(78, 'promotion.execute',            'Execute Promotion (Bulk)',         'promotion'),
(80, 'finance.view',                 'View Finance',                     'finance'),
(81, 'finance.create_payment',       'Record Payment',                   'finance'),
(82, 'finance.update_payment',       'Update Payment',                   'finance'),
(83, 'finance.void_payment',         'Void/Reverse Payment',             'finance'),
(84, 'finance.manage_fees',          'Manage Fee Structures',            'finance'),
(85, 'finance.approve_void',         'Approve Payment Void/Reversal',    'finance'),
(86, 'finance.generate_receipt',     'Generate Receipt',                 'finance'),
(87, 'finance.export',               'Export Financial Reports',         'finance'),
(88, 'finance.view_own',             'View Own Fees (Student)',          'finance'),
(90, 'discipline.view',              'View Discipline Records',          'discipline'),
(91, 'discipline.create',            'Create Discipline Incident',       'discipline'),
(92, 'discipline.update',            'Update Discipline Record',         'discipline'),
(93, 'discipline.recommend_action',  'Recommend Disciplinary Action',    'discipline'),
(94, 'discipline.approve',           'Approve Disciplinary Action',      'discipline'),
(95, 'discipline.resolve',           'Resolve Discipline Case',          'discipline'),
(96, 'discipline.delete',            'Delete Discipline Record',         'discipline'),
(100,'library.view',                 'View Library',                     'library'),
(101,'library.manage_books',         'Add/Edit/Remove Books',            'library'),
(102,'library.issue',                'Issue Books',                      'library'),
(103,'library.return',               'Return Books',                     'library'),
(104,'library.export',               'Export Library Reports',           'library'),
(110,'comms.view_announcements',     'View Announcements',               'communications'),
(111,'comms.create_announcement',    'Create Announcement',              'communications'),
(112,'comms.publish_announcement',   'Publish Announcement',             'communications'),
(113,'comms.manage_events',          'Manage Events',                    'communications'),
(120,'teachers.view',                'View Teachers',                    'teachers'),
(121,'teachers.create',              'Add Teacher',                      'teachers'),
(122,'teachers.update',              'Update Teacher',                   'teachers'),
(123,'teachers.delete',              'Remove Teacher',                   'teachers'),
(124,'teachers.assign',              'Assign Teacher to Class/Subject',  'teachers'),
(130,'users.view',                   'View Users',                       'system'),
(131,'users.create',                 'Create User',                      'system'),
(132,'users.update',                 'Update User',                      'system'),
(133,'users.delete',                 'Delete User',                      'system'),
(134,'users.reset_password',         'Reset User Password',              'system'),
(135,'roles.manage',                 'Manage Roles & Permissions',       'system'),
(136,'system.audit_logs',            'View Audit Logs',                  'system'),
(137,'system.settings',              'Manage System Settings',           'system'),
(138,'system.backup',                'Backup & Restore',                 'system'),
(139,'reports.view',                 'View Reports',                     'system'),
(140,'reports.export',               'Export Reports',                   'system'),
(150,'approvals.view',               'View Approval Center',             'approvals'),
(151,'approvals.act',                'Act on Approvals',                 'approvals');

-- ============================================================
-- 3. ROLE PERMISSIONS
-- ============================================================

-- sys_admin (1): ALL
INSERT INTO role_permissions (role_id, permission_id) SELECT 1, id FROM permissions;

-- school_admin (2): All except delete user / backup
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE id NOT IN (133,135,136,137,138);

-- principal (3): All except system config
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE id NOT IN (133,135,136,137,138);

-- vice_principal (4): Academic + approver
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE id IN (
  1, 10,12,14,16,17,18,19,
  20,22,23,24,27,29,
  30,31,32,33,34,35,36,
  40,41,42,44,45,46,47,48,49,51,52,54,
  55,56,57,58,59,61,
  62,63,64,65,66,67,68,
  70,71,72,73,74,
  75,76,77,
  80,87,
  90,91,92,93,94,95,
  100,
  110,111,112,113,
  120,121,122,124,
  130, 139,140,
  150,151
);

-- registrar (5): Admissions + student records
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE id IN (
  1, 10,11,12,14,15,17,18,19,
  20,21,22,23,24,27,28,29,
  30,35,
  55,61,
  62,68,
  75,76,
  110, 120,
  139,140
);

-- accountant (6): Finance only + view students
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE id IN (
  1, 10,14,
  80,81,82,83,84,85,86,87,
  110, 139,140
);

-- finance_officer (7): Basic payments + receipts
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE id IN (
  1, 10, 80,81,86, 110
);

-- teacher (8): Own classes only
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions WHERE id IN (
  1, 10,16, 35,
  40,41,42,43,44,53,
  55,60,61,
  62,63,64,65,69,
  70, 100, 110, 120
);

-- class_teacher (9): Teacher + class management + discipline view
INSERT INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions WHERE id IN (
  1, 10,12,16,17,
  35,
  40,41,42,43,44,53,54,
  55,57,60,61,
  62,63,64,65,66,69,
  70, 90,91,92,93,
  100, 110,111, 120
);

-- discipline_officer (10)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 10, id FROM permissions WHERE id IN (
  1, 10,16,
  90,91,92,93,94,95,
  110, 120
);

-- librarian (11)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 11, id FROM permissions WHERE id IN (
  1, 10,
  100,101,102,103,104,
  110
);

-- ict_officer (12)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 12, id FROM permissions WHERE id IN (
  1, 130,131,132,134,136,137,138, 110
);

-- student (13): Own data only
INSERT INTO role_permissions (role_id, permission_id)
SELECT 13, id FROM permissions WHERE id IN (
  1, 16,35,53,60,69,88,100,110
);

-- parent (14): Own child data
INSERT INTO role_permissions (role_id, permission_id)
SELECT 14, id FROM permissions WHERE id IN (
  1, 16,35,53,60,61,69,88,100,110
);

-- Legacy aliases: same as their equivalent
INSERT INTO role_permissions (role_id, permission_id)
SELECT 15, permission_id FROM role_permissions WHERE role_id=4; -- academic_dean = vice_principal
INSERT INTO role_permissions (role_id, permission_id)
SELECT 16, permission_id FROM role_permissions WHERE role_id=1; -- super_admin = sys_admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT 17, permission_id FROM role_permissions WHERE role_id=4; -- vice_principal_alt = vice_principal

-- ============================================================
-- 4. USERS — reassign then insert
-- ============================================================
UPDATE users SET role_id=1  WHERE email='admin@karnhighschool.edu.lr';
UPDATE users SET role_id=1  WHERE email='sysadmin@karnhighschool.edu.lr';
UPDATE users SET role_id=2  WHERE email='schooladmin@karnhighschool.edu.lr';
UPDATE users SET role_id=3  WHERE email='principal@karnhighschool.edu.lr';
UPDATE users SET role_id=4  WHERE email='vp@karnhighschool.edu.lr';
UPDATE users SET role_id=5  WHERE email='registrar@karnhighschool.edu.lr';
UPDATE users SET role_id=6  WHERE email='accountant@karnhighschool.edu.lr';
UPDATE users SET role_id=7  WHERE email='finance@karnhighschool.edu.lr';
UPDATE users SET role_id=8  WHERE email='teacher@karnhighschool.edu.lr';
UPDATE users SET role_id=9  WHERE email='classteacher@karnhighschool.edu.lr';
UPDATE users SET role_id=10 WHERE email='discipline@karnhighschool.edu.lr';
UPDATE users SET role_id=11 WHERE email='librarian@karnhighschool.edu.lr';
UPDATE users SET role_id=12 WHERE email='ict@karnhighschool.edu.lr';
UPDATE users SET role_id=13 WHERE email='student@karnhighschool.edu.lr';
UPDATE users SET role_id=14 WHERE email='parent@karnhighschool.edu.lr';

-- Insert new default users (password: 1234)
INSERT IGNORE INTO users (name, email, phone, password_hash, role_id) VALUES
('System Admin',       'sysadmin@karnhighschool.edu.lr',      '+231 880 001 001','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',1),
('School Admin',       'schooladmin@karnhighschool.edu.lr',   '+231 880 001 002','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',2),
('John Cooper',        'principal@karnhighschool.edu.lr',     '+231 880 001 003','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',3),
('Grace Flomo',        'vp@karnhighschool.edu.lr',            '+231 880 001 004','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',4),
('Mary Kollie',        'registrar@karnhighschool.edu.lr',     '+231 880 001 005','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',5),
('Moses Johnson',      'accountant@karnhighschool.edu.lr',    '+231 880 001 006','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',6),
('Ruth Freeman',       'finance@karnhighschool.edu.lr',       '+231 880 001 007','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',7),
('Sarah Williams',     'teacher@karnhighschool.edu.lr',       '+231 880 001 008','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',8),
('Robert Brown',       'classteacher@karnhighschool.edu.lr',  '+231 880 001 009','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',9),
('James Doe',          'discipline@karnhighschool.edu.lr',    '+231 880 001 010','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',10),
('Emmanuel Konneh',    'librarian@karnhighschool.edu.lr',     '+231 880 001 011','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',11),
('Thomas Williams',    'ict@karnhighschool.edu.lr',           '+231 880 001 012','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',12),
('Demo Student',       'student@karnhighschool.edu.lr',       '+231 880 001 013','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',13),
('Demo Parent',        'parent@karnhighschool.edu.lr',        '+231 880 001 014','$2y$10$qRHPA8KlN8ZV4wTgUV/TneiFgtXGEaiCD7uijgAK0vP7fYZNNKNPC',14);

-- ============================================================
-- 5. SYNC admin_users COMPAT TABLE
-- ============================================================
DELETE FROM admin_users;
INSERT INTO admin_users (name, email, password_hash, role)
SELECT u.name, u.email, u.password_hash, r.name
FROM users u JOIN roles r ON r.id=u.role_id
WHERE r.name NOT IN ('student','parent');

SET FOREIGN_KEY_CHECKS = 1;

-- Verification
SELECT r.label AS Role, u.email AS Email,
       (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id=r.id) AS Permissions
FROM users u JOIN roles r ON r.id=u.role_id ORDER BY r.id, u.email;
