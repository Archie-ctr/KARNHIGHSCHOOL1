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

// Helper: should a sidebar item be shown?
function sidebarVisible(string|array $perm): bool {
    if ($perm === 'always') return true;
    if (is_array($perm)) return canAny($perm);
    return can($perm);
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
            echo '<div class="sidebar-sep">'.e($label).'</div>';
            continue;
        }
        // Permission check
        if (!sidebarVisible($perm)) continue;

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
