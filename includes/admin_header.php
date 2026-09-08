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
  'promotion'        => ['Promotion',          '⬆️', BASE_URL.'/admin/promotion.php',           'promotion.view'],

  // ── ACADEMICS
  '_sep_academics'   => ['ACADEMICS', null, null, 'sep'],
  'academic_years'   => ['Academic Years',     '📅', BASE_URL.'/admin/academic_years.php',      'academics.manage_years'],
  'classes'          => ['Classes',            '🏫', BASE_URL.'/admin/classes.php',             'academics.manage_classes'],
  'subjects'         => ['Subjects',           '📚', BASE_URL.'/admin/subjects.php',            'academics.manage_subjects'],
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
  'exams'            => ['Examinations',       '📝', BASE_URL.'/admin/entrance_exams.php',      'exams.view'],
  'finance'          => ['Finance',            '💰', BASE_URL.'/admin/finance.php',             'finance.view'],
  'library'          => ['Library',            '📖', BASE_URL.'/admin/library.php',             'library.view'],
  'discipline'       => ['Discipline',         '⚖️', BASE_URL.'/admin/discipline.php',          'discipline.view'],

  // ── COMMUNICATIONS
  '_sep_comms'       => ['COMMUNICATIONS', null, null, 'sep'],
  'announcements'    => ['Announcements',      '📢', BASE_URL.'/admin/announcements.php',       ['comms.view_announcements','comms.create_announcement']],
  'events'           => ['Events',             '🎉', BASE_URL.'/admin/events_admin.php',        'comms.manage_events'],
  'messages'         => ['Messages',           '💬', BASE_URL.'/admin/messages.php',            'comms.view_announcements'],

  // ── SYSTEM
  '_sep_system'      => ['SYSTEM', null, null, 'sep'],
  'users'            => ['Users',              '👥', BASE_URL.'/admin/users.php',               'users.view'],
  'roles'            => ['Roles & Permissions','🔑', BASE_URL.'/admin/roles.php',               'roles.manage'],
  'audit_logs'       => ['Audit Logs',         '🔍', BASE_URL.'/admin/audit_logs.php',          'system.audit_logs'],
  'reports'          => ['Reports',            '📈', BASE_URL.'/admin/reports.php',             'reports.view'],
  'settings'         => ['Settings',           '⚙️', BASE_URL.'/admin/settings.php',            'system.settings'],
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
    'audit_logs',       // Audit logs / security
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
    'audit_logs',
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
    // Students — administration and records
    '_sep_students',
    'students',
    'guardians',
    'documents',
    'promotion',
    // Academics — setup and configuration
    '_sep_academics',
    'academic_years',   // academic calendar
    'classes',          // class setup
    'subjects',         // subject setup
    'teachers',         // staff records
    'assignments',      // teacher assignments
    'timetable',        // timetable coordination
    // Operations — attendance oversight
    '_sep_ops',
    'attendance',       // daily operations oversight
    // Communications — school announcements and events
    '_sep_comms',
    'announcements',    // school announcements
    'events',           // school events
    'messages',         // messages / contact
    // System — users and reports (no roles/audit/system settings)
    '_sep_system',
    'users',            // staff user accounts
    'reports',          // general reports
    'settings',         // school profile & configuration
  ],

  // ── Principal ─────────────────────────────────────────────────
  // Senior academic/administrative authority. Final approver.
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

    // Admissions — approve, reject, manage entrance exams
    '_sep_admissions',
    'applications',          // view all applications
    'entrance_exams',        // examination oversight
    'admissions_mgr',        // admissions approval / rejection

    // Students — student affairs
    '_sep_students',
    'students',              // all student records
    'guardians',             // guardian records
    'documents',             // student documents
    'promotion',             // promotion & graduation approval

    // Academics — academic oversight
    '_sep_academics',
    'academic_years',        // academic calendar oversight
    'classes',               // class/grade structure
    'subjects',              // subject oversight
    'teachers',              // staff oversight
    'assignments',           // teacher assignments
    'timetable',             // timetable oversight

    // Assessment — review & approve, NOT enter
    '_sep_assessment',
    'marks_approval',        // approve/reject submitted marks
    'results',               // examination results oversight
    'broadsheets',           // official broadsheets
    'report_cards',          // approve & publish report cards

    // Operations — financial oversight, discipline, attendance, exams
    '_sep_ops',
    'attendance',            // attendance oversight
    'exams',                 // examination oversight
    'finance',               // financial oversight: major expenses, fee waivers
    'discipline',            // approve major disciplinary actions
    'library',               // library oversight

    // Communications — announcements, policy, events
    '_sep_comms',
    'announcements',         // official school announcements
    'events',                // school events
    'messages',              // communications oversight

    // System — staff user management, official reports
    '_sep_system',
    'users',                 // staff oversight (view/manage user accounts)
    'reports',               // official school-wide reports
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

    // Students — student affairs, attendance monitoring, promotion
    '_sep_students',
    'students',              // student monitoring
    'documents',             // student academic documents
    'promotion',             // promotion recommendations & approval

    // Academics — full academic oversight
    '_sep_academics',
    'classes',               // class monitoring
    'subjects',              // subject oversight
    'teachers',              // teacher supervision & workload
    'assignments',           // teacher-class assignments
    'timetable',             // timetable supervision

    // Assessment — review teacher submissions, academic performance
    '_sep_assessment',
    'marks_approval',        // review & approve teacher mark submissions
    'results',               // academic performance oversight
    'broadsheets',           // class/school broadsheets
    'report_cards',          // report card oversight

    // Operations — attendance, exams, discipline
    '_sep_ops',
    'attendance',            // student attendance monitoring
    'exams',                 // examination supervision & records
    'discipline',            // disciplinary recommendations & approval

    // Communications
    '_sep_comms',
    'announcements',         // academic & school announcements
    'events',                // school events
    'messages',              // staff/student communications

    // System — reporting
    '_sep_system',
    'reports',               // academic & operational reports
  ],

  // vice_principal_alt = legacy alias — identical to vice_principal
  'vice_principal_alt' => [
    'dashboard',
    'approval_center',
    '_sep_students',
    'students',
    'documents',
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
    'exams',
    'discipline',
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
    'exams',
    'discipline',
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
    'promotion',             // prepare promotion & graduation lists

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
    'reports',               // enrollment, admission, graduation reports
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
  // No roles.manage. No academic/finance/marks operations.
  'ict_officer' => [
    'dashboard',
    '_sep_comms',
    'announcements',
    '_sep_system',
    'users',
    'audit_logs',
    'settings',
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
