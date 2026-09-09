<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);

$activePage = 'students';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$officer = null;
try { $s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1"); $s->execute([$user['id']]); $officer=$s->fetch()?:null; } catch (Throwable $e) {}
if (!$officer) $officer=['first_name'=>$user['username']??'Officer','last_name'=>'','id'=>0];

$studentId = (int)($_GET['id'] ?? 0);
$fSearch   = trim($_GET['q'] ?? '');
$fClass    = (int)($_GET['class_id'] ?? 0);

// Student detail
$student = null;
$history  = [];
if ($studentId) {
    try {
        $student = $pdo->query(
            "SELECT s.*,c.name class_name,g.name grade_name
             FROM students s
             LEFT JOIN classes c ON c.id=s.current_class_id
             LEFT JOIN grades g ON g.id=s.current_grade_id
             WHERE s.id=$studentId"
        )->fetch();
        $history = $pdo->query(
            "SELECT dr.*,DATE_FORMAT(dr.date_occurred,'%d %b %Y') date_fmt
             FROM discipline_records dr
             WHERE dr.student_id=$studentId
             ORDER BY dr.date_occurred DESC"
        )->fetchAll();
    } catch (Throwable $e) {}
    // Guardian
    try {
        $guardian=$pdo->query("SELECT * FROM guardians WHERE student_id=$studentId AND is_primary=1 LIMIT 1")->fetch();
    } catch(Throwable $e){$guardian=null;}
}

// List
if (!$studentId) {
    $where = ["s.status='Active'"];
    $params = [];
    if ($fSearch) {
        $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)";
        $p="%$fSearch%"; $params=[$p,$p,$p];
    }
    if ($fClass) { $where[]="s.current_class_id=?"; $params[]=$fClass; }
    $ws = implode(' AND ',$where);
    try {
        $students = $pdo->prepare(
            "SELECT s.*,c.name class_name,g.name grade_name,
                    (SELECT COUNT(*) FROM discipline_records dr WHERE dr.student_id=s.id AND dr.academic_year_id=$ayId) inc_count
             FROM students s
             LEFT JOIN classes c ON c.id=s.current_class_id
             LEFT JOIN grades g ON g.id=s.current_grade_id
             WHERE $ws ORDER BY inc_count DESC,s.last_name LIMIT 100"
        );
        $students->execute($params); $students=$students->fetchAll();
    } catch (Throwable $e) { $students=[]; }
    try { $allClasses=$pdo->query("SELECT id,name FROM classes ORDER BY name")->fetchAll(); } catch(Throwable $e){$allClasses=[];}
}

$statusColors=['open'=>'var(--error)','investigating'=>'var(--warning)','resolved'=>'var(--green)','closed'=>'var(--ink-soft)'];
$severityColors=['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Student Profiles — Discipline Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Student Profiles</h1><p>Discipline history by student &mdash; <?= e($ay) ?></p></div>
    <?php if($studentId):?><a href="students.php" class="button button-secondary">← All Students</a><?php endif;?>
  </div>

  <?php if ($studentId && $student): ?>
  <!-- Student detail view -->
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px;margin-bottom:16px">
    <div class="panel" style="padding:20px">
      <div style="text-align:center;margin-bottom:14px">
        <div class="avatar" style="width:56px;height:56px;font-size:18px;margin:0 auto 10px">
          <?= strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1)) ?>
        </div>
        <h3 style="font-weight:700;margin-bottom:2px"><?= e($student['first_name'].' '.$student['last_name']) ?></h3>
        <p style="color:var(--ink-soft);font-size:13px"><?= e($student['student_id']) ?></p>
      </div>
      <div style="font-size:13px;display:flex;flex-direction:column;gap:6px">
        <div><span class="muted">Class:</span> <strong><?= e($student['class_name']??'—') ?></strong></div>
        <div><span class="muted">Grade:</span> <?= e($student['grade_name']??'—') ?></div>
        <div><span class="muted">Status:</span> <span class="status approved" style="font-size:11px"><?= e($student['status']??'Active') ?></span></div>
        <div><span class="muted">Incidents:</span> <strong style="color:<?= count($history)>2?'var(--error)':'var(--ink2)' ?>"><?= count($history) ?></strong></div>
      </div>
      <?php if (!empty($guardian)): ?>
      <div style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px;font-size:12.5px">
        <div style="font-weight:700;margin-bottom:6px;color:var(--ink-soft)">GUARDIAN</div>
        <div><?= e($guardian['first_name'].' '.$guardian['last_name']) ?></div>
        <?php if ($guardian['phone']): ?><div>📞 <?= e($guardian['phone']) ?></div><?php endif; ?>
        <?php if ($guardian['email']): ?><div>✉️ <?= e($guardian['email']) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
      <div style="margin-top:12px">
        <a href="incidents.php?action=new" class="button button-primary" style="width:100%;text-align:center">+ Log Incident</a>
      </div>
    </div>

    <div class="panel" style="padding:18px">
      <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">Discipline History</h3>
      <?php if (empty($history)): ?>
      <p style="color:var(--ink-faint)">No incidents on record for this student.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Type</th><th>Severity</th><th>Status</th><th>Parent</th></tr></thead>
          <tbody>
            <?php foreach ($history as $h): ?>
            <tr>
              <td class="muted"><?= $h['date_fmt'] ?></td>
              <td><?= e($h['violation_type']) ?></td>
              <td><span style="font-weight:700;font-size:11px;color:<?= $severityColors[$h['severity']??'minor'] ?>"><?= ucfirst($h['severity']??'minor') ?></span></td>
              <td><span class="status" style="background:<?= $statusColors[$h['status']??'open'] ?>;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px"><?= ucfirst($h['status']??'open') ?></span></td>
              <td style="color:<?= $h['parent_notified']?'var(--green)':'var(--error)' ?>;font-size:12px;font-weight:600"><?= $h['parent_notified']?'✓':'✗' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- Search & List -->
  <form method="get" class="filter-bar" style="margin-bottom:16px">
    <input type="text" name="q" placeholder="Search student name or ID…" value="<?= e($fSearch) ?>"/>
    <select name="class_id">
      <option value="">All Classes</option>
      <?php foreach ($allClasses??[] as $cl): ?><option value="<?= $cl['id'] ?>" <?= $fClass==$cl['id']?'selected':'' ?>><?= e($cl['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="button button-secondary button-sm">Filter</button>
    <a href="students.php" class="button button-secondary button-sm">Reset</a>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>ID</th><th>Class</th><th>Incidents</th><th>Risk</th><th>Action</th></tr></thead>
      <tbody>
        <?php if (empty($students)): ?>
        <tr><td colspan="6" style="text-align:center;color:var(--ink-faint);padding:32px">No students found.</td></tr>
        <?php else: foreach ($students as $s): $risk=$s['inc_count']>3?'High':($s['inc_count']>1?'Moderate':'Low'); ?>
        <tr>
          <td><strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong></td>
          <td class="muted"><?= e($s['student_id']) ?></td>
          <td class="muted"><?= e($s['class_name']??'—') ?></td>
          <td><strong style="color:<?= $s['inc_count']>2?'var(--error)':'var(--ink2)' ?>"><?= $s['inc_count'] ?></strong></td>
          <td><span style="font-size:11px;font-weight:700;color:<?= $risk==='High'?'var(--error)':($risk==='Moderate'?'var(--warning)':'var(--green)') ?>"><?= $risk ?></span></td>
          <td><a href="?id=<?= $s['id'] ?>" class="button button-secondary button-sm">View</a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
