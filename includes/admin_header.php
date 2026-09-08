<?php
// ── Admin panel header ─────────────────────────────────────────
if (!ob_get_level()) ob_start();

require_once dirname(__DIR__).'/config/db.php';
requireStaff();

if (!isset($pageTitle))   $pageTitle   = 'Dashboard';
if (!isset($activeAdmin)) $activeAdmin = 'dashboard';

$user       = currentUser();
$adminName  = $user['name'] ?? 'Staff';
$adminRole  = $user['role_label'] ?? ucwords(str_replace('_',' ',$user['role'] ?? 'Staff'));
$initials   = implode('',array_map(fn($w)=>strtoupper($w[0]),array_slice(explode(' ',$adminName),0,2)));
$role       = currentRole();

// Badges
try { $appBadge=(int)db()->query("SELECT COUNT(*) FROM applications WHERE status='Application Submitted'")->fetchColumn(); } catch(Throwable $e){$appBadge=0;}
try { $msgBadge=(int)db()->query("SELECT COUNT(*) FROM contact_messages WHERE is_read=0")->fetchColumn(); } catch(Throwable $e){$msgBadge=0;}
$approvalCounts = countPendingApprovals();
$approvalTotal  = $approvalCounts['_total'] ?? 0;

// ── Sidebar definition: key=>[label, icon, href, permission(s)] ──
// permission can be: string, array (any), or 'always'
$sidebar = [

  // ── DASHBOARD
  'dashboard'        => ['Dashboard',          '🏠', BASE_URL.'/admin/index.php',              'always'],

  // ── APPROVAL CENTER (only for roles that approve)
  'approval_center'  => ['Approval Center',    '✅', BASE_URL.'/admin/approval_center.php',    'approvals.act'],

  // ── ADMISSIONS
  '_sep_admissions'  => ['ADMISSIONS', null, null, 'sep'],
  'applications'     => ['Applications',       '📋', BASE_URL.'/admin/applications.php',        'admissions.view'],
  'entrance_exams'   => ['Entrance Exams',     '📝', BASE_URL.'/admin/entrance_exams.php',      'admissions.manage_entrance'],
  'admissions_mgr'   => ['Admission Decisions','✔',  BASE_URL.'/admin/admission_decisions.php', 'admissions.approve'],

  // ── STUDENTS
  '_sep_students'    => ['STUDENTS', null, null, 'sep'],
  'students'         => ['All Students',       '🎓', BASE_URL.'/admin/students.php',            'students.view'],
  'guardians'        => ['Guardians',          '👨‍👩‍👧', BASE_URL.'/admin/guardians.php',           'students.manage_guardians'],
  'documents'        => ['Documents',          '📄', BASE_URL.'/admin/documents.php',           'students.manage_documents'],
  'student_idcards'  => ['Student ID Cards',   '🪪', BASE_URL.'/admin/student_idcards.php',     'students.view'],
  'student_stats'    => ['Student Statistics', '📊', BASE_URL.'/admin/student_statistics.php',  'students.view'],
  'promotion'        => ['Promotion',          '⬆️', BASE_URL.'/admin/promotion.php',           'promotion.view'],

  // ── ACADEMICS
  '_sep_academics'   => ['ACADEMICS', null, null, 'sep'],
  'academic_years'   => ['Academic Years',     '📅', BASE_URL.'/admin/academic_years.php',      'academics.manage_years'],
  'classes'          => ['Classes',            '🏫', BASE_URL.'/admin/classes.php',             'academics.manage_classes'],
  'subjects'         => ['Subjects',           '📚', BASE_URL.'/admin/subjects.php',            'academics.manage_subjects'],
  'departments'      => ['Departments',        '🏢', BASE_URL.'/admin/departments.php',         'teachers.view'],
  'teachers'         => ['Teachers',           '👩‍🏫', BASE_URL.'/admin/teachers_admin.php',      'teachers.view'],
  'assignments'      => ['Teacher Assignments','🔗', BASE_URL.'/admin/teacher_assignments.php', 'academics.assign_teachers'],
  'timetable'        => ['Timetable',          '⏰', BASE_URL.'/admin/timetable.php',           'academics.manage_timetable'],

  // ── ASSESSMENT
  '_sep_assessment'  => ['ASSESSMENT', null, null, 'sep'],
  'marks_entry'      => ['Enter Marks',        '✏️', BASE_URL.'/admin/marks_entry.php',         'marks.create'],
  'marks_approval'   => ['Marks Approval',     '🔍', BASE_URL.'/admin/marks_approval.php',      'marks.review'],
  'results'          => ['Results',            '📊', BASE_URL.'/admin/results.php',             'marks.view'],
  'broadsheets'      => ['Broadsheets',        '📃', BASE_URL.'/admin/broadsheets.php',         'marks.view'],
  'report_cards'     => ['Report Cards',       '📑', BASE_URL.'/admin/report_cards.php',        'reportcards.view'],

  // ── OPERATIONS
  '_sep_ops'         => ['OPERATIONS', null, null, 'sep'],
  'attendance'       => ['Attendance',         '📆', BASE_URL.'/admin/attendance.php',          ['attendance.take','attendance.view']],
  'staff_attendance' => ['Staff Attendance',   '📆', BASE_URL.'/admin/staff_attendance.php',    'teachers.view'],
  'exams'            => ['Examinations',       '📝', BASE_URL.'/admin/entrance_exams.php',      'exams.view'],
  'finance'          => ['Finance',            '💰', BASE_URL.'/admin/finance.php',             'finance.view'],
  'expenses'         => ['Expenses & Budget',  '💸', BASE_URL.'/admin/expenses.php',            'finance.view'],
  'student_transfers'=> ['Transfers & Withdrawals','➡️',BASE_URL.'/admin/student_transfers.php','students.view'],
  'graduation'       => ['Graduation',         '🎓', BASE_URL.'/admin/graduation.php',          'promotion.view'],
  'executive_reports'=> ['Executive Reports',  '📈', BASE_URL.'/admin/executive_reports.php',   'reports.view'],
  'library'          => ['Library',            '📖', BASE_URL.'/admin/library.php',             'library.view'],
  'discipline'       => ['Discipline',         '⚖️', BASE_URL.'/admin/discipline.php',          'discipline.view'],
  'vp_academic'      => ['Academic Overview',  '📊', BASE_URL.'/admin/vp_academic_overview.php','marks.view'],

  // ── COMMUNICATIONS
  '_sep_comms'       => ['COMMUNICATIONS', null, null, 'sep'],
  'announcements'    => ['Announcements',      '📢', BASE_URL.'/admin/announcements.php',       ['comms.view_announcements','comms.create_announcement']],
  'events'           => ['Events',             '🎉', BASE_URL.'/admin/events_admin.php',        'comms.manage_events'],
  'messages'         => ['Messages',           '💬', BASE_URL.'/admin/messages.php',            'comms.view_announcements'],

  // ── SYSTEM
  '_sep_system'      => ['SYSTEM', null, null, 'sep'],
  'users'            => ['Users',              '👥', BASE_URL.'/admin/users.php',               'users.view'],
  'roles'            => ['Roles & Permissions','🔑', BASE_URL.'/admin/roles.php',               'roles.manage'],
  'security'         => ['Security',           '🔐', BASE_URL.'/admin/security.php',            'system.audit_logs'],
  'audit_logs'       => ['Audit Logs',         '🔍', BASE_URL.'/admin/audit_logs.php',          'system.audit_logs'],
  'backup'           => ['Backup & Restore',   '💾', BASE_URL.'/admin/backup.php',              'system.backup'],
  'system_monitor'   => ['System Monitor',     '📊', BASE_URL.'/admin/system_monitor.php',      'system.settings'],
  'reports'          => ['Reports',            '📈', BASE_URL.'/admin/reports.php',             'reports.view'],
  'settings'         => ['Settings',           '⚙️', BASE_URL.'/admin/settings.php',            'system.settings'],
  'registrar_records' => ['Academic Records',  '📊', BASE_URL.'/admin/registrar_records.php',   'reports.view'],
  'registrar_reports' => ['Registrar Reports', '📋', BASE_URL.'/admin/registrar_reports.php',   'reports.view'],
];

// ── Per-role sidebar allowlists ───────────────────────────────
// Defines exactly which sidebar keys each role may see.
// Roles NOT listed here fall through to the normal permission check.
// 'always' keys (dashboard) are added automatically for every role.
$sidebarAllowlist = [

  // ── System Administrator ──────────────────────────────────────
  // Technical/system management only. Should NOT see marks entry,
  // finance, attendance, approval workflows, or student operations.
  'sys_admin'   => [
    'dashboard',
    // Configuration
    '_sep_academics',   // shown as section header
    'academic_years',   // Academic year setup
    'classes',          // Grade / class configuration
    'subjects',         // Subject configuration
    'teachers',         // View teacher list
    'assignments',      // Teacher assignments
    // Communications (oversight only)
    '_sep_comms',
    'announcements',
    'events',
    'messages',
    // System management
    '_sep_system',
    'users',            // User accounts
    'roles',            // Roles & permissions
    'security',         // Security centre, login history, account locks
    'audit_logs',       // Audit logs / security
    'backup',           // Database backup & restore
    'system_monitor',   // System health & monitoring
    'reports',          // System reports
    'settings',         // System configuration & school configuration
  ],

  // super_admin = same as sys_admin (legacy alias)
  'super_admin' => [
    'dashboard',
    '_sep_academics',
    'academic_years',
    'classes',
    'subjects',
    'teachers',
    'assignments',
    '_sep_comms',
    'announcements',
    'events',
    'messages',
    '_sep_system',
    'users',
    'roles',
    'security',
    'audit_logs',
    'backup',
    'system_monitor',
    'reports',
    'settings',
  ],

  // ── School Administrator ──────────────────────────────────────
  // ── School Administrator ──────────────────────────────────────
  // General school administration and operations.
  // Responsible for: school profile, academic calendar, student
  // administration, staff records, class/subject setup, school
  // documents, general reports, announcements, daily operations.
  // Does NOT: enter marks, approve marks, manage finance,
  // handle admissions decisions, access library/discipline modules,
  // or act on approval workflows (submits TO principal instead).
  'school_admin' => [
    'dashboard',
    // Students — full student administration
    '_sep_students',
    'students',           // student directory & registration
    'guardians',          // parent/guardian records
    'documents',          // student documents
    'student_idcards',    // print student ID cards
    'student_stats',      // enrollment statistics
    'promotion',          // student promotion
    // Academics — setup and configuration
    '_sep_academics',
    'academic_years',     // academic calendar
    'classes',            // class/grade setup
    'subjects',           // subject setup
    'departments',        // departments & houses
    'teachers',           // staff records
    'assignments',        // teacher assignments
    'timetable',          // timetable coordination
    // Operations — attendance oversight (student + staff)
    '_sep_ops',
    'attendance',         // student attendance monitoring
    'staff_attendance',   // staff/teacher attendance
    // Communications — school announcements and events
    '_sep_comms',
    'announcements',      // school announcements
    'events',             // school events
    'messages',           // messages / contact
    // System — users and reports (no roles/audit/system settings)
    '_sep_system',
    'users',              // staff user accounts
    'reports',            // all report types
    'settings',           // school profile & configuration
  ],

  // ── Principal ─────────────────────────────────────────────────
  // Head of the school and final institutional authority.
  // Responsible for: overall school management, academic oversight,
  // staff oversight, student affairs, financial oversight, discipline,
  // admissions approval, promotion/graduation, examination oversight,
  // policy implementation, official reports, all final approvals.
  // Does NOT enter marks — reviews and approves instead.
  // Does NOT manage system config (roles, audit logs, settings).
  'principal' => [
    'dashboard',
    'approval_center',       // final approver on ALL workflows

    // Admissions
    '_sep_admissions',
    'applications',
    'entrance_exams',
    'admissions_mgr',

    // Students — full oversight
    '_sep_students',
    'students',
    'guardians',
    'documents',
    'student_idcards',       // print student IDs
    'student_stats',         // enrollment analytics
    'promotion',             // promotion approval
    'student_transfers',     // transfers & withdrawals approval

    // Academics
    '_sep_academics',
    'academic_years',
    'classes',
    'subjects',
    'departments',           // departments & houses
    'teachers',
    'assignments',
    'timetable',

    // Assessment
    '_sep_assessment',
    'marks_approval',
    'results',
    'broadsheets',
    'report_cards',

    // Operations
    '_sep_ops',
    'attendance',
    'staff_attendance',      // teacher attendance oversight
    'exams',
    'finance',               // financial dashboard
    'expenses',              // expense & fee waiver approvals
    'graduation',            // graduation approval
    'discipline',
    'library',

    // Communications
    '_sep_comms',
    'announcements',
    'events',
    'messages',

    // System — reports & staff management
    '_sep_system',
    'users',
    'executive_reports',     // principal analytics dashboard
    'reports',               // data exports
  ],

  // ── Vice Principal ────────────────────────────────────────────
  // ── Vice Principal ────────────────────────────────────────────
  // Assists the Principal with academic and student affairs.
  // Responsible for: academic supervision, teacher supervision,
  // student attendance monitoring, class monitoring, examination
  // supervision, academic performance, discipline, timetable,
  // teacher workload, reviewing/approving teacher submissions.
  // Does NOT manage finances, admissions decisions, or system config.
  // Does NOT enter marks — reviews and approves teacher submissions.
  'vice_principal' => [
    'dashboard',
    'approval_center',       // approves marks, attendance, discipline, promotion

    // Students — student affairs, attendance, promotion, welfare
    '_sep_students',
    'students',              // student directory & monitoring
    'documents',             // student academic documents
    'student_stats',         // enrollment & performance statistics
    'promotion',             // promotion recommendations & approval

    // Academics — full academic oversight
    '_sep_academics',
    'classes',               // class monitoring
    'subjects',              // subject oversight
    'teachers',              // teacher supervision & workload
    'assignments',           // teacher-class assignments
    'timetable',             // timetable supervision

    // Assessment — review & approve teacher submissions
    '_sep_assessment',
    'marks_approval',        // review & approve teacher mark submissions
    'results',               // academic performance oversight
    'broadsheets',           // class/school broadsheets
    'report_cards',          // report card oversight

    // Operations — attendance (student + teacher), exams, discipline
    '_sep_ops',
    'attendance',            // student attendance monitoring
    'staff_attendance',      // teacher attendance oversight
    'exams',                 // examination supervision & records
    'discipline',            // disciplinary recommendations & approval
    'vp_academic',           // academic overview hub (new)

    // Communications
    '_sep_comms',
    'announcements',         // academic & school announcements
    'events',                // school events
    'messages',              // staff/student communications

    // System — reporting
    '_sep_system',
    'reports',               // academic, attendance, teacher, discipline reports
  ],

  // vice_principal_alt = legacy alias — identical to vice_principal
  'vice_principal_alt' => [
    'dashboard',
    'approval_center',
    '_sep_students',
    'students',
    'documents',
    'student_stats',
    'promotion',
    '_sep_academics',
    'classes',
    'subjects',
    'teachers',
    'assignments',
    'timetable',
    '_sep_assessment',
    'marks_approval',
    'results',
    'broadsheets',
    'report_cards',
    '_sep_ops',
    'attendance',
    'staff_attendance',
    'exams',
    'discipline',
    'vp_academic',
    '_sep_comms',
    'announcements',
    'events',
    'messages',
    '_sep_system',
    'reports',
  ],

  // academic_dean = legacy alias — identical to vice_principal
  'academic_dean' => [
    'dashboard',
    'approval_center',
    '_sep_students',
    'students',
    'documents',
    'student_stats',
    'promotion',
    '_sep_academics',
    'classes',
    'subjects',
    'teachers',
    'assignments',
    'timetable',
    '_sep_assessment',
    'marks_approval',
    'results',
    'broadsheets',
    'report_cards',
    '_sep_ops',
    'attendance',
    'staff_attendance',
    'exams',
    'discipline',
    'vp_academic',
    '_sep_comms',
    'announcements',
    'events',
    'messages',
    '_sep_system',
    'reports',
  ],

  // ── Registrar ─────────────────────────────────────────────────
  // ── Registrar ─────────────────────────────────────────────────
  // Official student records and enrollment authority.
  // Responsible for: student applications, registration, admission
  // processing, student profiles, enrollment/re-enrollment, transfers,
  // withdrawals, student documents, IDs, academic records, transcripts,
  // graduation/promotion lists, certificates.
  // Can: create/update student records, verify documents, process
  //   applications, prepare admission & promotion recommendations.
  // Cannot: finalize admission decisions (→ Principal), change exam
  //   results, approve major disciplinary actions.
  'registrar' => [
    'dashboard',

    // Admissions — process applications, prepare recommendations
    '_sep_admissions',
    'applications',          // student applications & admission processing
    'entrance_exams',        // manage entrance exam scheduling
    'admissions_mgr',        // prepare recommendations (final approval → Principal)

    // Students — registration, profiles, enrollment, transfers
    '_sep_students',
    'students',              // student profiles, enrollment, re-enrollment
    'guardians',             // guardian records (part of enrollment)
    'documents',             // student documents, IDs, certificates
    'student_idcards',       // print student ID cards
    'student_transfers',     // transfers, withdrawals, re-enrollment
    'promotion',             // prepare promotion & graduation lists
    'graduation',            // graduation candidates & certificates

    // Academics — view-only for enrollment placement context
    '_sep_academics',
    'classes',               // view class structure for enrollment placement
    'subjects',              // view subjects for academic records
    'timetable',             // view timetable for scheduling context

    // Assessment — academic records & transcripts
    '_sep_assessment',
    'report_cards',          // academic records, transcripts, certificates

    // Communications — school-wide announcements (view)
    '_sep_comms',
    'announcements',

    // System — records & reports
    '_sep_system',
    'registrar_records',     // full academic records hub
    'registrar_reports',     // enrollment, admissions, transfers, graduation reports
    'reports',               // general data exports
  ],

  // ── Accountant / Bursar ───────────────────────────────────────
  // School financial management (merged Accountant + Finance Officer).
  // Responsible for: school fees, fee structures, student invoices,
  // payments, receipts, outstanding balances, discounts, financial
  // records, daily collections, financial reports, expenses, budgets,
  // payment verification, bank/mobile money reconciliation.
  // Can: create invoices, record payments, issue receipts, view
  //   balances, prepare expense requests, verify payments,
  //   reconcile accounts, prepare financial reports.
  // Cannot: delete financial transactions, approve own expenses,
  //   approve major financial adjustments (→ Principal).
  // All finance work lives inside the Finance module (fee structures,
  // payments, receipts, invoices, balances, reconciliation, reports).
  'accountant' => [
    'dashboard',

    // Students — view-only for payment lookups and balance checks
    '_sep_students',
    'students',              // look up students for invoices & payments

    // Operations — all financial management
    '_sep_ops',
    'finance',               // fees, payments, receipts, invoices, balances,
                             // reconciliation, expense requests, fee structures

    // Communications — school-wide notices
    '_sep_comms',
    'announcements',

    // System — financial reports
    '_sep_system',
    'reports',               // daily collections, financial reports, summaries
  ],

  // ── Teacher ──────────────────────────────────────────────────
  // Teaching and student assessment for assigned classes/subjects.
  // Responsible for: attendance, marks entry, classwork, assignments,
  // quizzes, tests, grades, class performance, learning materials.
  // Can: view own students, take attendance, enter/update/submit marks,
  //   create assignments/quizzes, grade work, view class performance.
  // Cannot: approve own marks, publish results, change locked results,
  //   modify another teacher's class, change official student records.
  'teacher' => [
    'dashboard',

    // Students — assigned students only (scoped at page level)
    '_sep_students',
    'students',              // view assigned students

    // Academics — own schedule only
    '_sep_academics',
    'timetable',             // view own teaching timetable

    // Assessment — enter, update, submit marks; view class performance
    '_sep_assessment',
    'marks_entry',           // enter & update draft marks, submit for review
    'results',               // view class performance (own classes only)
    // No: marks_approval (cannot approve own marks)
    // No: report_cards (publishing/approval is VP/Principal)
    // No: broadsheets (school-wide, not teacher scope)

    // Operations — own class attendance only
    '_sep_ops',
    'attendance',            // take & submit attendance for assigned classes

    // Communications — view school announcements
    '_sep_comms',
    'announcements',         // view school-wide notices
  ],

  // ── Class Teacher ─────────────────────────────────────────────
  // Like teacher but also handles discipline for own class,
  // approves attendance corrections, and can create announcements.
  'class_teacher' => [
    'dashboard',
    '_sep_students',
    'students',
    '_sep_academics',
    'timetable',
    '_sep_assessment',
    'marks_entry',
    'results',
    'report_cards',
    '_sep_ops',
    'attendance',
    'discipline',
    '_sep_comms',
    'announcements',
  ],

  // ── Discipline Officer ────────────────────────────────────────
  // Student discipline and behavioral management.
  // Responsible for: discipline cases, incident reports, investigations,
  // student warnings, disciplinary history, parent notifications,
  // recommendations for disciplinary action.
  // Workflow: Incident → Discipline Officer (investigate & recommend)
  //           → Vice Principal → Principal → Final Decision.
  // Can: log incidents, record warnings, document investigations,
  //   prepare recommendations, track disciplinary history.
  // Cannot: approve or finalize disciplinary actions (→ VP/Principal),
  //   access academic records, finance, or system configuration.
  'discipline_officer' => [
    'dashboard',

    // Students — look up students for incidents and disciplinary history
    '_sep_students',
    'students',              // view student profiles and disciplinary history

    // Operations — full discipline management
    '_sep_ops',
    'discipline',            // incidents, investigations, warnings,
                             // recommendations, parent notifications

    // Communications — school-wide notices
    '_sep_comms',
    'announcements',         // view school announcements
  ],

  // ── Librarian ─────────────────────────────────────────────────
  // Manages books, borrowing and returns.
  // ── Librarian ─────────────────────────────────────────────────
  // Library management.
  // Responsible for: books, book categories, book copies, student &
  // teacher borrowing, returns, overdue books, fines, library
  // inventory, and library reports.
  // Can: add/edit/remove books, manage categories and copies, issue
  //   and process returns, track overdue items, apply fines, run
  //   inventory checks, generate library reports.
  // Cannot: access academic records, finance, discipline, or system.
  'librarian' => [
    'dashboard',

    // Students — look up student and teacher borrowers
    '_sep_students',
    'students',              // look up borrowers (students & teachers)

    // Operations — full library management
    '_sep_ops',
    'library',               // books, categories, copies, borrowing,
                             // returns, overdue, fines, inventory

    // Communications — school-wide notices
    '_sep_comms',
    'announcements',         // view school announcements

    // System — library reports
    '_sep_system',
    'reports',               // library inventory, overdue, fines, usage reports
  ],

  // ── ICT Officer ───────────────────────────────────────────────
  // System support: user accounts, audit logs, settings only.
  // ── ICT Officer ───────────────────────────────────────────────
  // School technology and technical/infrastructure management.
  // Responsible for: computers, network, internet, school devices,
  // smart classrooms, system & user technical support, device inventory.
  // Can: manage user accounts (technical support), view audit logs
  //   for system monitoring, manage system/school settings.
  // IMPORTANT: ICT Officer must NOT have access to student marks,
  //   financial records, academic assessments, or student personal data
  //   beyond what is needed for account support.
  // Cannot: manage roles/permissions, access finance, view marks,
  //   access admissions, discipline, or library records.
  'ict_officer' => [
    'dashboard',

    // Communications — view school notices
    '_sep_comms',
    'announcements',         // view school-wide announcements

    // System — technical management only
    '_sep_system',
    'users',                 // user account support (reset passwords, troubleshoot)
    'audit_logs',            // system monitoring, security, troubleshooting
    'settings',              // system configuration, school tech settings
    // No: roles (no roles.manage permission)
    // No: reports (no financial/academic reporting access)
  ],
];

// Helper: should a sidebar item be shown?
function sidebarVisible(string|array $perm): bool {
    if ($perm === 'always') return true;
    if (is_array($perm)) return canAny($perm);
    return can($perm);
}

// Helper: is this key allowed for the current role's allowlist (if any)?
function sidebarAllowed(string $key, string $role, array $allowlist): bool {
    if (!isset($allowlist[$role])) return true; // no allowlist → use permission check
    return in_array($key, $allowlist[$role], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?=e($pageTitle)?> — KHSMIS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Lora:ital,wght@1,500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head>
<body>
<div class="dashboard">

<!-- ── SIDEBAR ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="dash-brand">
    <img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/>
    <span>KHS<span>KHSMIS</span></span>
  </div>

  <!-- User info strip -->
  <div class="sidebar-user">
    <div class="avatar" style="flex-shrink:0"><?=e($initials)?></div>
    <div style="min-width:0">
      <strong style="display:block;font-size:12.5px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($adminName)?></strong>
      <small style="font-size:10.5px;color:rgba(255,255,255,.4)"><?=e(ucwords(str_replace('_',' ',$role)))?></small>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($sidebar as $key => [$label,$icon,$href,$perm]): ?>
      <?php
        // Separator
        if ($perm === 'sep') {
            // Only emit separator if the role's allowlist includes it
            if (!sidebarAllowed($key, $role, $sidebarAllowlist)) continue;
            echo '<div class="sidebar-sep">'.e($label).'</div>';
            continue;
        }
        // Allowlist check (sys_admin and other role-restricted roles)
        if (!sidebarAllowed($key, $role, $sidebarAllowlist)) continue;
        // Permission check (all roles without an allowlist, or roles with one that passed above)
        if ($perm !== 'always' && !sidebarVisible($perm)) continue;

        $isActive = ($activeAdmin === $key);
        $badge    = '';
        if ($key==='applications'  && $appBadge>0)    $badge='<b>'.$appBadge.'</b>';
        if ($key==='messages'      && $msgBadge>0)     $badge='<b>'.$msgBadge.'</b>';
        if ($key==='approval_center'&& $approvalTotal>0)$badge='<b style="background:var(--error)">'.$approvalTotal.'</b>';
        if ($key==='marks_approval')  {
            try { $mb=(int)db()->query("SELECT COUNT(*) FROM assessment_scores WHERE status IN ('submitted','resubmitted')")->fetchColumn(); if($mb>0)$badge='<b>'.$mb.'</b>'; } catch(Throwable $e){}
        }
      ?>
      <a href="<?=e($href)?>" class="<?=$isActive?'active':''?>">
        <span class="nav-icon"><?=$icon?></span>
        <span><?=e($label)?></span>
        <?=$badge?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-bottom">
    <a href="<?=BASE_URL?>/" target="_blank">🌐 View Website</a>
    <a href="<?=BASE_URL?>/admin/logout.php" class="logout-link">⬡ Sign Out</a>
  </div>
</aside>

<!-- ── MAIN AREA ──────────────────────────────────────────── -->
<div class="dashboard-main">
  <div class="dash-topbar">
    <button class="menu-btn dash-menu" id="sidebarToggle" aria-label="Toggle sidebar">☰</button>

    <form class="dash-search" method="get" action="<?=BASE_URL?>/admin/search.php">
      <span>🔍</span>
      <input type="search" name="q" placeholder="Search students, applications, staff…" value="<?=e($_GET['q']??'')?>" autocomplete="off"/>
    </form>

    <!-- ── Academic Year Indicator + Switcher ── -->
    <?php
    $currentAY   = currentAcademicYear();
    $allYears    = [];
    try { $allYears = db()->query("SELECT id,name,is_current,status FROM academic_years ORDER BY start_date DESC")->fetchAll(); } catch(Throwable $e){}
    ?>
    <div class="ay-switcher" style="position:relative">
      <button class="ay-badge" id="aySwitcherBtn" title="Current Academic Year — click to switch"
              style="display:flex;align-items:center;gap:6px;background:var(--primary-soft);border:1.5px solid var(--primary-light,#e0d5ff);border-radius:20px;padding:5px 12px;font-size:12px;font-weight:700;color:var(--primary);cursor:pointer;white-space:nowrap;transition:all .15s">
        <span>📅</span>
        <span><?= e($currentAY['name'] ?? 'No Year Set') ?></span>
        <?php if(count($allYears)>1): ?><span style="font-size:9px;opacity:.6">▾</span><?php endif; ?>
      </button>
      <?php if(count($allYears)>1 && (isSysAdmin()||isSchoolAdmin()||isPrincipal())): ?>
      <div id="aySwitcherMenu" hidden
           style="position:absolute;top:calc(100% + 6px);right:0;background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-lg);min-width:200px;z-index:300;overflow:hidden">
        <div style="padding:8px 14px;font-size:10.5px;font-weight:700;color:var(--ink-faint);text-transform:uppercase;letter-spacing:.07em;border-bottom:1px solid var(--line-soft)">
          Switch Academic Year
        </div>
        <?php foreach($allYears as $yr): ?>
        <form method="post" action="<?=BASE_URL?>/admin/academic_years.php">
          <?=csrfField()?>
          <input type="hidden" name="action"  value="set_current"/>
          <input type="hidden" name="ay_id"   value="<?= $yr['id'] ?>"/>
          <button type="submit" style="width:100%;text-align:left;padding:9px 14px;font-size:13px;display:flex;align-items:center;justify-content:space-between;gap:8px;background:<?=$yr['is_current']?'var(--primary-soft)':'none'?>;color:<?=$yr['is_current']?'var(--primary)':'var(--ink)'?>;font-weight:<?=$yr['is_current']?700:400?>;border:none;cursor:pointer;transition:background .1s"
                  <?= $yr['is_current'] ? 'disabled title="Currently active"' : '' ?>>
            <span><?= e($yr['name']) ?></span>
            <?php if($yr['is_current']): ?>
            <span style="font-size:10px;background:var(--primary);color:#fff;padding:2px 7px;border-radius:10px">Active</span>
            <?php elseif($yr['status']==='closed'): ?>
            <span style="font-size:10px;color:var(--ink-faint)">Closed</span>
            <?php endif; ?>
          </button>
        </form>
        <?php endforeach; ?>
      </div>
      <script>
      (function(){
        const btn  = document.getElementById('aySwitcherBtn');
        const menu = document.getElementById('aySwitcherMenu');
        if(!btn||!menu) return;
        btn.addEventListener('click', e=>{ e.stopPropagation(); menu.hidden=!menu.hidden; });
        document.addEventListener('click', ()=>{ if(menu) menu.hidden=true; });
      })();
      </script>
      <?php endif; ?>
    </div>
    <!-- ── End Academic Year Switcher ── -->

    <div class="dash-user">
      <?php if ($approvalTotal>0 && can('approvals.act')): ?>
      <a href="<?=BASE_URL?>/admin/approval_center.php" class="notification-btn" title="<?=$approvalTotal?> pending approvals" style="position:relative">
        🔔<span style="position:absolute;top:-2px;right:-2px;background:var(--error);color:#fff;font-size:10px;font-weight:700;border-radius:50%;width:17px;height:17px;display:flex;align-items:center;justify-content:center;line-height:1"><?=$approvalTotal?></span>
      </a>
      <?php endif; ?>
      <div class="avatar"><?=e($initials)?></div>
      <div class="dash-user-info">
        <strong><?=e($adminName)?></strong>
        <small><?=e(ucwords(str_replace('_',' ',$role)))?></small>
      </div>
    </div>
  </div>

  <div class="dash-content">
    <?=renderFlash()?>
