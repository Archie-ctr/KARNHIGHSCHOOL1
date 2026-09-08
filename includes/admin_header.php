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
  // Does NOT enter marks (approves/reviews instead).
  // Excluded: roles, audit_logs, settings (perms 135,136,137)
  'principal' => [
    'dashboard',
    'approval_center',
    '_sep_admissions',
    'applications',
    'entrance_exams',
    'admissions_mgr',
    '_sep_students',
    'students',
    'guardians',
    'documents',
    'promotion',
    '_sep_academics',
    'academic_years',
    'classes',
    'subjects',
    'teachers',
    'assignments',
    'timetable',
    '_sep_assessment',
    'marks_approval',   // reviews/approves — does NOT enter marks
    'results',
    'broadsheets',
    'report_cards',
    '_sep_ops',
    'attendance',
    'finance',
    'discipline',
    'library',
    '_sep_comms',
    'announcements',
    'events',
    'messages',
    '_sep_system',
    'users',
    'reports',
  ],

  // ── Vice Principal ────────────────────────────────────────────
  // Assists with academic/student management. First-level approver.
  // Does NOT enter marks. No finance management, no system config.
  'vice_principal' => [
    'dashboard',
    'approval_center',
    '_sep_admissions',
    'applications',        // view/recommend only — admissions_mgr hidden by perm
    '_sep_students',
    'students',
    'guardians',
    'documents',
    'promotion',
    '_sep_academics',
    'academic_years',
    'classes',
    'subjects',
    'teachers',
    'assignments',
    'timetable',
    '_sep_assessment',
    'marks_approval',      // reviews/approves — does NOT enter marks
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
    'users',
    'reports',
  ],

  // vice_principal_alt = legacy alias for vice_principal
  'vice_principal_alt' => [
    'dashboard',
    'approval_center',
    '_sep_admissions',
    'applications',
    '_sep_students',
    'students',
    'guardians',
    'documents',
    'promotion',
    '_sep_academics',
    'academic_years',
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
    'users',
    'reports',
  ],

  // academic_dean = legacy alias for vice_principal
  'academic_dean' => [
    'dashboard',
    'approval_center',
    '_sep_admissions',
    'applications',
    '_sep_students',
    'students',
    'guardians',
    'documents',
    'promotion',
    '_sep_academics',
    'academic_years',
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
    'users',
    'reports',
  ],

  // ── Registrar ─────────────────────────────────────────────────
  // Manages student registration, admissions, and academic records.
  // No marks entry/approval, no finance, no discipline, no system.
  'registrar' => [
    'dashboard',
    '_sep_admissions',
    'applications',
    'entrance_exams',
    'admissions_mgr',      // perm gate (admissions.approve) hides if not granted
    '_sep_students',
    'students',
    'guardians',
    'documents',
    'promotion',
    '_sep_assessment',
    'report_cards',        // view/print only
    '_sep_ops',
    'attendance',          // view/export only
    '_sep_comms',
    'announcements',
    '_sep_system',
    'reports',
  ],

  // ── Accountant / Bursar ───────────────────────────────────────
  // Finance only: payments, fee structures, receipts, reports.
  // No admissions, academics, marks, attendance, discipline, system.
  'accountant' => [
    'dashboard',
    '_sep_students',
    'students',            // view-only for context (payment lookups)
    '_sep_ops',
    'finance',
    '_sep_comms',
    'announcements',
    '_sep_system',
    'reports',
  ],

  // ── Teacher ──────────────────────────────────────────────────
  // Manages own classes: marks entry, attendance, results, timetable.
  // No admissions, academic config, finance, discipline, system.
  'teacher' => [
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
    '_sep_comms',
    'announcements',
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
  // Manages disciplinary incidents only.
  // No academics, marks, finance, library, system.
  'discipline_officer' => [
    'dashboard',
    '_sep_students',
    'students',
    '_sep_ops',
    'discipline',
    '_sep_comms',
    'announcements',
  ],

  // ── Librarian ─────────────────────────────────────────────────
  // Manages books, borrowing and returns.
  // No academics, marks, finance, discipline, system.
  'librarian' => [
    'dashboard',
    '_sep_students',
    'students',            // needed to look up borrowers
    '_sep_ops',
    'library',
    '_sep_comms',
    'announcements',
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
