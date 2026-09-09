<?php
// ============================================================
// Student Portal — Dashboard
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo        = db();
$user       = currentUser();
$activePage = 'dashboard';
$ayId       = currentAcademicYearId();
$ay         = currentAcademicYearName();

// Student record
$student = $pdo->prepare(
    "SELECT s.*, g.name grade_name, c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.user_id = ? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();

if (!$student) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>Student record not found</h2>
            <p>Please contact the school office to link your account.</p>
            <a href="'.BASE_URL.'/admin/logout.php">Sign out</a>
          </div>';
    exit;
}

$ini = strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1));

// Attendance stats
try {
    $attStats = $pdo->prepare(
        "SELECT SUM(status='Present') p, SUM(status='Absent') a, COUNT(*) t
         FROM attendance WHERE student_id=? AND academic_year_id=?"
    );
    $attStats->execute([$student['id'], $ayId]);
    $att = $attStats->fetch();
    $attPct = ($att['t'] > 0) ? round(($att['p'] / $att['t']) * 100, 1) : null;
} catch (Throwable $e) { $att = ['p'=>0,'a'=>0,'t'=>0]; $attPct = null; }

// Academic average
try {
    $avgStmt = $pdo->prepare(
        "SELECT ROUND(AVG(marks_obtained/max_marks*100),1)
         FROM assessment_scores
         WHERE student_id=? AND academic_year_id=?
           AND status IN ('submitted','approved')
           AND marks_obtained IS NOT NULL AND max_marks > 0"
    );
    $avgStmt->execute([$student['id'], $ayId]);
    $avg = $avgStmt->fetchColumn();
} catch (Throwable $e) { $avg = null; }

// Fees paid
try {
    $feesStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0)
         FROM payments WHERE student_id=? AND academic_year_id=? AND currency='LRD'"
    );
    $feesStmt->execute([$student['id'], $ayId]);
    $paid = (float)$feesStmt->fetchColumn();
} catch (Throwable $e) { $paid = 0; }

// Total fees due
try {
    $dueStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0)
         FROM fee_structures
         WHERE academic_year_id=? AND is_active=1 AND currency='LRD'
           AND (grade_id IS NULL OR grade_id=?)"
    );
    $dueStmt->execute([$ayId, $student['current_grade_id']]);
    $due = (float)$dueStmt->fetchColumn();
} catch (Throwable $e) { $due = 0; }

// Latest announcements
try {
    $anns = $pdo->query(
        "SELECT title, message, published_at
         FROM announcements
         WHERE target IN ('all','students')
           AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY published_at DESC LIMIT 4"
    )->fetchAll();
} catch (Throwable $e) { $anns = []; }

// Currently borrowed books — try library_transactions first
try {
    $borrowed = $pdo->prepare("SELECT COUNT(*) FROM library_transactions WHERE student_id=? AND status='Issued'");
    $borrowed->execute([$student['id']]); $borrowedCount=(int)$borrowed->fetchColumn();
} catch (Throwable $e) {
    try {
        $borrowed=$pdo->prepare("SELECT COUNT(*) FROM library_borrowings WHERE student_id=? AND returned_at IS NULL");
        $borrowed->execute([$student['id']]); $borrowedCount=(int)$borrowed->fetchColumn();
    } catch (Throwable $e2) { $borrowedCount=0; }
}

// Overdue books
try{
    $overdueBooks=(int)$pdo->prepare("SELECT COUNT(*) FROM library_transactions WHERE student_id=? AND status='Issued' AND due_date<CURDATE()")->execute([$student['id']]) ? $pdo->query("SELECT COUNT(*) FROM library_transactions WHERE student_id={$student['id']} AND status='Issued' AND due_date<CURDATE()")->fetchColumn() : 0;
}catch(Throwable $e){$overdueBooks=0;}

// Upcoming assessments (due in next 7 days)
try{
    $upcomingAsm=(int)$pdo->query("SELECT COUNT(*) FROM teacher_assessments WHERE class_id={$student['current_class_id']} AND academic_year_id=$ayId AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();
}catch(Throwable $e){$upcomingAsm=0;}

// Available quizzes
try{
    $availQuizzes=(int)$pdo->query("SELECT COUNT(*) FROM teacher_quizzes WHERE class_id={$student['current_class_id']} AND academic_year_id=$ayId AND is_published=1 AND (end_date IS NULL OR end_date>=NOW())")->fetchColumn();
}catch(Throwable $e){$availQuizzes=0;}

// Graduation status
try{$gradRecord=$pdo->query("SELECT status FROM graduation_records WHERE student_id={$student['id']} ORDER BY id DESC LIMIT 1")->fetchColumn();}catch(Throwable $e){$gradRecord=null;}

$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Student Portal — KARN HIGH SCHOOL</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>

<div class="portal-content">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <p style="font-size:13px;color:var(--ink-soft);margin-bottom:2px"><?=date('l, F j, Y')?></p>
      <h1 style="font-size:24px;font-weight:800;margin-bottom:4px"><?=$greet?>, <?= e($student['first_name']) ?>! 👋</h1>
      <p style="color:var(--ink-soft);font-size:13px">
        <?= e($student['grade_name'] ?? '') ?>
        <?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?>
        &mdash; <?= e($ay) ?>
      </p>
    </div>
    <div style="display:flex;align-items:center;gap:12px">
      <div class="avatar" style="width:44px;height:44px;font-size:15px"><?= e($ini) ?></div>
      <div>
        <strong style="font-size:14px"><?= e($student['first_name'].' '.$student['last_name']) ?></strong>
        <div style="font-size:11px;color:var(--ink-faint)"><?= e($student['student_id']) ?></div>
      </div>
    </div>
  </div>

  <?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

  <!-- Alert banners -->
  <?php if($overdueBooks>0):?>
  <div class="alert alert-warning" style="margin-bottom:12px">📚 <strong><?=$overdueBooks?> library book<?=$overdueBooks!=1?'s':''?> overdue.</strong> <a href="library.php" style="font-weight:700">Return now →</a></div>
  <?php endif;?>
  <?php if($upcomingAsm>0):?>
  <div class="alert alert-info" style="margin-bottom:12px">📝 <strong><?=$upcomingAsm?> assessment<?=$upcomingAsm!=1?'s':''?></strong> due in the next 7 days. <a href="assessments.php" style="font-weight:700">View →</a></div>
  <?php endif;?>

  <!-- Metric cards -->
  <div class="metric-grid" style="margin-bottom:24px">
    <div class="metric-card">
      <div class="metric-top"><span>Current Average</span><div class="metric-icon">📊</div></div>
      <strong style="color:<?=$avg?($avg>=70?'var(--green)':($avg>=50?'var(--warning)':'var(--error)')):'inherit'?>"><?= $avg ? $avg.'%' : '—' ?></strong>
      <small><i></i><?= e($ay) ?></small>
    </div>
    <div class="metric-card <?=$attPct!==null&&$attPct<75?'finance-metrics':''?>">
      <div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div>
      <strong style="color:<?=$attPct!==null?($attPct>=80?'var(--green)':($attPct>=70?'var(--warning)':'var(--error)')):'inherit'?>"><?= $attPct !== null ? $attPct.'%' : '—' ?></strong>
      <small><i></i>Days present: <?= $att['p'] ?? 0 ?></small>
    </div>
    <div class="metric-card <?= ($due > 0 && $paid < $due) ? 'finance-metrics' : '' ?>">
      <div class="metric-top"><span>Fees Balance</span><div class="metric-icon">💰</div></div>
      <strong style="<?= ($due > 0 && $paid < $due) ? 'color:var(--error)' : 'color:var(--green)' ?>">
        LRD <?= number_format($due - $paid) ?>
      </strong>
      <small><i></i><?= ($due > 0 && $paid >= $due) ? 'Fully paid' : 'Outstanding' ?></small>
    </div>
    <div class="metric-card <?=$overdueBooks>0?'finance-metrics':''?>">
      <div class="metric-top"><span>Library</span><div class="metric-icon">📖</div></div>
      <strong style="color:<?=$overdueBooks>0?'var(--error)':'inherit'?>"><?= $borrowedCount ?></strong>
      <small><i></i><?=$overdueBooks>0?$overdueBooks.' overdue':'Books on loan'?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Assessments</span><div class="metric-icon">📝</div></div>
      <strong style="color:<?=$upcomingAsm>0?'var(--warning)':'inherit'?>"><?=$upcomingAsm?></strong>
      <small><i></i>Due this week</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Quizzes</span><div class="metric-icon">🧠</div></div>
      <strong><?=$availQuizzes?></strong>
      <small><i></i>Available now</small>
    </div>
  </div>

  <!-- Quick links -->
  <div style="margin-bottom:24px">
    <h3 style="font-size:13px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px">Quick Access</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
      <a href="my_results.php"  class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📊</span><div><strong>My Results</strong><small>Marks &amp; grades</small></div></a>
      <a href="assessments.php" class="quick-item <?=$upcomingAsm>0?'style=&quot;border-color:var(--warning)&quot;':''?>" style="flex-direction:row;align-items:center;gap:12px;padding:14px<?=$upcomingAsm>0?';border-color:var(--warning)':''?>"><span class="qi-icon">📝</span><div><strong>Assessments</strong><small><?=$upcomingAsm?> due soon</small></div></a>
      <a href="timetable.php"   class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📅</span><div><strong>Timetable</strong><small>Class schedule</small></div></a>
      <a href="subjects.php"    class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📚</span><div><strong>Subjects</strong><small>&amp; Materials</small></div></a>
      <a href="fees.php"        class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">💰</span><div><strong>Fees</strong><small>Balance &amp; history</small></div></a>
      <a href="library.php"     class="quick-item <?=$overdueBooks>0?'style=&quot;border-color:var(--error)&quot;':''?>" style="flex-direction:row;align-items:center;gap:12px;padding:14px<?=$overdueBooks>0?';border-color:var(--error)':''?>"><span class="qi-icon">📖</span><div><strong>Library</strong><small><?=$overdueBooks>0?$overdueBooks.' overdue':'Books'?></small></div></a>
      <a href="attendance.php"  class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📆</span><div><strong>Attendance</strong><small><?=$attPct!==null?$attPct.'% rate':'History'?></small></div></a>
      <a href="report_card.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📑</span><div><strong>Report Card</strong><small>Download</small></div></a>
      <a href="graduation.php"  class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">🎓</span><div><strong>Graduation</strong><small><?=$gradRecord?ucfirst($gradRecord):'Check eligibility'?></small></div></a>
    </div>
  </div>

  <!-- Announcements -->
  <?php if (!empty($anns)): ?>
  <div class="panel">
    <div class="panel-heading">
      <div><h3>Latest Announcements</h3></div>
      <a href="<?= BASE_URL ?>/portal/student/announcements.php" class="filter-button">View all →</a>
    </div>
    <?php foreach ($anns as $ann): ?>
    <div class="activity">
      <span class="activity-dot pink"></span>
      <div>
        <strong><?= e($ann['title']) ?></strong>
        <p><?= e(mb_substr($ann['message'], 0, 120)).(mb_strlen($ann['message']) > 120 ? '…' : '') ?></p>
        <small><?= date('M d, Y', strtotime($ann['published_at'])) ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
