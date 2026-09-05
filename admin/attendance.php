<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
requireRole(['sys_admin','school_admin','principal','vice_principal','registrar','teacher','class_teacher']);

$pdo  = db();
$ayId = currentAcademicYearId();

$canRecord = can('take_attendance');  // teacher, class_teacher, vice_principal, principal, school_admin, sys_admin
$canView   = can('view_attendance');  // registrar + above also see

// Teachers see only their assigned classes; admin roles see all
$isTeacherRole = hasRole(['teacher','class_teacher']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save') {
    verifyCsrf();
    if (!$canRecord) {
        flash('error','You do not have permission to record attendance.');
        redirect(BASE_URL.'/admin/attendance.php');
    }
    $clsId = (int)($_POST['class_id'] ?? 0);
    $date  = $_POST['att_date'] ?? '';
    if ($clsId && $date) {
        // Teachers: only record for their own assigned classes
        if ($isTeacherRole) {
            $teacherId = (int)($pdo->query("SELECT id FROM teachers WHERE user_id=".currentUser()['id'])->fetchColumn() ?? 0);
            $ok = $pdo->prepare("SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND academic_year_id=?")->execute([$teacherId,$clsId,$ayId]) ? $pdo->query("SELECT COUNT(*) FROM teacher_assignments WHERE teacher_id=$teacherId AND class_id=$clsId AND academic_year_id=$ayId")->fetchColumn() : 0;
            if (!$ok) { flash('error','You are not assigned to this class.'); redirect(BASE_URL.'/admin/attendance.php'); }
        }
        foreach (($_POST['status'] ?? []) as $stdId => $st) {
            $stdId = (int)$stdId;
            $st    = in_array($st, ['Present','Absent','Late','Excused'], true) ? $st : 'Present';
            $remark= trim($_POST['remarks'][$stdId] ?? '');
            $pdo->prepare("INSERT INTO attendance (student_id,class_id,academic_year_id,date,status,remarks,recorded_by)
                VALUES (?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE status=VALUES(status),remarks=VALUES(remarks),recorded_by=VALUES(recorded_by)")
               ->execute([$stdId,$clsId,$ayId,$date,$st,$remark?:null,currentUser()['id']]);
        }
        flash('success','Attendance saved for '.date('M d, Y',strtotime($date)).'.');
    }
    redirect(BASE_URL.'/admin/attendance.php?class_id='.$clsId.'&att_date='.$date);
}

// Build class list based on role
if ($isTeacherRole) {
    $teacherId = (int)($pdo->query("SELECT id FROM teachers WHERE user_id=".currentUser()['id'])->fetchColumn() ?? 0);
    $classesStmt = $pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name");
    $classesStmt->execute([$teacherId,$ayId]); $classes=$classesStmt->fetchAll();
} else {
    $classesStmt = $pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name");
    $classesStmt->execute([$ayId]); $classes=$classesStmt->fetchAll();
}

$selClass = (int)($_GET['class_id'] ?? 0);
$selDate  = $_GET['att_date'] ?? date('Y-m-d');
$students = []; $existing = [];

if ($selClass) {
    $sts=$pdo->prepare("SELECT id,student_id,first_name,last_name FROM students WHERE current_class_id=? AND status='Active' ORDER BY last_name,first_name");
    $sts->execute([$selClass]); $students=$sts->fetchAll();
    $ex=$pdo->prepare("SELECT student_id,status,remarks FROM attendance WHERE class_id=? AND date=? AND academic_year_id=?");
    $ex->execute([$selClass,$selDate,$ayId]); $existing=array_column($ex->fetchAll(),null,'student_id');
}

// Summary report (last 14 days)
$report=[];
if ($selClass) {
    $rpt=$pdo->prepare("SELECT a.date,SUM(a.status='Present') present,SUM(a.status='Absent') absent,SUM(a.status='Late') late,SUM(a.status='Excused') excused,COUNT(*) total FROM attendance a WHERE a.class_id=? AND a.academic_year_id=? GROUP BY a.date ORDER BY a.date DESC LIMIT 14");
    $rpt->execute([$selClass,$ayId]); $report=$rpt->fetchAll();
}

$pageTitle   = 'Attendance';
$activeAdmin = 'attendance';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Attendance <span></span></div>
    <h1>Daily Attendance</h1>
    <p><?= $isTeacherRole ? 'Take attendance for your assigned classes.' : 'Record and view class attendance.' ?></p>
  </div>
</div>

<form method="get" class="filter-row" style="margin-bottom:24px">
  <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:160px">
    <option value="">Select Class…</option>
    <?php foreach($classes as $c): ?><option value="<?=$c['id']?>" <?=$selClass==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach; ?>
  </select>
  <input type="date" name="att_date" value="<?= e($selDate) ?>" class="filter-button" onchange="this.form.submit()"/>
</form>

<?php if ($selClass && !empty($students)): ?>

<?php if ($canRecord): ?>
<form method="post">
  <?= csrfField() ?>
  <input type="hidden" name="action"   value="save"/>
  <input type="hidden" name="class_id" value="<?= $selClass ?>"/>
  <input type="hidden" name="att_date" value="<?= e($selDate) ?>"/>
  <div class="form-section">
    <div class="form-section-title"><?= date('l, F d, Y', strtotime($selDate)) ?></div>
    <div style="display:flex;gap:8px;margin-bottom:14px">
      <button type="button" class="button button-sm button-secondary" onclick="document.querySelectorAll('.att-sel').forEach(s=>s.value='Present')">✓ All Present</button>
      <button type="button" class="button button-sm button-secondary" onclick="document.querySelectorAll('.att-sel').forEach(s=>s.value='Absent')">✗ All Absent</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Status</th><th>Remarks</th></tr></thead>
        <tbody>
          <?php foreach ($students as $i => $st):
            $cur = $existing[$st['id']]['status']  ?? 'Present';
            $rmk = $existing[$st['id']]['remarks'] ?? '';
          ?>
          <tr>
            <td class="muted"><?= $i+1 ?></td>
            <td><strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong></td>
            <td class="muted"><?= e($st['student_id']) ?></td>
            <td>
              <select name="status[<?= $st['id'] ?>]" class="att-sel" style="padding:6px 10px;border:1px solid var(--line);border-radius:6px;font-size:13px;background:var(--bg)">
                <?php foreach(['Present','Absent','Late','Excused'] as $opt): ?>
                <option value="<?= $opt ?>" <?= $cur===$opt?'selected':'' ?>><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </td>
            <td><input type="text" name="remarks[<?= $st['id'] ?>]" value="<?= e($rmk) ?>" placeholder="Optional…" style="padding:6px 10px;border:1px solid var(--line);border-radius:6px;font-size:13px;width:160px;background:var(--bg)"/></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button type="submit" class="button button-primary">💾 Save Attendance</button>
    </div>
  </div>
</form>

<?php else: ?>
<!-- View-only mode (e.g. school_admin viewing) -->
<div class="form-section">
  <div class="form-section-title"><?= date('l, F d, Y', strtotime($selDate)) ?> — <span class="status new-s">View Only</span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody>
        <?php foreach ($students as $i => $st):
          $cur = $existing[$st['id']]['status']  ?? '—';
          $rmk = $existing[$st['id']]['remarks'] ?? '';
          $cls = match($cur){'Present'=>'approved','Absent'=>'warning','Late'=>'pending','Excused'=>'new-s',default=>''};
        ?>
        <tr>
          <td class="muted"><?= $i+1 ?></td>
          <td><strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong></td>
          <td class="muted"><?= e($st['student_id']) ?></td>
          <td><?= $cls ? '<span class="status '.$cls.'">'.e($cur).'</span>' : '<span class="muted">—</span>' ?></td>
          <td class="muted"><?= e($rmk ?: '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php elseif ($selClass && empty($students)): ?>
<div style="text-align:center;padding:36px;color:var(--ink-faint)">No active students in this class.</div>
<?php elseif (empty($classes)): ?>
<div class="alert alert-warning">No classes available. <?= $isTeacherRole ? 'You have not been assigned to any class yet.' : 'Please create classes first.' ?></div>
<?php endif; ?>

<!-- Summary -->
<?php if (!empty($report)): ?>
<div class="form-section" style="margin-top:24px">
  <div class="form-section-title">Attendance Summary — Last 14 Days</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th><th>Rate</th></tr></thead>
      <tbody>
        <?php foreach ($report as $r):
          $rate = $r['total'] > 0 ? round(($r['present']/$r['total'])*100,1) : 0;
        ?>
        <tr>
          <td><?= date('M d, Y (D)', strtotime($r['date'])) ?></td>
          <td style="color:var(--green);font-weight:700"><?= $r['present'] ?></td>
          <td style="color:var(--error);font-weight:700"><?= $r['absent'] ?></td>
          <td style="color:var(--warning)"><?= $r['late'] ?></td>
          <td style="color:var(--blue)"><?= $r['excused'] ?></td>
          <td><?= $rate ?>%</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
